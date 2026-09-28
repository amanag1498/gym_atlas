package com.techybugs.gymatlas.smarthub

import java.nio.ByteBuffer
import java.nio.ByteOrder
import java.util.UUID

object AtlasBeaconProtocol {
    fun payload(hubId: Long): ByteArray {
        require(hubId in 1..0xffff_ffffL) { "Hub ID must fit the iBeacon major and minor fields." }
        val uuid = UUID.fromString(HubContracts.ATLAS_BLE_SERVICE_UUID)
        return ByteBuffer.allocate(23)
            .order(ByteOrder.BIG_ENDIAN)
            .put(0x02)
            .put(0x15)
            .putLong(uuid.mostSignificantBits)
            .putLong(uuid.leastSignificantBits)
            .putShort(((hubId ushr 16) and 0xffff).toShort())
            .putShort((hubId and 0xffff).toShort())
            .put(HubContracts.IBEACON_MEASURED_POWER.toByte())
            .array()
    }
}
