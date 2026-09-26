package com.techybugs.gymatlas.smarthub

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.bluetooth.le.AdvertiseCallback
import android.bluetooth.le.AdvertiseData
import android.bluetooth.le.AdvertiseSettings
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.ParcelUuid
import java.util.UUID

class AtlasBleAdvertiser(private val context: Context) {
    private val bluetoothManager = context.getSystemService(BluetoothManager::class.java)
    private val adapter: BluetoothAdapter? = bluetoothManager?.adapter
    private var callback: AdvertiseCallback? = null
    private var started = false

    val isAdvertising: Boolean
        get() = started
    val hasActiveRequest: Boolean
        get() = callback != null

    @SuppressLint("MissingPermission")
    fun bluetoothEnabled(): Boolean = adapter?.isEnabled == true

    @SuppressLint("MissingPermission")
    fun start(publicId: String, onStateChanged: (Boolean, String?) -> Unit = { _, _ -> }) {
        val payload = AtlasBleProtocol.payload(publicId)
        if (!hasAdvertisePermission()) throw IllegalStateException("Bluetooth advertise permission is not granted.")
        val bluetoothAdapter = adapter ?: throw IllegalStateException("Bluetooth is not available on this device.")
        if (!bluetoothAdapter.isEnabled) throw IllegalStateException("Bluetooth is turned off.")
        if (!bluetoothAdapter.isMultipleAdvertisementSupported) throw IllegalStateException("BLE advertising is not supported on this device.")

        stop()
        val serviceUuid = ParcelUuid(UUID.fromString(HubContracts.ATLAS_BLE_SERVICE_UUID))
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

        val advertiseCallback = object : AdvertiseCallback() {
            override fun onStartSuccess(settingsInEffect: AdvertiseSettings?) {
                started = true
                onStateChanged(true, null)
            }

            override fun onStartFailure(errorCode: Int) {
                started = false
                callback = null
                onStateChanged(false, "BLE advertising failed with code $errorCode.")
            }
        }
        started = false
        callback = advertiseCallback
        bluetoothAdapter.bluetoothLeAdvertiser?.startAdvertising(settings, data, scanResponse, advertiseCallback)
            ?: throw IllegalStateException("BLE advertiser is unavailable.")
    }

    @SuppressLint("MissingPermission")
    fun stop() {
        val advertiseCallback = callback ?: return
        if (hasAdvertisePermission()) {
            adapter?.bluetoothLeAdvertiser?.stopAdvertising(advertiseCallback)
        }
        callback = null
        started = false
    }

    private fun hasAdvertisePermission(): Boolean {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.S ||
            context.checkSelfPermission(Manifest.permission.BLUETOOTH_ADVERTISE) == PackageManager.PERMISSION_GRANTED
    }
}
