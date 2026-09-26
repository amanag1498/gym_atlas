package com.techybugs.gymatlas.smarthub

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Intent
import android.os.IBinder
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

    override fun onCreate() {
        super.onCreate()
        store = SecureCredentialStore(this)
        backend = HubBackendClient()
        advertiser = AtlasBleAdvertiser(this)
        createNotificationChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            store.setShouldRun(false)
            stopSelf()
            return START_NOT_STICKY
        }

        startForeground(NOTIFICATION_ID, notification("Starting Smart Hub"))
        startLoop()
        return START_STICKY
    }

    override fun onDestroy() {
        executor?.shutdownNow()
        advertiser.stop()
        publish(runtimeStatus.copy(serviceRunning = false, bleAdvertising = false))
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun startLoop() {
        if (executor?.isShutdown == false) return
        executor = Executors.newSingleThreadScheduledExecutor().also { scheduler ->
            scheduler.execute { activateAndHeartbeat() }
            scheduler.scheduleAtFixedRate({ sendHeartbeat() }, HubContracts.HEARTBEAT_INTERVAL_SECONDS, HubContracts.HEARTBEAT_INTERVAL_SECONDS, TimeUnit.SECONDS)
        }
    }

    private fun activateAndHeartbeat() {
        val credentials = store.load()
        if (credentials == null) {
            store.setShouldRun(false)
            publish(HubRuntimeStatus(lastError = "No hub credentials saved."))
            stopSelf()
            return
        }

        runCatching {
            val activated = backend.activate(credentials, BuildInfo.firmwareVersion)
            store.save(activated)
            if (advertiser.bluetoothEnabled()) {
                startBle(activated.publicId)
            }
            val status = backend.heartbeat(activated, BuildInfo.firmwareVersion, advertiser.isAdvertising)
            publish(
                status.copy(
                    gymName = activated.gymName,
                    branchName = activated.branchName,
                    publicId = activated.publicId,
                    lastError = if (advertiser.bluetoothEnabled()) null else "Bluetooth is turned off.",
                )
            )
        }.onFailure { error ->
            publish(
                HubRuntimeStatus(
                    provisioned = true,
                    serviceRunning = true,
                    bleAdvertising = advertiser.isAdvertising,
                    backendConnected = false,
                    publicId = credentials.publicId,
                    gymName = credentials.gymName,
                    branchName = credentials.branchName,
                    lastError = error.message ?: "Activation failed.",
                )
            )
        }
    }

    private fun sendHeartbeat() {
        var credentials = store.load() ?: return
        runCatching {
            if (credentials.publicId.isNullOrBlank()) {
                credentials = backend.activate(credentials, BuildInfo.firmwareVersion)
                store.save(credentials)
            }
            if (!advertiser.bluetoothEnabled()) {
                advertiser.stop()
            } else if (!advertiser.hasActiveRequest && credentials.publicId != null) {
                startBle(credentials.publicId)
            }
            val status = backend.heartbeat(credentials, BuildInfo.firmwareVersion, advertiser.isAdvertising)
            publish(status.copy(gymName = credentials.gymName, branchName = credentials.branchName, publicId = credentials.publicId))
        }.onFailure { error ->
            publish(
                runtimeStatus.copy(
                    provisioned = true,
                    serviceRunning = true,
                    bleAdvertising = advertiser.isAdvertising,
                    backendConnected = false,
                    lastError = error.message ?: "Heartbeat failed at ${Instant.now()}",
                )
            )
        }
    }

    private fun startBle(publicId: String?) {
        if (publicId.isNullOrBlank()) return
        advertiser.start(publicId) { advertising, error ->
            publish(
                runtimeStatus.copy(
                    provisioned = true,
                    serviceRunning = true,
                    bleAdvertising = advertising,
                    lastError = error,
                )
            )
            if (advertising) {
                executor?.execute { sendHeartbeat() }
            }
        }
    }

    private fun publish(status: HubRuntimeStatus) {
        runtimeStatus = status.copy(serviceRunning = status.serviceRunning || executor?.isShutdown == false)
        getSystemService(NotificationManager::class.java).notify(NOTIFICATION_ID, notification(notificationText(runtimeStatus)))
        sendBroadcast(Intent(HubContracts.ACTION_STATUS_CHANGED).setPackage(packageName).putExtra(HubContracts.EXTRA_STATUS, runtimeStatus))
    }

    private fun notificationText(status: HubRuntimeStatus): String {
        return when {
            status.lastError != null -> "Needs attention: ${status.lastError.take(80)}"
            status.bleAdvertising && status.backendConnected -> "Broadcasting ${status.publicId ?: "hub"} and connected"
            status.bleAdvertising -> "Broadcasting BLE signal"
            else -> "Smart Hub service running"
        }
    }

    private fun notification(text: String): Notification {
        return Notification.Builder(this, CHANNEL_ID)
            .setContentTitle("Atlas Smart Hub")
            .setContentText(text)
            .setSmallIcon(android.R.drawable.stat_sys_data_bluetooth)
            .setOngoing(true)
            .build()
    }

    private fun createNotificationChannel() {
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(CHANNEL_ID, "Smart Hub status", NotificationManager.IMPORTANCE_LOW)
        )
    }

    companion object {
        const val ACTION_STOP = "com.techybugs.gymatlas.smarthub.STOP"
        private const val CHANNEL_ID = "atlas_smart_hub_status"
        private const val NOTIFICATION_ID = 4101
    }
}

object BuildInfo {
    const val firmwareVersion = "atlas-smart-hub-android-0.1.0"
}
