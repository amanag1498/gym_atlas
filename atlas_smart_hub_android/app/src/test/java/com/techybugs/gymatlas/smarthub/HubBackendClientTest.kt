package com.techybugs.gymatlas.smarthub

import org.junit.Assert.assertEquals
import org.junit.Test

class HubBackendClientTest {
    @Test
    fun `activation errors prefer the backend message`() {
        assertEquals(
            "This hub secret has expired.",
            hubBackendError(403, "{\"message\":\"This hub secret has expired.\"}", "Forbidden"),
        )
    }

    @Test
    fun `activation errors extract the first validation message`() {
        assertEquals(
            "The firmware version is required.",
            hubBackendError(
                422,
                "{\"message\":\"Validation failed.\",\"errors\":{\"firmware_version\":[\"The firmware version is required.\"]}}",
                "Unprocessable Content",
            ),
        )
    }
}
