package com.techybugs.gymatlas.smarthub

import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertTrue
import org.junit.Assert.assertThrows
import org.junit.Test

class AtlasBleProtocolTest {
    @Test
    fun `builds the compact v2 payload`() {
        assertArrayEquals(
            byteArrayOf(2, 0x02, 0xa6.toByte(), 0x49, 0x21, 0xb5.toByte(), 0x36, 0x6c, 0xea.toByte(), 0x2f),
            AtlasBleProtocol.payload("SAHABC123DEF4567"),
        )
        assertArrayEquals(
            byteArrayOf(2, 0, 0, 0, 0, 0, 0, 0, 0, 0),
            AtlasBleProtocol.payload("SAH0000000000000"),
        )
        assertArrayEquals(
            byteArrayOf(2, 0x09, 0x3f, 0x4c, 0x09, 0xff.toByte(), 0xa3.toByte(), 0xff.toByte(), 0xff.toByte(), 0xff.toByte()),
            AtlasBleProtocol.payload("SAHZZZZZZZZZZZZZ"),
        )
    }

    @Test
    fun `rejects empty oversized and unsafe public ids`() {
        assertThrows(IllegalArgumentException::class.java) { AtlasBleProtocol.payload("") }
        assertThrows(IllegalArgumentException::class.java) { AtlasBleProtocol.payload("SAHABC") }
        assertThrows(IllegalArgumentException::class.java) { AtlasBleProtocol.payload("unsafe id") }
    }

    @Test
    fun `explains Android advertising failures with a recovery action`() {
        assertTrue(advertisingError(1).contains("data too large"))
        assertTrue(advertisingError(2).contains("no free advertiser slot"))
        assertTrue(advertisingError(4).contains("Toggle Bluetooth"))
    }
}
