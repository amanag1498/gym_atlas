package com.techybugs.gymatlas.member

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class SmartAttendanceNativeProtocolTest {
    @Test
    fun `decodes compact Atlas hub identity for headless sync`() {
        val payload = byteArrayOf(
            2,
            0x02,
            0xa6.toByte(),
            0x49,
            0x21,
            0xb5.toByte(),
            0x36,
            0x6c,
            0xea.toByte(),
            0x2f,
        )

        val decoded = SmartAttendanceNativeProtocol.decode(payload)

        assertEquals(2, decoded?.protocolVersion)
        assertEquals("SAHABC123DEF4567", decoded?.publicId)
    }

    @Test
    fun `rejects malformed payloads`() {
        assertNull(SmartAttendanceNativeProtocol.decode(byteArrayOf()))
        assertNull(SmartAttendanceNativeProtocol.decode(byteArrayOf(9, 1)))
        assertNull(SmartAttendanceNativeProtocol.decode(byteArrayOf(2, 1, 2)))
    }

    @Test
    fun `decodes Atlas iBeacon identity used by dedicated hardware`() {
        val payload = byteArrayOf(
            0x02, 0x15,
            0x8b.toByte(), 0x0f, 0x9c.toByte(), 0x60, 0x4f, 0x6d, 0x4b, 0x40,
            0x9e.toByte(), 0x8d.toByte(), 0x2d, 0x5d, 0x3f, 0x73, 0xa1.toByte(), 0xa1.toByte(),
            0x00, 0x12, 0x34, 0x56, 0xc5.toByte(),
        )

        val decoded = SmartAttendanceNativeProtocol.decodeIBeacon(payload)

        assertEquals(3, decoded?.protocolVersion)
        assertEquals(0x00123456L, decoded?.hubId)
        assertNull(decoded?.publicId)
    }
}
