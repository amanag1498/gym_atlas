package com.techybugs.gymatlas.member.workout

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.os.IBinder
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.techybugs.gymatlas.member.MainActivity
import com.techybugs.gymatlas.member.R
import org.json.JSONObject

class WorkoutForegroundService : Service() {
    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_START -> startWorkout(WorkoutState.fromIntent(intent))
            ACTION_UPDATE -> updateWorkout(WorkoutState.fromIntent(intent))
            ACTION_COMPLETE_SET -> handleUserAction("complete_set", intent)
            ACTION_REST -> handleRestAction(intent)
            ACTION_NEXT -> handleUserAction("next_exercise", intent)
            // Keep the foreground surface alive until Flutter confirms that the
            // backend completed the session and calls the explicit `end` method.
            // Otherwise a transient API failure would silently discard the user's
            // only route back into the active workout.
            ACTION_END -> handleUserAction("end", intent)
            ACTION_STOP -> stopWorkout(intent.getStringExtra(EXTRA_SESSION_ID))
            null -> restoreWorkout()
        }
        return if (WorkoutState.load(this) == null) START_NOT_STICKY else START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun startWorkout(state: WorkoutState?) {
        if (state == null) {
            stopSelf()
            return
        }
        state.save(this)
        startForeground(NOTIFICATION_ID, buildNotification(state))
    }

    private fun updateWorkout(incoming: WorkoutState?) {
        val current = WorkoutState.load(this)
        if (incoming == null || current == null) {
            stopSelf()
            return
        }
        if (incoming.sessionId != current.sessionId) return
        val updated = current.merge(incoming)
        updated.save(this)
        NotificationManagerCompat.from(this).notify(NOTIFICATION_ID, buildNotification(updated))
    }

    private fun restoreWorkout() {
        val state = WorkoutState.load(this)
        if (state == null) {
            stopSelf()
            return
        }
        startForeground(NOTIFICATION_ID, buildNotification(state))
    }

    private fun handleUserAction(action: String, intent: Intent) {
        val state = WorkoutState.load(this) ?: return
        val requestedSessionId = intent.getStringExtra(EXTRA_SESSION_ID)
        if (requestedSessionId != state.sessionId) return

        WorkoutActionStore.enqueue(
            this,
            buildMap {
                put("action", action)
                put("sessionId", state.sessionId)
                state.exerciseId?.let { put("exerciseId", it) }
                state.setNumber?.let { put("setNumber", it) }
                if (action == "complete_set") {
                    state.reps?.let { put("reps", it) }
                    state.weight?.let { put("weight", it) }
                }
            },
        )
    }

    private fun handleRestAction(intent: Intent) {
        val state = WorkoutState.load(this) ?: return
        val activeRestEnd = state.restEndsAtEpochMs?.takeIf { it > System.currentTimeMillis() }
        handleUserAction(
            if (activeRestEnd == null) "start_rest" else "skip_rest",
            intent,
        )
        val updated = state.copy(
            restEndsAtEpochMs = if (activeRestEnd == null) {
                System.currentTimeMillis() + ((state.restSeconds ?: 60) * 1_000L)
            } else {
                null
            },
        )
        updated.save(this)
        NotificationManagerCompat.from(this).notify(NOTIFICATION_ID, buildNotification(updated))
    }

    private fun stopWorkout(requestedSessionId: String? = null) {
        val current = WorkoutState.load(this)
        if (requestedSessionId != null && current?.sessionId != requestedSessionId) return
        WorkoutState.clear(this)
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    private fun buildNotification(state: WorkoutState): Notification {
        val activeRestEnd = state.restEndsAtEpochMs?.takeIf { it > System.currentTimeMillis() }
        val openIntent = Intent(this, MainActivity::class.java).apply {
            action = ACTION_OPEN
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra(EXTRA_SESSION_ID, state.sessionId)
            state.exerciseId?.let { putExtra(EXTRA_EXERCISE_ID, it) }
            state.setNumber?.let { putExtra(EXTRA_SET_NUMBER, it) }
        }
        val contentIntent = PendingIntent.getActivity(
            this,
            requestCode(state.sessionId, ACTION_OPEN),
            openIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val builder = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_workout)
            .setColor(Color.rgb(27, 105, 255))
            .setContentTitle(state.workoutName)
            .setContentText(state.description)
            .setSubText("Workout in progress")
            .setContentIntent(contentIntent)
            .setOngoing(true)
            .setAutoCancel(false)
            .setOnlyAlertOnce(true)
            .setSilent(true)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setShowWhen(true)
            .setWhen(activeRestEnd ?: state.startedAtEpochMs)
            .setUsesChronometer(true)
            .setChronometerCountDown(activeRestEnd != null)
            .addAction(action(state, ACTION_COMPLETE_SET, "Complete set"))
            .addAction(action(state, ACTION_REST, if (activeRestEnd == null) "Rest" else "Skip rest"))
            .addAction(action(state, ACTION_NEXT, "Next"))

        return builder.build()
    }

    private fun action(state: WorkoutState, action: String, title: String): NotificationCompat.Action {
        val intent = Intent(this, WorkoutForegroundService::class.java).apply {
            this.action = action
            putExtra(EXTRA_SESSION_ID, state.sessionId)
        }
        val pendingIntent = PendingIntent.getService(
            this,
            requestCode(state.sessionId, action),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        return NotificationCompat.Action.Builder(0, title, pendingIntent).build()
    }

    private fun createNotificationChannel() {
        val manager = getSystemService(NotificationManager::class.java) ?: return
        val channel = NotificationChannel(
            CHANNEL_ID,
            "Active workouts",
            NotificationManager.IMPORTANCE_LOW,
        ).apply {
            description = "Lock-screen controls for an active workout"
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setShowBadge(false)
            enableVibration(false)
            setSound(null, null)
        }
        manager.createNotificationChannel(channel)
    }

    private fun requestCode(sessionId: String, action: String): Int =
        (31 * sessionId.hashCode() + action.hashCode()) and Int.MAX_VALUE

    companion object {
        const val ACTION_START = "com.techybugs.gymatlas.member.workout.START"
        const val ACTION_UPDATE = "com.techybugs.gymatlas.member.workout.UPDATE"
        const val ACTION_STOP = "com.techybugs.gymatlas.member.workout.STOP"
        const val ACTION_COMPLETE_SET = "com.techybugs.gymatlas.member.workout.COMPLETE_SET"
        const val ACTION_REST = "com.techybugs.gymatlas.member.workout.REST"
        const val ACTION_NEXT = "com.techybugs.gymatlas.member.workout.NEXT"
        const val ACTION_END = "com.techybugs.gymatlas.member.workout.END"
        const val ACTION_OPEN = "com.techybugs.gymatlas.member.workout.OPEN"

        const val EXTRA_SESSION_ID = "sessionId"
        const val EXTRA_EXERCISE_ID = "exerciseId"
        const val EXTRA_SET_NUMBER = "setNumber"

        private const val CHANNEL_ID = "active_workout"
        private const val NOTIFICATION_ID = 4107

        fun intentFor(context: Context, action: String, arguments: Map<*, *>?): Intent =
            Intent(context, WorkoutForegroundService::class.java).apply {
                this.action = action
                arguments?.forEach { (key, value) ->
                    val name = key as? String ?: return@forEach
                    when (value) {
                        is String -> putExtra(name, value)
                        is Int -> putExtra(name, value)
                        is Long -> putExtra(name, value)
                        is Double -> putExtra(name, value)
                        is Boolean -> putExtra(name, value)
                        is Number -> putExtra(name, value.toLong())
                    }
                }
            }
    }
}

private data class WorkoutState(
    val sessionId: String,
    val workoutName: String,
    val exerciseName: String?,
    val exerciseId: String?,
    val setNumber: Int?,
    val totalSets: Int?,
    val reps: Int?,
    val weight: Double?,
    val restSeconds: Int?,
    val startedAtEpochMs: Long,
    val restEndsAtEpochMs: Long?,
) {
    val description: String
        get() = buildList {
            exerciseName?.takeIf { it.isNotBlank() }?.let(::add)
            if (setNumber != null) {
                add(if (totalSets == null) "Set $setNumber" else "Set $setNumber of $totalSets")
            }
            if (reps != null || weight != null) {
                add(
                    listOfNotNull(
                        reps?.let { "$it reps" },
                        weight?.let { "${formatWeight(it)} kg" },
                    ).joinToString(" • "),
                )
            }
        }.joinToString("  ·  ").ifBlank { "Tap to return to your workout" }

    fun merge(incoming: WorkoutState): WorkoutState = copy(
        workoutName = incoming.workoutName.ifBlank { workoutName },
        exerciseName = incoming.exerciseName ?: exerciseName,
        exerciseId = incoming.exerciseId ?: exerciseId,
        setNumber = incoming.setNumber ?: setNumber,
        totalSets = incoming.totalSets ?: totalSets,
        reps = incoming.reps ?: reps,
        weight = incoming.weight ?: weight,
        restSeconds = incoming.restSeconds ?: restSeconds,
        startedAtEpochMs = incoming.startedAtEpochMs,
        restEndsAtEpochMs = incoming.restEndsAtEpochMs,
    )

    fun save(context: Context) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_STATE, toJson().toString())
            .apply()
    }

    private fun toJson(): JSONObject = JSONObject().apply {
        put("sessionId", sessionId)
        put("workoutName", workoutName)
        put("exerciseName", exerciseName)
        put("exerciseId", exerciseId)
        put("setNumber", setNumber)
        put("totalSets", totalSets)
        put("reps", reps)
        put("weight", weight)
        put("restSeconds", restSeconds)
        put("startedAtEpochMs", startedAtEpochMs)
        put("restEndsAtEpochMs", restEndsAtEpochMs)
    }

    companion object {
        private const val PREFS_NAME = "workout_lock_screen_state"
        private const val KEY_STATE = "active_state"

        fun fromIntent(intent: Intent): WorkoutState? {
            val sessionId = intent.getStringExtra("sessionId")?.takeIf { it.isNotBlank() }
                ?: return null
            return WorkoutState(
                sessionId = sessionId,
                workoutName = intent.getStringExtra("workoutName") ?: "Active workout",
                exerciseName = intent.getStringExtra("exerciseName"),
                exerciseId = intent.stringOrNumberExtra("exerciseId"),
                setNumber = intent.numberExtra("setNumber")?.toInt(),
                totalSets = intent.numberExtra("totalSets")?.toInt(),
                reps = intent.numberExtra("reps")?.toInt(),
                weight = intent.numberExtra("weight")?.toDouble(),
                restSeconds = intent.numberExtra("restSeconds")?.toInt(),
                startedAtEpochMs = intent.numberExtra("startedAtEpochMillis")?.toLong()
                    ?: intent.numberExtra("startedAtEpochMs")?.toLong()
                    ?: System.currentTimeMillis(),
                restEndsAtEpochMs = intent.numberExtra("restEndsAtEpochMillis")?.toLong()
                    ?: intent.numberExtra("restEndsAtEpochMs")?.toLong(),
            )
        }

        fun load(context: Context): WorkoutState? {
            val raw = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                .getString(KEY_STATE, null) ?: return null
            return runCatching {
                val json = JSONObject(raw)
                WorkoutState(
                    sessionId = json.getString("sessionId"),
                    workoutName = json.optString("workoutName", "Active workout"),
                    exerciseName = json.nullableString("exerciseName"),
                    exerciseId = json.nullableString("exerciseId"),
                    setNumber = json.nullableInt("setNumber"),
                    totalSets = json.nullableInt("totalSets"),
                    reps = json.nullableInt("reps"),
                    weight = json.nullableDouble("weight"),
                    restSeconds = json.nullableInt("restSeconds"),
                    startedAtEpochMs = json.optLong("startedAtEpochMs", System.currentTimeMillis()),
                    restEndsAtEpochMs = json.nullableLong("restEndsAtEpochMs"),
                )
            }.getOrNull()
        }

        fun clear(context: Context) {
            context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                .edit()
                .remove(KEY_STATE)
                .apply()
        }

        private fun formatWeight(value: Double): String =
            if (value % 1.0 == 0.0) value.toInt().toString() else value.toString()
    }
}

@Suppress("DEPRECATION")
private fun Intent.numberExtra(name: String): Number? = extras?.get(name) as? Number
@Suppress("DEPRECATION")
private fun Intent.stringOrNumberExtra(name: String): String? = when (val value = extras?.get(name)) {
    is String -> value
    is Number -> value.toLong().toString()
    else -> null
}
private fun JSONObject.nullableString(name: String): String? =
    if (isNull(name)) null else getString(name)
private fun JSONObject.nullableInt(name: String): Int? =
    if (isNull(name)) null else optInt(name)
private fun JSONObject.nullableLong(name: String): Long? =
    if (isNull(name)) null else optLong(name)
private fun JSONObject.nullableDouble(name: String): Double? =
    if (isNull(name)) null else optDouble(name)
