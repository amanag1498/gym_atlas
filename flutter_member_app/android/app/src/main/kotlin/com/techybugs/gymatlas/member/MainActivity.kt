package com.techybugs.gymatlas.member

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.bluetooth.le.ScanCallback
import android.bluetooth.le.ScanFilter
import android.bluetooth.le.ScanResult
import android.bluetooth.le.ScanSettings
import android.content.Context
import android.content.pm.PackageManager
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.ParcelUuid
import android.os.SystemClock
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodChannel
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.util.UUID
import kotlin.math.roundToInt

class MainActivity : FlutterFragmentActivity() {
    private var smartAttendanceScanner: SmartAttendanceBleScanner? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            STEP_SENSOR_CHANNEL,
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "readTodaySensorSteps" -> readTodaySensorSteps(result)
                else -> result.notImplemented()
            }
        }

        val scanner = SmartAttendanceBleScanner(this)
        smartAttendanceScanner = scanner
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            SMART_ATTENDANCE_BLE_CHANNEL,
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "startForegroundScan" -> scanner.start(result, background = false)
                "startBackgroundScan" -> scanner.start(result, background = true)
                "stopScan" -> scanner.stop(result)
                "androidSdkInt" -> result.success(Build.VERSION.SDK_INT)
                else -> result.notImplemented()
            }
        }
        EventChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            SMART_ATTENDANCE_BLE_EVENTS_CHANNEL,
        ).setStreamHandler(scanner)
    }

    override fun onDestroy() {
        smartAttendanceScanner?.stop(null)
        super.onDestroy()
    }

    private fun readTodaySensorSteps(result: MethodChannel.Result) {
        val sensorManager = getSystemService(Context.SENSOR_SERVICE) as? SensorManager
        val stepCounter = sensorManager?.getDefaultSensor(Sensor.TYPE_STEP_COUNTER)

        if (sensorManager == null || stepCounter == null) {
            result.success(
                mapOf(
                    "available" to false,
                    "steps" to 0,
                    "distanceMeters" to 0,
                    "caloriesEstimated" to 0,
                ),
            )
            return
        }

        val handler = Handler(Looper.getMainLooper())
        var completed = false
        var listener: SensorEventListener? = null

        fun finish(payload: Map<String, Any>) {
            if (completed) {
                return
            }

            completed = true
            listener?.let(sensorManager::unregisterListener)
            handler.removeCallbacksAndMessages(null)
            result.success(payload)
        }

        val prefs = getSharedPreferences(STEP_SENSOR_PREFS, Context.MODE_PRIVATE)
        listener = object : SensorEventListener {
            override fun onSensorChanged(event: SensorEvent) {
                val cumulativeSteps = event.values.firstOrNull()?.roundToInt() ?: 0
                val todaySteps = resolveTodaySteps(cumulativeSteps, prefs)
                val distanceMeters = estimateDistanceMeters(todaySteps)
                val caloriesEstimated = estimateCalories(todaySteps)

                finish(
                    mapOf(
                        "available" to true,
                        "steps" to todaySteps,
                        "distanceMeters" to distanceMeters,
                        "caloriesEstimated" to caloriesEstimated,
                    ),
                )
            }

            override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) = Unit
        }

        sensorManager.registerListener(listener, stepCounter, SensorManager.SENSOR_DELAY_NORMAL)
        handler.postDelayed(
            {
                finish(
                    mapOf(
                        "available" to false,
                        "steps" to 0,
                        "distanceMeters" to 0,
                        "caloriesEstimated" to 0,
                    ),
                )
            },
            SENSOR_TIMEOUT_MS,
        )
    }

    private fun resolveTodaySteps(cumulativeSteps: Int, prefs: android.content.SharedPreferences): Int {
        val today = LocalDate.now(ZoneId.systemDefault()).toString()
        val currentBootEpochMs = System.currentTimeMillis() - SystemClock.elapsedRealtime()
        val bootDate = Instant.ofEpochMilli(currentBootEpochMs)
            .atZone(ZoneId.systemDefault())
            .toLocalDate()
            .toString()

        if (bootDate == today) {
            prefs.edit()
                .putString(KEY_STEP_DATE, today)
                .putLong(KEY_BOOT_EPOCH_MS, currentBootEpochMs)
                .putInt(KEY_BASELINE_STEPS, 0)
                .apply()
            return cumulativeSteps.coerceAtLeast(0)
        }

        val storedDate = prefs.getString(KEY_STEP_DATE, null)
        val storedBootEpochMs = prefs.getLong(KEY_BOOT_EPOCH_MS, Long.MIN_VALUE)
        val storedBaseline = prefs.getInt(KEY_BASELINE_STEPS, cumulativeSteps)

        if (storedDate == today && storedBootEpochMs == currentBootEpochMs) {
            return (cumulativeSteps - storedBaseline).coerceAtLeast(0)
        }

        prefs.edit()
            .putString(KEY_STEP_DATE, today)
            .putLong(KEY_BOOT_EPOCH_MS, currentBootEpochMs)
            .putInt(KEY_BASELINE_STEPS, cumulativeSteps)
            .apply()

        return 0
    }

    private fun estimateDistanceMeters(steps: Int): Int {
        return (steps * STEP_LENGTH_METERS).roundToInt()
    }

    private fun estimateCalories(steps: Int): Int {
        return (steps * CALORIES_PER_STEP).roundToInt()
    }

    companion object {
        private const val STEP_SENSOR_CHANNEL = "com.techybugs.gymatlas.member/step_sensor"
        private const val SMART_ATTENDANCE_BLE_CHANNEL = "com.techybugs.gymatlas.member/smart_attendance_ble"
        private const val SMART_ATTENDANCE_BLE_EVENTS_CHANNEL = "com.techybugs.gymatlas.member/smart_attendance_ble_events"
        private const val STEP_SENSOR_PREFS = "step_sensor_fallback"
        private const val KEY_STEP_DATE = "step_date"
        private const val KEY_BOOT_EPOCH_MS = "boot_epoch_ms"
        private const val KEY_BASELINE_STEPS = "baseline_steps"
        private const val SENSOR_TIMEOUT_MS = 1500L
        private const val STEP_LENGTH_METERS = 0.78
        private const val CALORIES_PER_STEP = 0.04
    }
}

private class SmartAttendanceBleScanner(private val context: Context) : EventChannel.StreamHandler {
    private val bluetoothManager = context.getSystemService(BluetoothManager::class.java)
    private val bluetoothAdapter = bluetoothManager?.adapter
    private var eventSink: EventChannel.EventSink? = null
    private var scanCallback: ScanCallback? = null
    private var currentBackgroundMode = false
    private val serviceUuid = ParcelUuid(UUID.fromString(ATLAS_SERVICE_UUID))

    override fun onListen(arguments: Any?, events: EventChannel.EventSink?) {
        eventSink = events
    }

    override fun onCancel(arguments: Any?) {
        eventSink = null
    }

    @SuppressLint("MissingPermission")
    fun start(result: MethodChannel.Result, background: Boolean) {
        if (!hasScanPermission()) {
            result.error("permission_denied", "Bluetooth scan permission is required.", null)
            return
        }
        val adapter = bluetoothAdapter
        if (adapter == null) {
            result.error("bluetooth_unavailable", "Bluetooth is not available on this device.", null)
            return
        }
        // Android 12+ protects isEnabled with BLUETOOTH_CONNECT, which Atlas
        // does not otherwise need. The scanner-null/failure paths below report
        // Bluetooth-off without asking members for that broader permission.
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S && !adapter.isEnabled) {
            result.error("bluetooth_off", "Bluetooth is turned off.", null)
            return
        }
        val scanner = adapter.bluetoothLeScanner
        if (scanner == null) {
            result.error("scanner_unavailable", "BLE scanner is unavailable.", null)
            return
        }

        stop(null)
        val callback = object : ScanCallback() {
            override fun onScanResult(callbackType: Int, scanResult: ScanResult) {
                emit(scanResult)
            }

            override fun onBatchScanResults(results: MutableList<ScanResult>) {
                results.forEach(::emit)
            }

            override fun onScanFailed(errorCode: Int) {
                eventSink?.success(mapOf("diagnostic" to "BLE scan failed with code $errorCode.", "detectedAt" to System.currentTimeMillis(), "source" to if (background) "android_background_ble" else "android_foreground_ble"))
            }
        }
        scanCallback = callback
        currentBackgroundMode = background
        scanner.startScan(
            listOf(ScanFilter.Builder().setServiceUuid(serviceUuid).build()),
            ScanSettings.Builder()
                .setScanMode(if (background) ScanSettings.SCAN_MODE_LOW_POWER else ScanSettings.SCAN_MODE_LOW_LATENCY)
                .setCallbackType(ScanSettings.CALLBACK_TYPE_ALL_MATCHES)
                .setReportDelay(if (background) BACKGROUND_REPORT_DELAY_MS else 0L)
                .build(),
            callback,
        )
        result.success(null)
    }

    @SuppressLint("MissingPermission")
    fun stop(result: MethodChannel.Result?) {
        val callback = scanCallback
        if (callback != null && hasScanPermission()) {
            bluetoothAdapter?.bluetoothLeScanner?.stopScan(callback)
        }
        scanCallback = null
        currentBackgroundMode = false
        result?.success(null)
    }

    private fun emit(result: ScanResult) {
        val serviceData = result.scanRecord?.getServiceData(serviceUuid)
        eventSink?.success(
            mapOf(
                "serviceUuid" to ATLAS_SERVICE_UUID,
                "serviceData" to serviceData,
                "rssi" to result.rssi,
                "detectedAt" to System.currentTimeMillis(),
                "source" to if (currentBackgroundMode) "android_background_ble" else "android_foreground_ble",
            ),
        )
    }

    private fun hasScanPermission(): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            context.checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN) == PackageManager.PERMISSION_GRANTED
        } else {
            context.checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
                context.checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        }
    }

    companion object {
        private const val ATLAS_SERVICE_UUID = "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1"
        private const val BACKGROUND_REPORT_DELAY_MS = 15000L
    }
}
