package com.techybugs.gymatlas.smarthub

import android.content.Context
import android.content.SharedPreferences
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import org.json.JSONObject
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class SecureCredentialStore(context: Context) {
    private val appContext = context.applicationContext
    private val prefs: SharedPreferences = appContext.getSharedPreferences("atlas_smart_hub_secure", Context.MODE_PRIVATE)

    fun save(credentials: HubCredentials) {
        val json = JSONObject()
            .put("baseUrl", credentials.baseUrl.trimEnd('/'))
            .put("hubUuid", credentials.hubUuid.trim())
            .put("deviceSecret", credentials.deviceSecret.trim())
            .put("publicId", credentials.publicId)
            .put("gymName", credentials.gymName)
            .put("branchName", credentials.branchName)
            .toString()
        val encrypted = encrypt(json.toByteArray(Charsets.UTF_8))
        prefs.edit()
            .putString("payload", encrypted.payload)
            .putString("iv", encrypted.iv)
            .apply()
    }

    fun load(): HubCredentials? {
        val payload = prefs.getString("payload", null) ?: return null
        val iv = prefs.getString("iv", null) ?: return null
        return runCatching {
            val json = JSONObject(String(decrypt(EncryptedPayload(payload, iv)), Charsets.UTF_8))
            HubCredentials(
                baseUrl = json.optString("baseUrl", HubContracts.DEFAULT_BASE_URL).ifBlank { HubContracts.DEFAULT_BASE_URL },
                hubUuid = json.optString("hubUuid"),
                deviceSecret = json.optString("deviceSecret"),
                publicId = json.optString("publicId").takeIf { it.isNotBlank() && it != "null" },
                gymName = json.optString("gymName").takeIf { it.isNotBlank() && it != "null" },
                branchName = json.optString("branchName").takeIf { it.isNotBlank() && it != "null" },
            )
        }.getOrElse {
            clear()
            null
        }
    }

    fun updateServerConfig(publicId: String?, gymName: String?, branchName: String?) {
        val current = load() ?: return
        save(current.copy(publicId = publicId ?: current.publicId, gymName = gymName ?: current.gymName, branchName = branchName ?: current.branchName))
    }

    fun clear() {
        prefs.edit().clear().apply()
    }

    fun setShouldRun(shouldRun: Boolean) {
        prefs.edit().putBoolean("shouldRun", shouldRun).apply()
    }

    fun shouldRun(): Boolean = prefs.getBoolean("shouldRun", false)

    private fun encrypt(bytes: ByteArray): EncryptedPayload {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateKey())
        return EncryptedPayload(
            payload = Base64.encodeToString(cipher.doFinal(bytes), Base64.NO_WRAP),
            iv = Base64.encodeToString(cipher.iv, Base64.NO_WRAP),
        )
    }

    private fun decrypt(payload: EncryptedPayload): ByteArray {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        val iv = Base64.decode(payload.iv, Base64.NO_WRAP)
        cipher.init(Cipher.DECRYPT_MODE, getOrCreateKey(), GCMParameterSpec(128, iv))
        return cipher.doFinal(Base64.decode(payload.payload, Base64.NO_WRAP))
    }

    private fun getOrCreateKey(): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (keyStore.getEntry(KEY_ALIAS, null) as? KeyStore.SecretKeyEntry)?.secretKey?.let { return it }

        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore")
        generator.init(
            KeyGenParameterSpec.Builder(KEY_ALIAS, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setRandomizedEncryptionRequired(true)
                .build()
        )
        return generator.generateKey()
    }

    private data class EncryptedPayload(val payload: String, val iv: String)

    companion object {
        private const val KEY_ALIAS = "atlas_smart_hub_credentials_v1"
        private const val TRANSFORMATION = "AES/GCM/NoPadding"
    }
}
