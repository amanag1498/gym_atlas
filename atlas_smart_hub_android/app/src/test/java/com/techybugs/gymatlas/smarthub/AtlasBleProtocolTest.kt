package com.techybugs.gymatlas.smarthub

import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class AtlasBleProtocolTest {
    @Test
    fun `builds the frozen v1 payload`() {
        assertArrayEquals(
            byteArrayOf(1) + "SAHABC123DEF4567".toByteArray(Charsets.UTF_8),
            AtlasBleProtocol.payload("SAHABC123DEF4567"),
        )
    }

    @Test
    fun `rejects empty oversized and unsafe public ids`() {
        assertThrows(IllegalArgumentException::class.java) { AtlasBleProtocol.payload("") }
        assertThrows(IllegalArgumentException::class.java) { AtlasBleProtocol.payload("A".repeat(21)) }
        assertThrows(IllegalArgumentException::class.java) { AtlasBleProtocol.payload("unsafe id") }
    }
}
