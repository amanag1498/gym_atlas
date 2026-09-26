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

class SmartAttendanceScanService : Service() {
    private val serviceUuid = ParcelUuid(UUID.fromString(SmartAttendanceScanContract.ATLAS_SERVICE_UUID))
    private var scanCallback: ScanCallback? = null

    override fun onCreate() {
        super.onCreate()
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
            listOf(ScanFilter.Builder().setServiceUuid(serviceUuid).build()),
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
        val data = result.scanRecord?.getServiceData(serviceUuid) ?: return
        val detectedAt = System.currentTimeMillis()
        SmartAttendanceDetectionQueue.save(this, data, result.rssi, detectedAt)
        sendBroadcast(
            Intent(SmartAttendanceScanContract.ACTION_DETECTION)
                .setPackage(packageName)
                .putExtra(SmartAttendanceScanContract.EXTRA_SERVICE_DATA, data)
                .putExtra(SmartAttendanceScanContract.EXTRA_RSSI, result.rssi)
                .putExtra(SmartAttendanceScanContract.EXTRA_DETECTED_AT, detectedAt),
        )
    }

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
        .setSmallIcon(android.R.drawable.stat_sys_data_bluetooth)
        .setOngoing(true)
        .build()

    companion object {
        const val ACTION_STOP = "com.techybugs.gymatlas.member.STOP_SMART_ATTENDANCE_SCAN"
        private const val CHANNEL_ID = "gym_atlas_smart_attendance"
        private const val NOTIFICATION_ID = 4201
        private const val BACKGROUND_REPORT_DELAY_MS = 15_000L
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
    const val ACTION_DETECTION = "com.techybugs.gymatlas.member.SMART_ATTENDANCE_DETECTION"
    const val EXTRA_SERVICE_DATA = "serviceData"
    const val EXTRA_RSSI = "rssi"
    const val EXTRA_DETECTED_AT = "detectedAt"
}

object SmartAttendanceDetectionQueue {
    private const val PREFS = "smart_attendance_background_detections"
    private const val KEY = "latest_by_hub"

    fun save(context: Context, data: ByteArray, rssi: Int, detectedAt: Long) {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val root = runCatching { JSONObject(prefs.getString(KEY, "{}") ?: "{}") }.getOrDefault(JSONObject())
        val encoded = Base64.encodeToString(data, Base64.NO_WRAP)
        root.put(encoded, JSONObject().put("data", encoded).put("rssi", rssi).put("detectedAt", detectedAt))
        prefs.edit().putString(KEY, root.toString()).apply()
    }

    fun drain(context: Context): List<Map<String, Any>> {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val root = runCatching { JSONObject(prefs.getString(KEY, "{}") ?: "{}") }.getOrDefault(JSONObject())
        prefs.edit().remove(KEY).apply()
        return root.keys().asSequence().mapNotNull { key ->
            val item = root.optJSONObject(key) ?: return@mapNotNull null
            val data = runCatching { Base64.decode(item.getString("data"), Base64.NO_WRAP) }.getOrNull()
                ?: return@mapNotNull null
            mapOf(
                SmartAttendanceScanContract.EXTRA_SERVICE_DATA to data,
                SmartAttendanceScanContract.EXTRA_RSSI to item.optInt("rssi"),
                SmartAttendanceScanContract.EXTRA_DETECTED_AT to item.optLong("detectedAt"),
            )
        }.toList()
    }
}
