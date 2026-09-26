package com.techybugs.gymatlas.smarthub

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.app.AlertDialog
import android.bluetooth.BluetoothManager
import android.content.BroadcastReceiver
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.Typeface
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.net.Uri
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import java.util.concurrent.Executors

class MainActivity : Activity() {
    private lateinit var store: SecureCredentialStore
    private val backend = HubBackendClient()
    private val worker = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())

    private lateinit var baseUrlInput: EditText
    private lateinit var uuidInput: EditText
    private lateinit var secretInput: EditText
    private lateinit var statusText: TextView
    private lateinit var gymText: TextView
    private lateinit var branchText: TextView
    private lateinit var publicIdText: TextView
    private lateinit var bleText: TextView
    private lateinit var backendText: TextView
    private lateinit var heartbeatText: TextView
    private lateinit var errorText: TextView
    private var startAfterPermission = false

    private val statusReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            val status = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                intent?.getParcelableExtra(HubContracts.EXTRA_STATUS, HubRuntimeStatus::class.java)
            } else {
                @Suppress("DEPRECATION")
                intent?.getParcelableExtra(HubContracts.EXTRA_STATUS)
            }
            if (status != null) renderStatus(status)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        store = SecureCredentialStore(this)
        setContentView(buildContent())
        loadSavedCredentials()
        requestRuntimePermissions()
        renderSavedState()
    }

    @SuppressLint("UnspecifiedRegisterReceiverFlag")
    override fun onResume() {
        super.onResume()
        val filter = IntentFilter(HubContracts.ACTION_STATUS_CHANGED)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(statusReceiver, filter, RECEIVER_NOT_EXPORTED)
        } else {
            @Suppress("DEPRECATION")
            registerReceiver(statusReceiver, filter)
        }
    }

    override fun onPause() {
        runCatching { unregisterReceiver(statusReceiver) }
        super.onPause()
    }

    override fun onDestroy() {
        worker.shutdownNow()
        super.onDestroy()
    }

    private fun buildContent(): View {
        val scroll = ScrollView(this).apply { setBackgroundColor(Color.rgb(7, 17, 31)) }
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(20), dp(24), dp(20), dp(28))
        }
        scroll.addView(root, ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))

        root.addView(label("Gym Atlas", 13, Color.rgb(142, 154, 180), true))
        root.addView(label("Atlas Smart Hub", 30, Color.WHITE, true).apply { setPadding(0, dp(4), 0, 0) })
        root.addView(label("Provision this entrance device, broadcast the Smart Attendance BLE signal, and keep backend heartbeat alive.", 15, Color.rgb(202, 211, 226), false).apply { setPadding(0, dp(10), 0, dp(18)) })

        val statusCard = card()
        statusText = label("Not running", 18, Color.WHITE, true)
        gymText = valueLine("Gym", "Not activated")
        branchText = valueLine("Branch", "Not activated")
        publicIdText = valueLine("Hub public ID", "Not activated")
        bleText = valueLine("BLE broadcasting", "Off")
        backendText = valueLine("Backend connectivity", "Not checked")
        heartbeatText = valueLine("Last heartbeat", "Never")
        errorText = label("", 13, Color.rgb(252, 165, 165), false)
        statusCard.addView(statusText)
        statusCard.addView(gymText)
        statusCard.addView(branchText)
        statusCard.addView(publicIdText)
        statusCard.addView(bleText)
        statusCard.addView(backendText)
        statusCard.addView(heartbeatText)
        statusCard.addView(errorText)
        root.addView(statusCard)

        val formCard = card().apply { setPadding(dp(16), dp(16), dp(16), dp(18)) }
        formCard.addView(label("Provision hub", 18, Color.WHITE, true))
        formCard.addView(label("Use the UUID and one-time secret from Gym Admin → Smart Attendance.", 13, Color.rgb(202, 211, 226), false).apply { setPadding(0, dp(4), 0, dp(10)) })
        baseUrlInput = input("Backend base URL", false).apply { setText(HubContracts.DEFAULT_BASE_URL) }
        uuidInput = input("Hub UUID", false)
        secretInput = input("Device secret", true)
        formCard.addView(baseUrlInput)
        formCard.addView(uuidInput)
        formCard.addView(secretInput)

        val provisionButton = primaryButton("Activate and start hub") { provisionAndStart() }
        val startButton = secondaryButton("Start saved hub") { startService() }
        val stopButton = secondaryButton("Stop broadcasting") { stopService() }
        val clearButton = dangerButton("Clear saved credentials") { clearCredentials() }
        formCard.addView(provisionButton)
        formCard.addView(startButton)
        formCard.addView(stopButton)
        formCard.addView(clearButton)
        root.addView(formCard)

        val setupCard = card()
        setupCard.addView(label("Device checks", 18, Color.WHITE, true))
        setupCard.addView(valueLine("Bluetooth", bluetoothState()))
        setupCard.addView(valueLine("BLE advertiser", if (packageManager.hasSystemFeature(PackageManager.FEATURE_BLUETOOTH_LE)) "Available" else "Missing"))
        setupCard.addView(valueLine("Heartbeat interval", "${HubContracts.HEARTBEAT_INTERVAL_SECONDS} seconds"))
        setupCard.addView(valueLine("BLE protocol", "v${HubContracts.PROTOCOL_VERSION}"))
        setupCard.addView(valueLine("Service UUID", HubContracts.ATLAS_BLE_SERVICE_UUID).apply { setOnLongClickListener { copy(HubContracts.ATLAS_BLE_SERVICE_UUID); true } })
        root.addView(setupCard)

        return scroll
    }

    private fun loadSavedCredentials() {
        val saved = store.load() ?: return
        baseUrlInput.setText(saved.baseUrl)
        uuidInput.setText(saved.hubUuid)
        secretInput.setText(saved.deviceSecret)
    }

    private fun renderSavedState() {
        val saved = store.load()
        if (saved == null) {
            renderStatus(HubRuntimeStatus())
        } else {
            renderStatus(
                HubRuntimeStatus(
                    provisioned = true,
                    publicId = saved.publicId,
                    gymName = saved.gymName,
                    branchName = saved.branchName,
                )
            )
        }
    }

    private fun provisionAndStart() {
        val baseUrl = baseUrlInput.text.toString().trim().ifBlank { HubContracts.DEFAULT_BASE_URL }.trimEnd('/')
        val parsedBaseUrl = Uri.parse(baseUrl)
        if (parsedBaseUrl.scheme != "https" || parsedBaseUrl.host.isNullOrBlank()) {
            renderStatus(HubRuntimeStatus(lastError = "Use a valid HTTPS backend URL."))
            return
        }
        val credentials = HubCredentials(
            baseUrl = baseUrl,
            hubUuid = uuidInput.text.toString().trim(),
            deviceSecret = secretInput.text.toString().trim(),
        )
        if (credentials.hubUuid.isBlank() || credentials.deviceSecret.isBlank()) {
            renderStatus(HubRuntimeStatus(lastError = "Hub UUID and device secret are required."))
            return
        }
        renderStatus(HubRuntimeStatus(provisioned = true, lastError = "Activating with backend..."))
        worker.execute {
            runCatching {
                val activated = backend.activate(credentials, BuildInfo.firmwareVersion)
                store.save(activated)
                main.post {
                    loadSavedCredentials()
                    renderStatus(HubRuntimeStatus(provisioned = true, backendConnected = true, publicId = activated.publicId, gymName = activated.gymName, branchName = activated.branchName))
                    startService()
                }
            }.onFailure { error ->
                main.post { renderStatus(HubRuntimeStatus(provisioned = true, lastError = error.message ?: "Activation failed.")) }
            }
        }
    }

    private fun startService() {
        val saved = store.load()
        if (saved == null) {
            renderStatus(HubRuntimeStatus(lastError = "Provision the hub before starting."))
            return
        }
        val missingPermissions = missingBluetoothPermissions()
        if (missingPermissions.isNotEmpty()) {
            startAfterPermission = true
            requestPermissions((missingPermissions + missingNotificationPermission()).toTypedArray(), HUB_PERMISSION_REQUEST)
            return
        }
        launchHubService()
    }

    private fun launchHubService() {
        val saved = store.load() ?: return
        store.setShouldRun(true)
        startForegroundService(Intent(this, HubForegroundService::class.java))
        renderStatus(HubRuntimeStatus(provisioned = true, serviceRunning = true, publicId = saved.publicId, gymName = saved.gymName, branchName = saved.branchName))
    }

    private fun stopService() {
        store.setShouldRun(false)
        startService(Intent(this, HubForegroundService::class.java).setAction(HubForegroundService.ACTION_STOP))
        val saved = store.load()
        renderStatus(HubRuntimeStatus(provisioned = saved != null, publicId = saved?.publicId, gymName = saved?.gymName, branchName = saved?.branchName))
    }

    private fun clearCredentials() {
        AlertDialog.Builder(this)
            .setTitle("Clear hub credentials?")
            .setMessage("Broadcasting will stop. You will need the current UUID and device secret, or a newly rotated secret, to provision this phone again.")
            .setNegativeButton("Cancel", null)
            .setPositiveButton("Clear") { _, _ ->
                stopService()
                store.clear()
                uuidInput.setText("")
                secretInput.setText("")
                renderStatus(HubRuntimeStatus(lastError = "Saved credentials cleared."))
            }
            .show()
    }

    private fun renderStatus(status: HubRuntimeStatus) {
        statusText.text = when {
            status.serviceRunning && status.bleAdvertising && status.backendConnected -> "Hub online"
            status.serviceRunning && status.bleAdvertising -> "Broadcasting, backend pending"
            status.serviceRunning -> "Service running"
            status.provisioned -> "Provisioned"
            else -> "Not provisioned"
        }
        gymText.text = "Gym\n${status.gymName ?: "Not activated"}"
        branchText.text = "Branch\n${status.branchName ?: "Gym-wide or not activated"}"
        publicIdText.text = "Hub public ID\n${status.publicId ?: "Not activated"}"
        bleText.text = "BLE broadcasting\n${if (status.bleAdvertising) "On" else "Off"}"
        backendText.text = "Backend connectivity\n${if (status.backendConnected) "Connected" else "Not connected"}"
        heartbeatText.text = "Last heartbeat\n${status.lastHeartbeatAt ?: "Never"}"
        errorText.text = status.lastError?.let { "\n$it" } ?: ""
    }

    private fun requestRuntimePermissions() {
        val permissions = missingBluetoothPermissions() + missingNotificationPermission()
        if (permissions.isNotEmpty()) requestPermissions(permissions.toTypedArray(), HUB_PERMISSION_REQUEST)
    }

    private fun missingBluetoothPermissions(): List<String> = buildList {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            if (checkSelfPermission(Manifest.permission.BLUETOOTH_ADVERTISE) != PackageManager.PERMISSION_GRANTED) {
                add(Manifest.permission.BLUETOOTH_ADVERTISE)
            }
            if (checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) {
                add(Manifest.permission.BLUETOOTH_CONNECT)
            }
        }
    }

    private fun missingNotificationPermission(): List<String> = buildList {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            add(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode != HUB_PERMISSION_REQUEST || !startAfterPermission) return
        startAfterPermission = false
        if (missingBluetoothPermissions().isEmpty()) {
            launchHubService()
        } else {
            renderStatus(HubRuntimeStatus(provisioned = store.load() != null, lastError = "Bluetooth permissions are required to keep the hub running."))
        }
    }

    private fun bluetoothState(): String {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED
        ) return "Permission required"
        val adapter = getSystemService(BluetoothManager::class.java)?.adapter ?: return "Unavailable"
        return if (adapter.isEnabled) "On" else "Off"
    }

    private fun label(text: String, size: Int, color: Int, bold: Boolean): TextView = TextView(this).apply {
        this.text = text
        textSize = size.toFloat()
        setTextColor(color)
        if (bold) typeface = Typeface.DEFAULT_BOLD
        setLineSpacing(dp(2).toFloat(), 1.0f)
    }

    private fun valueLine(title: String, value: String): TextView = label("$title\n$value", 14, Color.rgb(226, 232, 240), false).apply {
        setPadding(0, dp(10), 0, 0)
    }

    private fun card(): LinearLayout = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        setPadding(dp(16), dp(16), dp(16), dp(16))
        setBackgroundColor(Color.rgb(16, 28, 48))
        val params = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
        params.setMargins(0, 0, 0, dp(16))
        layoutParams = params
    }

    private fun input(hint: String, secret: Boolean): EditText = EditText(this).apply {
        this.hint = hint
        setHintTextColor(Color.rgb(148, 163, 184))
        setTextColor(Color.WHITE)
        setSingleLine(true)
        inputType = if (secret) InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD else InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
        setPadding(dp(12), dp(10), dp(12), dp(10))
    }

    private fun primaryButton(text: String, action: () -> Unit): Button = button(text, Color.rgb(54, 65, 245), Color.WHITE, action)
    private fun secondaryButton(text: String, action: () -> Unit): Button = button(text, Color.rgb(30, 41, 59), Color.WHITE, action)
    private fun dangerButton(text: String, action: () -> Unit): Button = button(text, Color.rgb(127, 29, 29), Color.WHITE, action)

    private fun button(text: String, bg: Int, fg: Int, action: () -> Unit): Button = Button(this).apply {
        this.text = text
        setTextColor(fg)
        setBackgroundColor(bg)
        gravity = Gravity.CENTER
        setOnClickListener { action() }
        val params = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
        params.setMargins(0, dp(10), 0, 0)
        layoutParams = params
    }

    private fun copy(value: String) {
        getSystemService(ClipboardManager::class.java).setPrimaryClip(ClipData.newPlainText("Atlas Smart Hub", value))
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    companion object {
        private const val HUB_PERMISSION_REQUEST = 42
    }
}
