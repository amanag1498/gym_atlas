package com.techybugs.gymatlas.smarthub

object HubStartupPolicy {
    fun canBroadcastOffline(credentials: HubCredentials): Boolean =
        !credentials.publicId.isNullOrBlank()

    fun initialStatus(credentials: HubCredentials, bluetoothEnabled: Boolean): HubRuntimeStatus =
        HubRuntimeStatus(
            provisioned = true,
            serviceRunning = true,
            backendConnected = false,
            publicId = credentials.publicId,
            gymName = credentials.gymName,
            branchName = credentials.branchName,
            lastError = if (bluetoothEnabled) null else "Bluetooth is turned off.",
        )
}
