package com.techybugs.gymatlas.member.workout

import android.content.Context
import android.content.Intent
import org.json.JSONArray
import org.json.JSONObject

internal object WorkoutActionStore {
    const val ACTION_EVENT_AVAILABLE =
        "com.techybugs.gymatlas.member.workout.ACTION_EVENT_AVAILABLE"

    private const val PREFS_NAME = "workout_lock_screen_actions"
    private const val KEY_PENDING_ACTIONS = "pending_actions"
    private const val MAX_PENDING_ACTIONS = 50

    @Synchronized
    fun enqueue(context: Context, action: Map<String, Any?>) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val pending = runCatching {
            JSONArray(prefs.getString(KEY_PENDING_ACTIONS, "[]"))
        }.getOrDefault(JSONArray())

        while (pending.length() >= MAX_PENDING_ACTIONS) {
            pending.remove(0)
        }
        pending.put(JSONObject(action))
        prefs.edit().putString(KEY_PENDING_ACTIONS, pending.toString()).commit()

        context.sendBroadcast(
            Intent(ACTION_EVENT_AVAILABLE).setPackage(context.packageName),
        )
    }

    @Synchronized
    fun drain(context: Context): List<Map<String, Any?>> {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val pending = runCatching {
            JSONArray(prefs.getString(KEY_PENDING_ACTIONS, "[]"))
        }.getOrDefault(JSONArray())
        prefs.edit().remove(KEY_PENDING_ACTIONS).commit()

        return buildList {
            for (index in 0 until pending.length()) {
                val item = pending.optJSONObject(index) ?: continue
                add(
                    buildMap {
                        item.keys().forEach { key ->
                            val value = item.opt(key)
                            put(key, if (value == JSONObject.NULL) null else value)
                        }
                    },
                )
            }
        }
    }
}
