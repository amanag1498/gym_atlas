package com.techybugs.gymatlas.member

import android.content.Context
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.os.Handler
import android.os.Looper
import android.os.SystemClock
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import kotlin.math.roundToInt

class MainActivity : FlutterFragmentActivity() {
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
        private const val STEP_SENSOR_PREFS = "step_sensor_fallback"
        private const val KEY_STEP_DATE = "step_date"
        private const val KEY_BOOT_EPOCH_MS = "boot_epoch_ms"
        private const val KEY_BASELINE_STEPS = "baseline_steps"
        private const val SENSOR_TIMEOUT_MS = 1500L
        private const val STEP_LENGTH_METERS = 0.78
        private const val CALORIES_PER_STEP = 0.04
    }
}
