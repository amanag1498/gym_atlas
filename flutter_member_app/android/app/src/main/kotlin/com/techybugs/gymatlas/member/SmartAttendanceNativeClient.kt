package com.techybugs.gymatlas.member

import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.time.Instant

class SmartAttendanceNativeClient {
    fun checkIn(
        config: SmartAttendanceBackgroundConfig,
        detection: SmartAttendanceNativePayload,
        rssi: Int,
        detectedAtMs: Long,
    ): SmartAttendanceNativeSession {
        val response = post(config, "/member/attendance/smart-check-in") {
            detection.publicId?.let { put("hub_public_id", it) }
            detection.hubId?.let { put("hub_id", it) }
            put("protocol_version", detection.protocolVersion)
            put("rssi", rssi)
            put("detected_at", Instant.ofEpochMilli(detectedAtMs).toString())
            put("source", "android_background_native")
            put("metadata", JSONObject().put("transport", "android_foreground_service"))
        }
        val attendance = response.getJSONObject("data").getJSONObject("attendance")
        val checkedInAtMs = instantMillis(attendance.optString("checked_in_at")) ?: detectedAtMs
        return SmartAttendanceNativeSession(
            attendanceLogId = attendance.getLong("id"),
            hubPublicId = detection.key,
            checkedInAtMs = checkedInAtMs,
            lastPresenceAtMs = instantMillis(attendance.optString("last_presence_at")) ?: detectedAtMs,
            windowEndsAtMs = instantMillis(attendance.optString("attendance_window_ends_at"))
                ?: checkedInAtMs + ATTENDANCE_WINDOW_MS,
            checkedOutAtMs = instantMillis(attendance.optString("checked_out_at")),
        )
    }

    fun checkOut(
        config: SmartAttendanceBackgroundConfig,
        session: SmartAttendanceNativeSession,
    ) {
        post(config, "/member/attendance/smart-check-out") {
            put("attendance_log_id", session.attendanceLogId)
            put("last_presence_at", Instant.ofEpochMilli(session.lastPresenceAtMs).toString())
        }
    }

    private fun post(
        config: SmartAttendanceBackgroundConfig,
        path: String,
        bodyBuilder: JSONObject.() -> Unit,
    ): JSONObject {
        val connection = (URL(config.baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = 12_000
            readTimeout = 12_000
            doOutput = true
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Content-Type", "application/json")
            setRequestProperty("Authorization", "Bearer ${config.accessToken}")
            setRequestProperty("X-Gym-Id", config.gymId.toString())
            setRequestProperty("X-Atlas-App", "member")
            setRequestProperty("X-Client-Platform", "android")
        }
        try {
            OutputStreamWriter(connection.outputStream, Charsets.UTF_8).use {
                it.write(JSONObject().apply(bodyBuilder).toString())
            }
            val status = connection.responseCode
            val stream = if (status in 200..299) connection.inputStream else connection.errorStream
            val text = stream?.let { BufferedReader(InputStreamReader(it)).use(BufferedReader::readText) }.orEmpty()
            if (status !in 200..299) {
                val message = runCatching { JSONObject(text).optString("message") }.getOrNull()
                throw IllegalStateException(message?.takeIf { it.isNotBlank() } ?: "HTTP $status")
            }
            return JSONObject(text)
        } finally {
            connection.disconnect()
        }
    }

    private fun instantMillis(value: String?): Long? =
        value?.takeIf { it.isNotBlank() && it != "null" }?.let { runCatching { Instant.parse(it).toEpochMilli() }.getOrNull() }

    companion object {
        private const val ATTENDANCE_WINDOW_MS = 6 * 60 * 60 * 1000L
    }
}
