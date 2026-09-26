package com.techybugs.gymatlas.smarthub

import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class HubStartupPolicyTest {
    private val baseCredentials = HubCredentials(
        baseUrl = "https://gymatlas.in",
        hubUuid = "hub-uuid",
        deviceSecret = "secret",
    )

    @Test
    fun `one successful activation makes the hub offline ready`() {
        val credentials = baseCredentials.copy(
            publicId = "SAHABC123DEF4567",
            gymName = "Atlas Gym",
            branchName = "Main branch",
        )

        assertTrue(HubStartupPolicy.canBroadcastOffline(credentials))

        val status = HubStartupPolicy.initialStatus(credentials, bluetoothEnabled = true)
        assertTrue(status.provisioned)
        assertTrue(status.serviceRunning)
        assertFalse(status.backendConnected)
        assertNull(status.lastError)
    }

    @Test
    fun `credentials without a public id still require online activation`() {
        assertFalse(HubStartupPolicy.canBroadcastOffline(baseCredentials))
        assertFalse(HubStartupPolicy.canBroadcastOffline(baseCredentials.copy(publicId = " ")))
    }
}
