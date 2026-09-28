package com.techybugs.gymatlas.smarthub

import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class AtlasBeaconProtocolTest {
    @Test
    fun `encodes atlas uuid and hub id in iBeacon layout`() {
        val payload = AtlasBeaconProtocol.payload(0x1234_5678)

        assertEquals(23, payload.size)
        assertEquals(0x02, payload[0].toInt())
        assertEquals(0x15, payload[1].toInt())
        assertArrayEquals(byteArrayOf(0x12, 0x34, 0x56, 0x78), payload.copyOfRange(18, 22))
        assertEquals(HubContracts.IBEACON_MEASURED_POWER.toByte(), payload[22])
    }

    @Test
    fun `rejects ids that do not fit major and minor`() {
        assertThrows(IllegalArgumentException::class.java) { AtlasBeaconProtocol.payload(0) }
        assertThrows(IllegalArgumentException::class.java) { AtlasBeaconProtocol.payload(0x1_0000_0000L) }
    }
}
