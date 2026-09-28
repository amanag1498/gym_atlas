package com.techybugs.gymatlas.smarthub

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.bluetooth.le.AdvertiseCallback
import android.bluetooth.le.AdvertiseData
import android.bluetooth.le.AdvertiseSettings
import android.bluetooth.le.AdvertisingSet
import android.bluetooth.le.AdvertisingSetCallback
import android.bluetooth.le.AdvertisingSetParameters
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.ParcelUuid
import java.util.UUID

class AtlasBleAdvertiser(private val context: Context) {
    private val bluetoothManager = context.getSystemService(BluetoothManager::class.java)
    private val adapter: BluetoothAdapter? = bluetoothManager?.adapter
    private var legacyCallback: AdvertiseCallback? = null
    private var extendedCallback: AdvertisingSetCallback? = null
    private var advertisingSet: AdvertisingSet? = null
    private var stateCallback: (BleAdvertisingState) -> Unit = {}

    var isAdvertising: Boolean = false
        private set
    var mode: String? = null
        private set
    val hasActiveRequest: Boolean
        get() = legacyCallback != null || extendedCallback != null

    @SuppressLint("MissingPermission")
    fun bluetoothEnabled(): Boolean = adapter?.isEnabled == true

    @SuppressLint("MissingPermission")
    fun start(publicId: String, onStateChanged: (BleAdvertisingState) -> Unit = {}) {
        val payload = AtlasBleProtocol.payload(publicId)
        if (!hasAdvertisePermission()) throw IllegalStateException("Nearby devices permission is required to broadcast.")
        val bluetoothAdapter = adapter ?: throw IllegalStateException("Bluetooth is not available on this device.")
        if (!bluetoothAdapter.isEnabled) throw IllegalStateException("Bluetooth is turned off.")
        if (!bluetoothAdapter.isMultipleAdvertisementSupported) throw IllegalStateException("BLE advertising is not supported on this device.")

        stop()
        stateCallback = onStateChanged
        startLegacy(bluetoothAdapter, payload)
    }

    @SuppressLint("MissingPermission")
    private fun startLegacy(bluetoothAdapter: BluetoothAdapter, payload: ByteArray) {
        val serviceUuid = serviceUuid()
        val settings = AdvertiseSettings.Builder()
            .setAdvertiseMode(AdvertiseSettings.ADVERTISE_MODE_LOW_LATENCY)
            .setTxPowerLevel(AdvertiseSettings.ADVERTISE_TX_POWER_MEDIUM)
            .setConnectable(false)
            .build()
        val data = AdvertiseData.Builder()
            .addServiceUuid(serviceUuid)
            .setIncludeDeviceName(false)
            .setIncludeTxPowerLevel(false)
            .build()
        val scanResponse = AdvertiseData.Builder()
            .addServiceData(serviceUuid, payload)
            .setIncludeDeviceName(false)
            .setIncludeTxPowerLevel(false)
            .build()

        val callback = object : AdvertiseCallback() {
            override fun onStartSuccess(settingsInEffect: AdvertiseSettings?) {
                isAdvertising = true
                mode = MODE_LEGACY
                stateCallback(BleAdvertisingState(true, MODE_LEGACY))
            }

            override fun onStartFailure(errorCode: Int) {
                legacyCallback = null
                isAdvertising = false
                mode = null
                if (errorCode == ADVERTISE_FAILED_DATA_TOO_LARGE && canUseExtended(bluetoothAdapter)) {
                    startExtended(bluetoothAdapter, payload)
                    return
                }
                stateCallback(BleAdvertisingState(false, error = advertisingError(errorCode)))
            }
        }
        legacyCallback = callback
        bluetoothAdapter.bluetoothLeAdvertiser?.startAdvertising(settings, data, scanResponse, callback)
            ?: throw IllegalStateException("BLE advertiser is unavailable.")
    }

    @SuppressLint("MissingPermission")
    private fun startExtended(bluetoothAdapter: BluetoothAdapter, payload: ByteArray) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            stateCallback(
                BleAdvertisingState(
                    false,
                    error = advertisingError(AdvertiseCallback.ADVERTISE_FAILED_DATA_TOO_LARGE),
                ),
            )
            return
        }
        val serviceUuid = serviceUuid()
        val data = AdvertiseData.Builder()
            .addServiceUuid(serviceUuid)
            .addServiceData(serviceUuid, payload)
            .setIncludeDeviceName(false)
            .setIncludeTxPowerLevel(false)
            .build()
        val parameters = AdvertisingSetParameters.Builder()
            .setLegacyMode(false)
            .setConnectable(false)
            .setScannable(false)
            .setInterval(AdvertisingSetParameters.INTERVAL_MEDIUM)
            .setTxPowerLevel(AdvertisingSetParameters.TX_POWER_MEDIUM)
            .setPrimaryPhy(PHY_LE_1M)
            .setSecondaryPhy(PHY_LE_1M)
            .build()
        val callback = object : AdvertisingSetCallback() {
            override fun onAdvertisingSetStarted(set: AdvertisingSet?, txPower: Int, status: Int) {
                if (status == ADVERTISE_SUCCESS && set != null) {
                    advertisingSet = set
                    isAdvertising = true
                    mode = MODE_EXTENDED
                    stateCallback(BleAdvertisingState(true, MODE_EXTENDED, usedFallback = true))
                    return
                }
                extendedCallback = null
                advertisingSet = null
                isAdvertising = false
                mode = null
                stateCallback(BleAdvertisingState(false, error = advertisingError(status, extended = true)))
            }

            override fun onAdvertisingSetStopped(set: AdvertisingSet?) {
                if (set === advertisingSet) {
                    advertisingSet = null
                    isAdvertising = false
                    mode = null
                }
            }
        }
        extendedCallback = callback
        bluetoothAdapter.bluetoothLeAdvertiser?.startAdvertisingSet(parameters, data, null, null, null, callback)
            ?: throw IllegalStateException("Extended BLE advertiser is unavailable.")
    }

    @SuppressLint("MissingPermission")
    fun stop() {
        if (hasAdvertisePermission()) {
            legacyCallback?.let { adapter?.bluetoothLeAdvertiser?.stopAdvertising(it) }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                extendedCallback?.let { adapter?.bluetoothLeAdvertiser?.stopAdvertisingSet(it) }
            }
        }
        legacyCallback = null
        extendedCallback = null
        advertisingSet = null
        isAdvertising = false
        mode = null
    }

    @SuppressLint("MissingPermission")
    private fun canUseExtended(bluetoothAdapter: BluetoothAdapter): Boolean =
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && bluetoothAdapter.isLeExtendedAdvertisingSupported

    private fun hasAdvertisePermission(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.S ||
            context.checkSelfPermission(Manifest.permission.BLUETOOTH_ADVERTISE) == PackageManager.PERMISSION_GRANTED

    private fun serviceUuid(): ParcelUuid = ParcelUuid(UUID.fromString(HubContracts.ATLAS_BLE_SERVICE_UUID))

    companion object {
        const val MODE_LEGACY = "Legacy BLE"
        const val MODE_EXTENDED = "Extended BLE fallback"
        private const val PHY_LE_1M = 1
    }
}

data class BleAdvertisingState(
    val advertising: Boolean,
    val mode: String? = null,
    val error: String? = null,
    val usedFallback: Boolean = false,
)

internal fun advertisingError(code: Int, extended: Boolean = false): String {
    val prefix = if (extended) "Extended BLE advertising" else "BLE advertising"
    return when (code) {
        AdvertiseCallback.ADVERTISE_FAILED_DATA_TOO_LARGE -> "$prefix could not fit the Atlas signal (data too large)."
        AdvertiseCallback.ADVERTISE_FAILED_TOO_MANY_ADVERTISERS -> "$prefix is busy because this phone has no free advertiser slot. Stop Nearby Share or another broadcaster, then retry."
        AdvertiseCallback.ADVERTISE_FAILED_ALREADY_STARTED -> "$prefix was already starting. Wait a moment and refresh."
        AdvertiseCallback.ADVERTISE_FAILED_INTERNAL_ERROR -> "$prefix hit a phone Bluetooth error. Toggle Bluetooth off and on, then retry."
        AdvertiseCallback.ADVERTISE_FAILED_FEATURE_UNSUPPORTED -> "$prefix is not supported by this phone."
        else -> "$prefix failed with Android error $code. Toggle Bluetooth and retry."
    }
}
