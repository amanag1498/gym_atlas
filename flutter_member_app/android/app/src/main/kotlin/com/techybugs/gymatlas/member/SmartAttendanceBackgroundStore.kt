package com.techybugs.gymatlas.member

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import org.json.JSONObject
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

data class SmartAttendanceBackgroundConfig(
    val baseUrl: String,
    val accessToken: String,
    val gymId: Long,
)

class SmartAttendanceBackgroundConfigStore(context: Context) {
    private val prefs = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun save(config: SmartAttendanceBackgroundConfig) {
        val plain = JSONObject()
            .put("baseUrl", config.baseUrl.trimEnd('/'))
            .put("accessToken", config.accessToken)
            .put("gymId", config.gymId)
            .toString()
            .toByteArray(Charsets.UTF_8)
        val encrypted = encrypt(plain)
        prefs.edit().putString(KEY_PAYLOAD, encrypted.first).putString(KEY_IV, encrypted.second).apply()
    }

    fun load(): SmartAttendanceBackgroundConfig? {
        val payload = prefs.getString(KEY_PAYLOAD, null) ?: return null
        val iv = prefs.getString(KEY_IV, null) ?: return null
        return runCatching {
            val json = JSONObject(String(decrypt(payload, iv), Charsets.UTF_8))
            SmartAttendanceBackgroundConfig(
                baseUrl = json.getString("baseUrl"),
                accessToken = json.getString("accessToken"),
                gymId = json.getLong("gymId"),
            )
        }.getOrElse {
            clear()
            null
        }
    }

    fun clear() {
        prefs.edit().clear().apply()
    }

    private fun encrypt(bytes: ByteArray): Pair<String, String> {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, key())
        return Base64.encodeToString(cipher.doFinal(bytes), Base64.NO_WRAP) to
            Base64.encodeToString(cipher.iv, Base64.NO_WRAP)
    }

    private fun decrypt(payload: String, iv: String): ByteArray {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(
            Cipher.DECRYPT_MODE,
            key(),
            GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP)),
        )
        return cipher.doFinal(Base64.decode(payload, Base64.NO_WRAP))
    }

    private fun key(): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (keyStore.getEntry(KEY_ALIAS, null) as? KeyStore.SecretKeyEntry)?.secretKey?.let { return it }
        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore").run {
            init(
                KeyGenParameterSpec.Builder(
                    KEY_ALIAS,
                    KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
                )
                    .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                    .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                    .setRandomizedEncryptionRequired(true)
                    .build(),
            )
            generateKey()
        }
    }

    companion object {
        private const val PREFS = "smart_attendance_background_config"
        private const val KEY_PAYLOAD = "payload"
        private const val KEY_IV = "iv"
        private const val KEY_ALIAS = "gym_atlas_member_smart_attendance_v1"
        private const val TRANSFORMATION = "AES/GCM/NoPadding"
    }
}

data class SmartAttendanceNativeSession(
    val attendanceLogId: Long,
    val hubPublicId: String,
    val checkedInAtMs: Long,
    val lastPresenceAtMs: Long,
    val windowEndsAtMs: Long,
    val checkedOutAtMs: Long? = null,
)

class SmartAttendanceNativeSessionStore(context: Context) {
    private val prefs = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun save(session: SmartAttendanceNativeSession) {
        prefs.edit().putString(
            KEY_SESSION,
            JSONObject()
                .put("attendanceLogId", session.attendanceLogId)
                .put("hubPublicId", session.hubPublicId)
                .put("checkedInAtMs", session.checkedInAtMs)
                .put("lastPresenceAtMs", session.lastPresenceAtMs)
                .put("windowEndsAtMs", session.windowEndsAtMs)
                .put("checkedOutAtMs", session.checkedOutAtMs)
                .toString(),
        ).apply()
    }

    fun load(): SmartAttendanceNativeSession? {
        val raw = prefs.getString(KEY_SESSION, null) ?: return null
        return runCatching {
            val json = JSONObject(raw)
            SmartAttendanceNativeSession(
                attendanceLogId = json.getLong("attendanceLogId"),
                hubPublicId = json.getString("hubPublicId"),
                checkedInAtMs = json.getLong("checkedInAtMs"),
                lastPresenceAtMs = json.getLong("lastPresenceAtMs"),
                windowEndsAtMs = json.getLong("windowEndsAtMs"),
                checkedOutAtMs = if (json.isNull("checkedOutAtMs")) null else json.getLong("checkedOutAtMs"),
            )
        }.getOrElse {
            clear()
            null
        }
    }

    fun clear() {
        prefs.edit().clear().apply()
    }

    companion object {
        private const val PREFS = "smart_attendance_native_session"
        private const val KEY_SESSION = "session"
    }
}
