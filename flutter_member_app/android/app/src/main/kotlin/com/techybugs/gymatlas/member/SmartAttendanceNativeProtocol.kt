package com.techybugs.gymatlas.member

import java.math.BigInteger

data class SmartAttendanceNativePayload(
    val protocolVersion: Int,
    val publicId: String? = null,
    val hubId: Long? = null,
) {
    val key: String get() = publicId ?: "BEACON_$hubId"
}

object SmartAttendanceNativeProtocol {
    private val publicIdPattern = Regex("^[A-Z0-9_-]+$")

    fun decode(data: ByteArray): SmartAttendanceNativePayload? {
        if (data.size < 2) return null
        return when (val version = data[0].toInt() and 0xff) {
            1 -> {
                val publicId = runCatching { data.copyOfRange(1, data.size).toString(Charsets.UTF_8) }.getOrNull()
                    ?: return null
                if (publicId.isBlank() || publicId.length > 20 || !publicIdPattern.matches(publicId)) null
                else SmartAttendanceNativePayload(version, publicId)
            }
            2 -> {
                if (data.size != 10) return null
                val suffix = BigInteger(1, data.copyOfRange(1, data.size))
                    .toString(36)
                    .uppercase()
                    .padStart(13, '0')
                if (suffix.length != 13) null else SmartAttendanceNativePayload(version, "SAH$suffix")
            }
            else -> null
        }
    }

    fun decodeIBeacon(data: ByteArray): SmartAttendanceNativePayload? {
        if (data.size < 23 || data[0] != 0x02.toByte() || data[1] != 0x15.toByte()) return null
        val atlasUuid = byteArrayOf(
            0x8b.toByte(), 0x0f, 0x9c.toByte(), 0x60, 0x4f, 0x6d, 0x4b, 0x40,
            0x9e.toByte(), 0x8d.toByte(), 0x2d, 0x5d, 0x3f, 0x73, 0xa1.toByte(), 0xa1.toByte(),
        )
        if (!data.copyOfRange(2, 18).contentEquals(atlasUuid)) return null
        val major = ((data[18].toInt() and 0xff) shl 8) or (data[19].toInt() and 0xff)
        val minor = ((data[20].toInt() and 0xff) shl 8) or (data[21].toInt() and 0xff)
        val hubId = (major.toLong() shl 16) or minor.toLong()
        return hubId.takeIf { it > 0 }?.let { SmartAttendanceNativePayload(3, hubId = it) }
    }
}
