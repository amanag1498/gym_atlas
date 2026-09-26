package com.techybugs.gymatlas.smarthub

import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.time.Instant

class HubBackendClient {
    fun activate(credentials: HubCredentials, firmwareVersion: String): HubCredentials {
        val response = request(credentials, "POST", "/api/smart-attendance/hubs/${credentials.hubUuid}/activate") {
            put("firmware_version", firmwareVersion)
            put("ble_advertising", false)
        }
        return credentialsFromResponse(credentials, response)
    }

    fun heartbeat(credentials: HubCredentials, firmwareVersion: String, bleAdvertising: Boolean): HubRuntimeStatus {
        val response = request(credentials, "POST", "/api/smart-attendance/hubs/${credentials.hubUuid}/heartbeat") {
            put("firmware_version", firmwareVersion)
            put("ble_advertising", bleAdvertising)
        }
        val data = response.optJSONObject("data") ?: JSONObject()
        val hub = data.optJSONObject("hub") ?: JSONObject()
        return HubRuntimeStatus(
            provisioned = true,
            serviceRunning = true,
            bleAdvertising = bleAdvertising,
            backendConnected = true,
            lastHeartbeatAt = data.optString("server_time", Instant.now().toString()),
            publicId = hub.optString("public_id").takeIf { it.isNotBlank() },
        )
    }

    fun config(credentials: HubCredentials): HubCredentials {
        val response = request(credentials, "GET", "/api/smart-attendance/hubs/${credentials.hubUuid}/config")
        return credentialsFromResponse(credentials, response)
    }

    private fun credentialsFromResponse(current: HubCredentials, response: JSONObject): HubCredentials {
        val data = response.optJSONObject("data") ?: JSONObject()
        val hub = data.optJSONObject("hub") ?: JSONObject()
        val gym = data.optJSONObject("gym") ?: JSONObject()
        val branch = data.optJSONObject("branch")
        return current.copy(
            publicId = hub.optString("public_id").takeIf { it.isNotBlank() } ?: current.publicId,
            gymName = gym.optString("name").takeIf { it.isNotBlank() } ?: current.gymName,
            branchName = branch?.optString("name")?.takeIf { it.isNotBlank() } ?: current.branchName,
        )
    }

    private fun request(credentials: HubCredentials, method: String, path: String, bodyBuilder: (JSONObject.() -> Unit)? = null): JSONObject {
        val baseUrl = credentials.baseUrl.trimEnd('/')
        val connection = (URL(baseUrl + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 12_000
            readTimeout = 12_000
            setRequestProperty("Accept", "application/json")
            setRequestProperty(HubContracts.DEVICE_TOKEN_HEADER, credentials.deviceSecret)
        }

        if (bodyBuilder != null) {
            val body = JSONObject().apply(bodyBuilder).toString()
            connection.doOutput = true
            connection.setRequestProperty("Content-Type", "application/json")
            OutputStreamWriter(connection.outputStream, Charsets.UTF_8).use { it.write(body) }
        }

        try {
            val statusCode = connection.responseCode
            val stream = if (statusCode in 200..299) connection.inputStream else connection.errorStream
            val responseText = stream?.let { BufferedReader(InputStreamReader(it)).use(BufferedReader::readText) }.orEmpty()
            if (statusCode !in 200..299) {
                throw IllegalStateException("Backend returned $statusCode: ${responseText.ifBlank { connection.responseMessage }}")
            }
            return JSONObject(responseText)
        } finally {
            connection.disconnect()
        }
    }
}
