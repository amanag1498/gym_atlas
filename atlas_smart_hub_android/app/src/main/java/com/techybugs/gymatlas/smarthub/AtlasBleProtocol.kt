package com.techybugs.gymatlas.smarthub

import java.math.BigInteger

object AtlasBleProtocol {
    private val publicIdPattern = Regex("^SAH[A-Z0-9]{${HubContracts.PUBLIC_ID_SUFFIX_LENGTH}}$")
    private val radix = BigInteger.valueOf(36)
    private val byteRadix = BigInteger.valueOf(256)

    fun payload(publicId: String): ByteArray {
        val normalized = publicId.trim().uppercase()
        require(publicIdPattern.matches(normalized)) {
            "Hub public ID must use the current SAH plus 13 letter-or-number format."
        }

        var value = BigInteger.ZERO
        normalized.removePrefix("SAH").forEach { character ->
            val digit = Character.digit(character, 36)
            require(digit >= 0) { "Hub public ID contains unsupported BLE characters." }
            value = value.multiply(radix).add(BigInteger.valueOf(digit.toLong()))
        }

        val compactId = ByteArray(HubContracts.COMPACT_PUBLIC_ID_BYTES)
        for (index in compactId.indices.reversed()) {
            val result = value.divideAndRemainder(byteRadix)
            compactId[index] = result[1].toInt().toByte()
            value = result[0]
        }
        require(value == BigInteger.ZERO) { "Hub public ID exceeds the compact BLE limit." }

        return byteArrayOf(HubContracts.PROTOCOL_VERSION.toByte()) + compactId
    }
}
