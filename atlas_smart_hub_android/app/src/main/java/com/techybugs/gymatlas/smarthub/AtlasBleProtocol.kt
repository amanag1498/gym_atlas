package com.techybugs.gymatlas.smarthub

object AtlasBleProtocol {
    private val publicIdPattern = Regex("^[A-Z0-9_-]+$")

    fun payload(publicId: String): ByteArray {
        val normalized = publicId.trim()
        val publicIdBytes = normalized.toByteArray(Charsets.UTF_8)
        require(normalized.isNotEmpty()) { "Hub public ID is missing. Activate the hub before broadcasting." }
        require(publicIdBytes.size <= HubContracts.PUBLIC_ID_MAX_BYTES) { "Hub public ID exceeds the BLE V1 limit." }
        require(publicIdPattern.matches(normalized)) { "Hub public ID contains unsupported BLE characters." }

        return byteArrayOf(HubContracts.PROTOCOL_VERSION.toByte()) + publicIdBytes
    }
}
