package com.techybugs.gymatlas.member

import android.Manifest
import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.bluetooth.BluetoothManager
import android.bluetooth.le.ScanCallback
import android.bluetooth.le.ScanFilter
import android.bluetooth.le.ScanResult
import android.bluetooth.le.ScanSettings
import android.content.Context
import android.content.BroadcastReceiver
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.IBinder
import android.os.ParcelUuid
import android.util.Base64
import org.json.JSONObject
import java.util.UUID
import java.util.concurrent.Executors
import java.util.concurrent.ScheduledExecutorService
import java.util.concurrent.TimeUnit

class SmartAttendanceScanService : Service() {
    private val serviceUuid = ParcelUuid(UUID.fromString(SmartAttendanceScanContract.ATLAS_SERVICE_UUID))
    private var scanCallback: ScanCallback? = null
    private lateinit var configStore: SmartAttendanceBackgroundConfigStore
    private lateinit var sessionStore: SmartAttendanceNativeSessionStore
    private lateinit var client: SmartAttendanceNativeClient
    private var worker: ScheduledExecutorService? = null
    @Volatile private var lastDetectionDispatchAt = 0L
    private val firstQualifiedByHub = mutableMapOf<String, Long>()
    private val lastQualifiedByHub = mutableMapOf<String, Long>()
    private val lastAttemptByHub = mutableMapOf<String, Long>()

    override fun onCreate() {
        super.onCreate()
        configStore = SmartAttendanceBackgroundConfigStore(this)
        sessionStore = SmartAttendanceNativeSessionStore(this)
        client = SmartAttendanceNativeClient(this)
        worker = Executors.newSingleThreadScheduledExecutor().also { executor ->
            executor.scheduleAtFixedRate(::finalizeIfNeeded, 1, 1, TimeUnit.MINUTES)
        }
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(CHANNEL_ID, "Smart Attendance", NotificationManager.IMPORTANCE_LOW),
        )
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            setEnabled(this, false)
            stopScanning()
            stopSelf()
            return START_NOT_STICKY
        }
        setEnabled(this, true)
        startForeground(NOTIFICATION_ID, notification())
        startScanning()
        return START_STICKY
    }

    override fun onDestroy() {
        stopScanning()
        worker?.shutdownNow()
        worker = null
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    @SuppressLint("MissingPermission")
    private fun startScanning() {
        if (scanCallback != null || !hasScanPermission()) return
        val adapter = getSystemService(BluetoothManager::class.java)?.adapter ?: return
        val scanner = adapter.bluetoothLeScanner ?: return
        val callback = object : ScanCallback() {
            override fun onScanResult(callbackType: Int, result: ScanResult) = emit(result)

            override fun onBatchScanResults(results: MutableList<ScanResult>) {
                results.forEach(::emit)
            }
        }
        scanCallback = callback
        scanner.startScan(
            listOf(
                ScanFilter.Builder().setServiceUuid(serviceUuid).build(),
                ScanFilter.Builder().setManufacturerData(
                    SmartAttendanceScanContract.IBEACON_COMPANY_ID,
                    byteArrayOf(0x02, 0x15),
                    byteArrayOf(0xff.toByte(), 0xff.toByte()),
                ).build(),
            ),
            ScanSettings.Builder()
                .setScanMode(ScanSettings.SCAN_MODE_LOW_POWER)
                .setCallbackType(ScanSettings.CALLBACK_TYPE_ALL_MATCHES)
                .setReportDelay(BACKGROUND_REPORT_DELAY_MS)
                .build(),
            callback,
        )
    }

    @SuppressLint("MissingPermission")
    private fun stopScanning() {
        val callback = scanCallback ?: return
        if (hasScanPermission()) {
            getSystemService(BluetoothManager::class.java)?.adapter?.bluetoothLeScanner?.stopScan(callback)
        }
        scanCallback = null
    }

    private fun emit(result: ScanResult) {
        val record = result.scanRecord ?: return
        val data = record.getServiceData(serviceUuid)
        val beaconData = record.getManufacturerSpecificData(SmartAttendanceScanContract.IBEACON_COMPANY_ID)
        val detection = data?.let(SmartAttendanceNativeProtocol::decode)
            ?: beaconData?.let(SmartAttendanceNativeProtocol::decodeIBeacon)
            ?: return
        val detectedAt = System.currentTimeMillis()
        if (detectedAt - lastDetectionDispatchAt < NATIVE_DETECTION_INTERVAL_MS) return
        lastDetectionDispatchAt = detectedAt
        SmartAttendanceDetectionQueue.save(this, detection, data, result.rssi, detectedAt)
        sendBroadcast(
            Intent(SmartAttendanceScanContract.ACTION_DETECTION)
                .setPackage(packageName)
                .apply {
                    data?.let { putExtra(SmartAttendanceScanContract.EXTRA_SERVICE_DATA, it) }
                    detection.hubId?.let { putExtra(SmartAttendanceScanContract.EXTRA_HUB_ID, it) }
                }
                .putExtra(SmartAttendanceScanContract.EXTRA_RSSI, result.rssi)
                .putExtra(SmartAttendanceScanContract.EXTRA_DETECTED_AT, detectedAt),
        )
        worker?.execute { handlePresence(detection, result.rssi, detectedAt) }
    }

    private fun handlePresence(detection: SmartAttendanceNativePayload, rssi: Int, detectedAt: Long) {
        val config = configStore.load() ?: return
        val hubKey = detection.key.uppercase()
        if (rssi < MINIMUM_RSSI) {
            firstQualifiedByHub.remove(hubKey)
            lastQualifiedByHub.remove(hubKey)
            return
        }

        val previous = lastQualifiedByHub[hubKey]
        if (previous == null || detectedAt < previous || detectedAt - previous > PRESENCE_CONTINUITY_MS) {
            firstQualifiedByHub[hubKey] = detectedAt
        }
        lastQualifiedByHub[hubKey] = detectedAt

        var session = sessionStore.load()
        if (session != null && detectedAt >= session.windowEndsAtMs) {
            if (session.checkedOutAtMs == null && !finishSession(config, session)) return
            sessionStore.clear()
            session = null
        }

        if (session != null && detectedAt < session.windowEndsAtMs && session.checkedOutAtMs == null) {
            if (detectedAt >= session.lastPresenceAtMs) {
                sessionStore.save(
                    session.copy(hubPublicId = hubKey, lastPresenceAtMs = detectedAt),
                )
            }
            return
        }

        val firstSeen = firstQualifiedByHub[hubKey] ?: detectedAt
        if (detectedAt - firstSeen < PRESENCE_CONFIRMATION_MS) return
        val lastAttempt = lastAttemptByHub[hubKey]
        if (lastAttempt != null && detectedAt - lastAttempt < REQUEST_DEBOUNCE_MS) return
        lastAttemptByHub[hubKey] = detectedAt

        runCatching { client.checkIn(config, detection, rssi, firstSeen) }
            .onSuccess { response ->
                sessionStore.save(
                    response.copy(
                        lastPresenceAtMs = maxOf(response.lastPresenceAtMs, detectedAt),
                        checkedOutAtMs = null,
                    ),
                )
                updateNotification("Background attendance active · check-in synced")
            }
            .onFailure {
                updateNotification("Background attendance active · waiting to sync")
            }
    }

    private fun finalizeIfNeeded() {
        val config = configStore.load() ?: return
        val session = sessionStore.load() ?: return
        val now = System.currentTimeMillis()
        if (session.checkedOutAtMs != null) {
            if (now >= session.windowEndsAtMs) sessionStore.clear()
            return
        }
        if (now < session.windowEndsAtMs && now < session.lastPresenceAtMs + ABSENCE_TIMEOUT_MS) return
        if (finishSession(config, session)) {
            if (now >= session.windowEndsAtMs) sessionStore.clear()
            else sessionStore.save(session.copy(checkedOutAtMs = session.lastPresenceAtMs))
        }
    }

    private fun finishSession(
        config: SmartAttendanceBackgroundConfig,
        session: SmartAttendanceNativeSession,
    ): Boolean = runCatching { client.checkOut(config, session) }
        .onSuccess { updateNotification("Background attendance active · out time synced") }
        .onFailure { updateNotification("Background attendance active · out time waiting to sync") }
        .isSuccess

    private fun hasScanPermission(): Boolean =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN) == PackageManager.PERMISSION_GRANTED
        } else {
            checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
                checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        }

    private fun notification(): Notification = Notification.Builder(this, CHANNEL_ID)
        .setContentTitle("Gym Atlas Smart Attendance")
        .setContentText("Recording nearby gym presence in the background")
        .setSmallIcon(R.drawable.ic_stat_chat)
        .setOngoing(true)
        .build()

    private fun updateNotification(text: String) {
        getSystemService(NotificationManager::class.java).notify(
            NOTIFICATION_ID,
            Notification.Builder(this, CHANNEL_ID)
                .setContentTitle("Gym Atlas Smart Attendance")
                .setContentText(text)
                .setSmallIcon(R.drawable.ic_stat_chat)
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .build(),
        )
    }

    companion object {
        const val ACTION_STOP = "com.techybugs.gymatlas.member.STOP_SMART_ATTENDANCE_SCAN"
        private const val CHANNEL_ID = "gym_atlas_smart_attendance"
        private const val NOTIFICATION_ID = 4201
        private const val BACKGROUND_REPORT_DELAY_MS = 15_000L
        private const val NATIVE_DETECTION_INTERVAL_MS = 1_000L
        private const val MINIMUM_RSSI = -78
        private const val PRESENCE_CONFIRMATION_MS = 2_400L
        private const val PRESENCE_CONTINUITY_MS = 30_000L
        private const val REQUEST_DEBOUNCE_MS = 15_000L
        private const val ABSENCE_TIMEOUT_MS = 2 * 60 * 60 * 1000L
        private const val PREFS = "smart_attendance_scan_service"
        private const val KEY_ENABLED = "enabled"

        fun isEnabled(context: Context): Boolean =
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean(KEY_ENABLED, false)

        private fun setEnabled(context: Context, enabled: Boolean) {
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
                .edit()
                .putBoolean(KEY_ENABLED, enabled)
                .apply()
        }
    }
}

class SmartAttendanceBootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED && intent.action != Intent.ACTION_MY_PACKAGE_REPLACED) return
        if (!SmartAttendanceScanService.isEnabled(context)) return
        context.startForegroundService(Intent(context, SmartAttendanceScanService::class.java))
    }
}

object SmartAttendanceScanContract {
    const val ATLAS_SERVICE_UUID = "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1"
    const val IBEACON_COMPANY_ID = 0x004C
    const val ACTION_DETECTION = "com.techybugs.gymatlas.member.SMART_ATTENDANCE_DETECTION"
    const val EXTRA_SERVICE_DATA = "serviceData"
    const val EXTRA_HUB_ID = "hubId"
    const val EXTRA_RSSI = "rssi"
    const val EXTRA_DETECTED_AT = "detectedAt"
}

object SmartAttendanceDetectionQueue {
    private const val PREFS = "smart_attendance_background_detections"
    private const val KEY = "latest_by_hub"

    fun save(
        context: Context,
        detection: SmartAttendanceNativePayload,
        data: ByteArray?,
        rssi: Int,
        detectedAt: Long,
    ) {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val root = runCatching { JSONObject(prefs.getString(KEY, "{}") ?: "{}") }.getOrDefault(JSONObject())
        val encoded = data?.let { Base64.encodeToString(it, Base64.NO_WRAP) }
        root.put(detection.key, JSONObject()
            .put("data", encoded)
            .put("hubId", detection.hubId)
            .put("rssi", rssi)
            .put("detectedAt", detectedAt))
        prefs.edit().putString(KEY, root.toString()).apply()
    }

    fun drain(context: Context): List<Map<String, Any>> {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val root = runCatching { JSONObject(prefs.getString(KEY, "{}") ?: "{}") }.getOrDefault(JSONObject())
        prefs.edit().remove(KEY).apply()
        return root.keys().asSequence().mapNotNull { key ->
            val item = root.optJSONObject(key) ?: return@mapNotNull null
            val data = item.optString("data").takeIf { it.isNotBlank() && it != "null" }
                ?.let { runCatching { Base64.decode(it, Base64.NO_WRAP) }.getOrNull() }
            buildMap<String, Any> {
                data?.let { put(SmartAttendanceScanContract.EXTRA_SERVICE_DATA, it) }
                item.optLong("hubId").takeIf { it > 0 }?.let { put(SmartAttendanceScanContract.EXTRA_HUB_ID, it) }
                put(SmartAttendanceScanContract.EXTRA_RSSI, item.optInt("rssi"))
                put(SmartAttendanceScanContract.EXTRA_DETECTED_AT, item.optLong("detectedAt"))
            }.takeIf { it.containsKey(SmartAttendanceScanContract.EXTRA_SERVICE_DATA) || it.containsKey(SmartAttendanceScanContract.EXTRA_HUB_ID) }
        }.toList()
    }
}
