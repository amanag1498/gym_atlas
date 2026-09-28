package com.techybugs.gymatlas.smarthub

import android.os.Parcelable
import kotlinx.parcelize.Parcelize

object HubContracts {
    const val DEFAULT_BASE_URL = "https://gymatlas.in"
    const val DEVICE_TOKEN_HEADER = "X-GymAtlas-Device-Token"
    const val HEARTBEAT_INTERVAL_SECONDS = 60L
    const val ATLAS_BLE_SERVICE_UUID = "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1"
    const val IBEACON_COMPANY_ID = 0x004C
    const val IBEACON_MEASURED_POWER = -59
    const val PROTOCOL_VERSION = 2
    const val PUBLIC_ID_SUFFIX_LENGTH = 13
    const val COMPACT_PUBLIC_ID_BYTES = 9

    const val ACTION_STATUS_CHANGED = "com.techybugs.gymatlas.smarthub.STATUS_CHANGED"
    const val EXTRA_STATUS = "status"
}

@Parcelize
data class HubCredentials(
    val baseUrl: String,
    val hubUuid: String,
    val deviceSecret: String,
    val hubId: Long? = null,
    val publicId: String? = null,
    val gymName: String? = null,
    val branchName: String? = null,
) : Parcelable

@Parcelize
data class HubRuntimeStatus(
    val provisioned: Boolean = false,
    val serviceRunning: Boolean = false,
    val bleAdvertising: Boolean = false,
    val backendConnected: Boolean = false,
    val lastHeartbeatAt: String? = null,
    val lastStatusAt: String? = null,
    val lastError: String? = null,
    val advertisingMode: String? = null,
    val batteryPercent: Int? = null,
    val publicId: String? = null,
    val gymName: String? = null,
    val branchName: String? = null,
) : Parcelable
