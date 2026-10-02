package com.techybugs.gymatlas.smarthub

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.bluetooth.BluetoothAdapter
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.ConnectivityManager
import android.net.Network
import android.os.BatteryManager
import android.os.IBinder
import android.os.SystemClock
import java.time.Instant
import java.util.concurrent.Executors
import java.util.concurrent.ScheduledExecutorService
import java.util.concurrent.TimeUnit

class HubForegroundService : Service() {
    private lateinit var store: SecureCredentialStore
    private lateinit var backend: HubBackendClient
    private lateinit var advertiser: AtlasBleAdvertiser
    private var executor: ScheduledExecutorService? = null
    private var runtimeStatus = HubRuntimeStatus()
    private var lastConfigRefreshElapsed = 0L
    private var receiversRegistered = false

    private val bluetoothReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            if (intent?.action != BluetoothAdapter.ACTION_STATE_CHANGED) return
            when (intent.getIntExtra(BluetoothAdapter.EXTRA_STATE, BluetoothAdapter.ERROR)) {
                BluetoothAdapter.STATE_ON -> scheduleNow { ensureBroadcasting(); sendHeartbeat(forceConfig = true) }
                BluetoothAdapter.STATE_OFF, BluetoothAdapter.STATE_TURNING_OFF -> scheduleNow {
                    advertiser.stop()
                    publish(
                        runtimeStatus.copy(
                            bleAdvertising = false,
                            backendConnected = false,
                            advertisingMode = null,
                            lastError = "Bluetooth is turned off. Turn it on to resume the entrance signal.",
                        ),
                    )
                }
            }
        }
    }

    private val networkCallback = object : ConnectivityManager.NetworkCallback() {
        override fun onAvailable(network: Network) {
            scheduleNow { sendHeartbeat(forceConfig = true) }
        }

        override fun onLost(network: Network) {
            scheduleNow {
                publish(
                    runtimeStatus.copy(
                        backendConnected = false,
                        lastError = if (advertiser.isAdvertising) null else runtimeStatus.lastError,
                    ),
                )
            }
        }
    }

    override fun onCreate() {
        super.onCreate()
        store = SecureCredentialStore(this)
        backend = HubBackendClient()
        advertiser = AtlasBleAdvertiser(this)
        runtimeStatus = store.loadRuntimeStatus() ?: HubRuntimeStatus()
        createNotificationChannel()
        registerRuntimeObservers()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            store.setShouldRun(false)
            stopSelf()
            return START_NOT_STICKY
        }

        startForeground(NOTIFICATION_ID, notification("Starting Smart Hub"))
        startLoop()
        if (intent?.action == ACTION_REFRESH) {
            scheduleNow { sendHeartbeat(forceConfig = true) }
        }
        return START_STICKY
    }

    override fun onDestroy() {
        executor?.shutdownNow()
        advertiser.stop()
        unregisterRuntimeObservers()
        publish(runtimeStatus.copy(serviceRunning = false, bleAdvertising = false, advertisingMode = null))
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun startLoop() {
        if (executor?.isShutdown == false) return
        executor = Executors.newSingleThreadScheduledExecutor().also { scheduler ->
            scheduler.execute { activateAndHeartbeat() }
            scheduler.scheduleAtFixedRate(
                { sendHeartbeat() },
                HubContracts.HEARTBEAT_INTERVAL_SECONDS,
                HubContracts.HEARTBEAT_INTERVAL_SECONDS,
                TimeUnit.SECONDS,
            )
        }
    }

    private fun activateAndHeartbeat() {
        val credentials = store.load()
        if (credentials == null) {
            store.setShouldRun(false)
            publish(HubRuntimeStatus(lastError = "No hub credentials are saved. Activate this phone again."))
            stopSelf()
            return
        }

        publish(HubStartupPolicy.initialStatus(credentials, bluetoothEnabled()).copy(batteryPercent = batteryPercent()))
        ensureBroadcasting(credentials)
        sendHeartbeat(forceConfig = true)
    }

    private fun sendHeartbeat(forceConfig: Boolean = false) {
        var credentials = store.load() ?: return
        runCatching {
            if (credentials.publicId.isNullOrBlank()) {
                credentials = backend.activate(credentials, BuildInfo.firmwareVersion)
                store.save(credentials)
            }

            val previousPublicId = credentials.publicId
            credentials = refreshConfig(credentials, forceConfig)
            if (previousPublicId != credentials.publicId && advertiser.hasActiveRequest) {
                advertiser.stop()
            }
            ensureBroadcasting(credentials)
            val status = backend.heartbeat(
                credentials,
                BuildInfo.firmwareVersion,
                advertiser.isAdvertising,
                batteryPercent(),
            )
            publish(
                status.copy(
                    gymName = credentials.gymName,
                    branchName = credentials.branchName,
                    publicId = credentials.publicId,
                    advertisingMode = advertiser.mode,
                ),
            )
        }.onFailure(::handleBackendFailure)
    }

    private fun refreshConfig(credentials: HubCredentials, force: Boolean): HubCredentials {
        val now = SystemClock.elapsedRealtime()
        if (!force && now - lastConfigRefreshElapsed < CONFIG_REFRESH_INTERVAL_MS) return credentials
        val refreshed = backend.config(credentials)
        store.save(refreshed)
        lastConfigRefreshElapsed = now
        return refreshed
    }

    private fun ensureBroadcasting(credentials: HubCredentials? = store.load()) {
        val publicId = credentials?.publicId
        if (!bluetoothEnabled()) {
            advertiser.stop()
            publish(
                runtimeStatus.copy(
                    provisioned = credentials != null,
                    bleAdvertising = false,
                    backendConnected = false,
                    advertisingMode = null,
                    lastError = "Bluetooth is turned off. Turn it on to resume the entrance signal.",
                ),
            )
            return
        }
        val hubId = credentials?.hubId
        if (publicId.isNullOrBlank() || hubId == null || advertiser.hasActiveRequest) return
        runCatching { startBle(publicId, hubId) }
            .onFailure { error -> publish(runtimeStatus.copy(lastError = error.message ?: "BLE advertising could not start.")) }
    }

    private fun startBle(publicId: String, hubId: Long) {
        advertiser.start(publicId, hubId) { state ->
            publish(
                runtimeStatus.copy(
                    provisioned = true,
                    serviceRunning = true,
                    bleAdvertising = state.advertising,
                    advertisingMode = state.mode,
                    lastError = state.error,
                ),
            )
            if (state.advertising) scheduleNow { sendHeartbeat() }
        }
    }

    private fun handleBackendFailure(error: Throwable) {
        val fatalCredentialError = error is HubBackendException && error.statusCode in listOf(401, 403, 404)
        if (fatalCredentialError) {
            advertiser.stop()
            store.setShouldRun(false)
        }
        val canKeepBroadcasting = store.load()?.let(HubStartupPolicy::canBroadcastOffline) == true
        publish(
            runtimeStatus.copy(
                provisioned = store.load() != null,
                serviceRunning = !fatalCredentialError,
                bleAdvertising = advertiser.isAdvertising,
                backendConnected = false,
                advertisingMode = advertiser.mode,
                batteryPercent = batteryPercent(),
                lastError = when {
                    fatalCredentialError -> error.message ?: "Hub access was revoked. Activate with the current secret."
                    !canKeepBroadcasting -> error.message ?: "Initial activation requires internet."
                    !bluetoothEnabled() -> "Bluetooth is turned off. Turn it on to resume the entrance signal."
                    advertiser.isAdvertising -> null
                    else -> runtimeStatus.lastError ?: error.message
                },
            ),
        )
        if (fatalCredentialError) stopSelf()
    }

    private fun bluetoothEnabled(): Boolean = runCatching { advertiser.bluetoothEnabled() }.getOrDefault(false)

    private fun batteryPercent(): Int? =
        getSystemService(BatteryManager::class.java)
            ?.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
            ?.takeIf { it in 0..100 }

    private fun publish(status: HubRuntimeStatus) {
        val credentials = store.load()
        runtimeStatus = status.copy(
            serviceRunning = status.serviceRunning || executor?.isShutdown == false,
            lastStatusAt = Instant.now().toString(),
            advertisingMode = status.advertisingMode ?: advertiser.mode,
            batteryPercent = status.batteryPercent ?: batteryPercent(),
            publicId = status.publicId ?: credentials?.publicId,
            gymName = status.gymName ?: credentials?.gymName,
            branchName = status.branchName ?: credentials?.branchName,
        )
        store.saveRuntimeStatus(runtimeStatus)
        getSystemService(NotificationManager::class.java).notify(NOTIFICATION_ID, notification(notificationText(runtimeStatus)))
        sendBroadcast(
            Intent(HubContracts.ACTION_STATUS_CHANGED)
                .setPackage(packageName)
                .putExtra(HubContracts.EXTRA_STATUS, runtimeStatus),
        )
    }

    private fun notificationText(status: HubRuntimeStatus): String = when {
        status.bleAdvertising && status.backendConnected -> "Entrance signal active · backend connected"
        status.bleAdvertising -> "Entrance signal active · hub internet offline"
        status.lastError != null -> "Needs attention · ${status.lastError.take(72)}"
        else -> "Smart Hub service running"
    }

    private fun notification(text: String): Notification {
        val openApp = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        return Notification.Builder(this, CHANNEL_ID)
            .setContentTitle("Atlas Smart Hub")
            .setContentText(text)
            .setSmallIcon(R.drawable.ic_stat_atlas)
            .setContentIntent(openApp)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .build()
    }

    private fun createNotificationChannel() {
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(CHANNEL_ID, "Smart Hub status", NotificationManager.IMPORTANCE_LOW),
        )
    }

    private fun registerRuntimeObservers() {
        registerReceiver(bluetoothReceiver, IntentFilter(BluetoothAdapter.ACTION_STATE_CHANGED))
        runCatching { getSystemService(ConnectivityManager::class.java).registerDefaultNetworkCallback(networkCallback) }
        receiversRegistered = true
    }

    private fun unregisterRuntimeObservers() {
        if (!receiversRegistered) return
        runCatching { unregisterReceiver(bluetoothReceiver) }
        runCatching { getSystemService(ConnectivityManager::class.java).unregisterNetworkCallback(networkCallback) }
        receiversRegistered = false
    }

    private fun scheduleNow(action: () -> Unit) {
        val scheduler = executor
        if (scheduler != null && !scheduler.isShutdown) scheduler.execute(action)
    }

    companion object {
        const val ACTION_STOP = "com.techybugs.gymatlas.smarthub.STOP"
        const val ACTION_REFRESH = "com.techybugs.gymatlas.smarthub.REFRESH"
        private const val CHANNEL_ID = "atlas_smart_hub_status"
        private const val NOTIFICATION_ID = 4101
        private const val CONFIG_REFRESH_INTERVAL_MS = 10 * 60 * 1000L
    }
}

object BuildInfo {
    const val firmwareVersion = "atlas-smart-hub-android-0.4.0"
}
