package com.techybugs.gymatlas.member.workout

import android.Manifest
import android.app.Activity
import android.app.NotificationManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

class WorkoutLockScreenBridge(
    private val activity: Activity,
    messenger: BinaryMessenger,
) : MethodChannel.MethodCallHandler, EventChannel.StreamHandler {
    private val methodChannel = MethodChannel(messenger, METHOD_CHANNEL)
    private val eventChannel = EventChannel(messenger, EVENT_CHANNEL)
    private var eventSink: EventChannel.EventSink? = null
    private var receiverRegistered = false

    private val actionReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            flushPendingActions()
        }
    }

    fun register() {
        methodChannel.setMethodCallHandler(this)
        eventChannel.setStreamHandler(this)
        val filter = IntentFilter(WorkoutActionStore.ACTION_EVENT_AVAILABLE)
        ContextCompat.registerReceiver(
            activity,
            actionReceiver,
            filter,
            ContextCompat.RECEIVER_NOT_EXPORTED,
        )
        receiverRegistered = true
    }

    fun unregister() {
        methodChannel.setMethodCallHandler(null)
        eventChannel.setStreamHandler(null)
        eventSink = null
        if (receiverRegistered) {
            activity.unregisterReceiver(actionReceiver)
            receiverRegistered = false
        }
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "isSupported" -> result.success(Build.VERSION.SDK_INT >= Build.VERSION_CODES.O)
            "start" -> start(call, result)
            "update" -> update(call, result)
            "end" -> end(call, result)
            else -> result.notImplemented()
        }
    }

    private fun start(call: MethodCall, result: MethodChannel.Result) {
        val arguments = call.arguments as? Map<*, *>
        val sessionId = arguments?.get("sessionId")?.toString()?.takeIf { it.isNotBlank() }
        if (sessionId == null) {
            result.error("invalid_arguments", "sessionId is required", null)
            return
        }
        if (!notificationsEnabled()) {
            result.error(
                "notifications_disabled",
                "Workout lock-screen notifications are disabled",
                null,
            )
            return
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q &&
            ContextCompat.checkSelfPermission(activity, Manifest.permission.ACTIVITY_RECOGNITION) !=
            PackageManager.PERMISSION_GRANTED
        ) {
            result.error(
                "health_permission_required",
                "Activity recognition permission is required for the workout foreground service",
                null,
            )
            return
        }

        runCatching {
            ContextCompat.startForegroundService(
                activity,
                WorkoutForegroundService.intentFor(
                    activity,
                    WorkoutForegroundService.ACTION_START,
                    arguments,
                ),
            )
        }.onSuccess {
            result.success(true)
        }.onFailure { error ->
            result.error("start_failed", error.message, null)
        }
    }

    private fun update(call: MethodCall, result: MethodChannel.Result) {
        val arguments = call.arguments as? Map<*, *>
        val sessionId = arguments?.get("sessionId")?.toString()?.takeIf { it.isNotBlank() }
        if (sessionId == null) {
            result.error("invalid_arguments", "sessionId is required", null)
            return
        }

        runCatching {
            activity.startService(
                WorkoutForegroundService.intentFor(
                    activity,
                    WorkoutForegroundService.ACTION_UPDATE,
                    arguments,
                ),
            )
        }.onSuccess {
            result.success(true)
        }.onFailure { error ->
            result.error("update_failed", error.message, null)
        }
    }

    private fun end(call: MethodCall, result: MethodChannel.Result) {
        val sessionId = (call.arguments as? Map<*, *>)
            ?.get("sessionId")
            ?.toString()
            ?.takeIf { it.isNotBlank() }

        runCatching {
            activity.startService(
                Intent(activity, WorkoutForegroundService::class.java).apply {
                    action = WorkoutForegroundService.ACTION_STOP
                    sessionId?.let { putExtra(WorkoutForegroundService.EXTRA_SESSION_ID, it) }
                },
            )
        }.onSuccess {
            result.success(true)
        }.onFailure { error ->
            result.error("end_failed", error.message, null)
        }
    }

    fun handleLaunchIntent(intent: Intent?) {
        if (intent?.action != WorkoutForegroundService.ACTION_OPEN) return
        val sessionId = intent.getStringExtra(WorkoutForegroundService.EXTRA_SESSION_ID) ?: return
        WorkoutActionStore.enqueue(
            activity,
            mapOf(
                "action" to "open",
                "sessionId" to sessionId,
                "exerciseId" to intent.getStringExtra(WorkoutForegroundService.EXTRA_EXERCISE_ID),
                "setNumber" to intent.intExtraOrNull(WorkoutForegroundService.EXTRA_SET_NUMBER),
            ),
        )
        intent.action = null
    }

    override fun onListen(arguments: Any?, events: EventChannel.EventSink?) {
        eventSink = events
        flushPendingActions()
    }

    override fun onCancel(arguments: Any?) {
        eventSink = null
    }

    private fun flushPendingActions() {
        val sink = eventSink ?: return
        WorkoutActionStore.drain(activity).forEach(sink::success)
    }

    private fun notificationsEnabled(): Boolean {
        val manager = ContextCompat.getSystemService(activity, NotificationManager::class.java)
            ?: return false
        if (!manager.areNotificationsEnabled()) {
            return false
        }
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(activity, Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED
    }

    private fun Intent.intExtraOrNull(name: String): Int? =
        if (hasExtra(name)) getIntExtra(name, 0) else null

    companion object {
        const val METHOD_CHANNEL =
            "com.techybugs.gymatlas.member/workout_lock_screen"
        const val EVENT_CHANNEL =
            "com.techybugs.gymatlas.member/workout_lock_screen_actions"
    }
}
