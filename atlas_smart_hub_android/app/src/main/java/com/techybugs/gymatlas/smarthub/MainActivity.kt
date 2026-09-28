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
import android.graphics.drawable.GradientDrawable
import android.provider.Settings
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.net.Uri
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.view.WindowManager
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
    private lateinit var provisionButton: Button
    private lateinit var actionText: TextView
    private lateinit var statusText: TextView
    private lateinit var gymText: TextView
    private lateinit var branchText: TextView
    private lateinit var publicIdText: TextView
    private lateinit var bleText: TextView
    private lateinit var backendText: TextView
    private lateinit var heartbeatText: TextView
    private lateinit var statusUpdatedText: TextView
    private lateinit var batteryText: TextView
    private lateinit var errorText: TextView
    private lateinit var bluetoothCheckText: TextView
    private lateinit var permissionCheckText: TextView
    private lateinit var batteryCheckText: TextView
    private var startAfterPermission = false
    private var activationInProgress = false

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
        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_STATE_ALWAYS_HIDDEN)
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
        renderSavedState()
        renderDeviceChecks()
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
        window.statusBarColor = Color.rgb(5, 12, 25)
        window.navigationBarColor = Color.rgb(5, 12, 25)
        val scroll = ScrollView(this).apply { setBackgroundColor(Color.rgb(5, 12, 25)) }
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            isFocusableInTouchMode = true
            requestFocus()
            setPadding(dp(20), dp(24), dp(20), dp(28))
        }
        scroll.addView(root, ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))

        root.addView(label("GYM ATLAS  •  SMART ATTENDANCE", 12, Color.rgb(129, 140, 248), true))
        root.addView(label("Entrance Hub", 32, Color.WHITE, true).apply { setPadding(0, dp(7), 0, 0) })
        root.addView(label("A clear view of the entrance signal, backend connection, and the exact action needed when something stops.", 15, Color.rgb(177, 189, 211), false).apply { setPadding(0, dp(9), 0, dp(18)) })

        val statusCard = card()
        statusText = label("Not running", 20, Color.WHITE, true).apply {
            setPadding(dp(13), dp(9), dp(13), dp(9))
            gravity = Gravity.CENTER
        }
        statusCard.addView(label("LIVE HUB STATUS", 11, Color.rgb(129, 140, 248), true))
        gymText = valueLine("Gym", "Not activated")
        branchText = valueLine("Branch", "Not activated")
        publicIdText = valueLine("Hub public ID", "Not activated")
        bleText = valueLine("BLE broadcasting", "Off")
        backendText = valueLine("Backend connectivity", "Not checked")
        heartbeatText = valueLine("Last heartbeat", "Never")
        statusUpdatedText = valueLine("Status updated", "Never")
        batteryText = valueLine("Entrance phone battery", "Checking")
        errorText = label("", 13, Color.rgb(252, 165, 165), false)
        statusCard.addView(statusText)
        statusCard.addView(gymText)
        statusCard.addView(branchText)
        statusCard.addView(publicIdText)
        statusCard.addView(bleText)
        statusCard.addView(backendText)
        statusCard.addView(heartbeatText)
        statusCard.addView(statusUpdatedText)
        statusCard.addView(batteryText)
        statusCard.addView(errorText)
        statusCard.addView(secondaryButton("Refresh live status") { refreshStatus() })
        root.addView(statusCard)

        val formCard = card().apply { setPadding(dp(16), dp(16), dp(16), dp(18)) }
        formCard.addView(label("One-time setup", 19, Color.WHITE, true))
        formCard.addView(label("Copy both credentials from Gym Admin → Attendance → Smart Attendance. Activation saves them securely on this phone.", 13, Color.rgb(177, 189, 211), false).apply { setPadding(0, dp(5), 0, dp(12)) })
        baseUrlInput = input("https://gymatlas.in", false).apply { setText(HubContracts.DEFAULT_BASE_URL) }
        uuidInput = input("Paste Hub UUID", false)
        secretInput = input("Paste one-time Device secret", true)
        formCard.addView(field("Backend URL", baseUrlInput))
        formCard.addView(field("Hub UUID", uuidInput))
        formCard.addView(field("Device secret", secretInput))

        provisionButton = primaryButton("Activate and start hub") { provisionAndStart() }
        actionText = label("Ready to activate.", 13, Color.rgb(148, 163, 184), false).apply {
            setPadding(0, dp(10), 0, 0)
        }
        val startButton = secondaryButton("Start saved hub") { startService() }
        val stopButton = secondaryButton("Stop entrance signal") { stopService() }
        val clearButton = dangerButton("Clear saved credentials") { clearCredentials() }
        formCard.addView(provisionButton)
        formCard.addView(actionText)
        formCard.addView(startButton)
        formCard.addView(stopButton)
        formCard.addView(clearButton)
        root.addView(formCard)

        val setupCard = card()
        setupCard.addView(label("Phone readiness", 19, Color.WHITE, true))
        setupCard.addView(label("All three checks should be ready for reliable all-day entrance use.", 13, Color.rgb(177, 189, 211), false).apply { setPadding(0, dp(4), 0, dp(5)) })
        bluetoothCheckText = valueLine("Bluetooth", bluetoothState())
        permissionCheckText = valueLine("Nearby devices", permissionState())
        batteryCheckText = valueLine("Battery background access", batteryOptimizationState())
        setupCard.addView(bluetoothCheckText)
        setupCard.addView(permissionCheckText)
        setupCard.addView(batteryCheckText)
        setupCard.addView(valueLine("BLE advertiser", if (packageManager.hasSystemFeature(PackageManager.FEATURE_BLUETOOTH_LE)) "Available" else "Missing"))
        setupCard.addView(valueLine("Heartbeat interval", "${HubContracts.HEARTBEAT_INTERVAL_SECONDS} seconds"))
        setupCard.addView(valueLine("BLE protocol", "v${HubContracts.PROTOCOL_VERSION}"))
        setupCard.addView(valueLine("Service UUID", HubContracts.ATLAS_BLE_SERVICE_UUID).apply { setOnLongClickListener { copy(HubContracts.ATLAS_BLE_SERVICE_UUID); true } })
        setupCard.addView(secondaryButton("Open Bluetooth settings") { startActivity(Intent(Settings.ACTION_BLUETOOTH_SETTINGS)) })
        setupCard.addView(secondaryButton("Review battery restrictions") { openBatterySettings() })
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
            renderStatus(store.loadRuntimeStatus() ?:
                HubRuntimeStatus(
                    provisioned = true,
                    publicId = saved.publicId,
                    gymName = saved.gymName,
                    branchName = saved.branchName,
                )
            )
        }
    }

    private fun refreshStatus() {
        val saved = store.load()
        if (saved == null) {
            setActivationState(false, "Activate this phone before refreshing hub status.", true)
            return
        }
        renderDeviceChecks()
        if (store.shouldRun()) {
            startForegroundService(
                Intent(this, HubForegroundService::class.java)
                    .setAction(HubForegroundService.ACTION_REFRESH),
            )
            setActivationState(false, "Checking Bluetooth, saved configuration, and backend connection…", false)
        } else {
            setActivationState(false, "The hub is stopped. Tap Start saved hub to broadcast again.", true)
        }
    }

    private fun provisionAndStart() {
        if (activationInProgress) return
        baseUrlInput.error = null
        uuidInput.error = null
        secretInput.error = null
        val baseUrl = baseUrlInput.text.toString().trim().ifBlank { HubContracts.DEFAULT_BASE_URL }.trimEnd('/')
        val parsedBaseUrl = Uri.parse(baseUrl)
        if (parsedBaseUrl.scheme != "https" || parsedBaseUrl.host.isNullOrBlank()) {
            baseUrlInput.error = "Enter a valid HTTPS URL"
            setActivationState(false, "Check the backend URL and try again.", true)
            return
        }
        val credentials = HubCredentials(
            baseUrl = baseUrl,
            hubUuid = uuidInput.text.toString().trim(),
            deviceSecret = secretInput.text.toString().trim(),
        )
        if (credentials.hubUuid.isBlank()) {
            uuidInput.error = "Hub UUID is required"
        }
        if (credentials.deviceSecret.isBlank()) {
            secretInput.error = "Device secret is required"
        }
        if (credentials.hubUuid.isBlank() || credentials.deviceSecret.isBlank()) {
            setActivationState(false, "Enter the Hub UUID and Device secret from Gym Admin.", true)
            return
        }
        setActivationState(true, "Contacting Gym Atlas and validating this hub…", false)
        worker.execute {
            runCatching {
                val activated = backend.activate(credentials, BuildInfo.firmwareVersion)
                store.save(activated)
                main.post {
                    setActivationState(false, "Hub activated. Starting Bluetooth broadcast…", false)
                    loadSavedCredentials()
                    renderStatus(HubRuntimeStatus(provisioned = true, backendConnected = true, publicId = activated.publicId, gymName = activated.gymName, branchName = activated.branchName))
                    startService()
                }
            }.onFailure { error ->
                main.post {
                    val message = error.message ?: "Activation failed. Check the details and try again."
                    setActivationState(false, message, true)
                    renderStatus(HubRuntimeStatus(provisioned = store.load() != null, lastError = message))
                }
            }
        }
    }

    private fun startService() {
        val saved = store.load()
        if (saved == null) {
            setActivationState(false, "Activate this phone before starting the hub.", true)
            renderStatus(HubRuntimeStatus(lastError = "Provision the hub before starting."))
            return
        }
        val missingPermissions = missingBluetoothPermissions()
        if (missingPermissions.isNotEmpty()) {
            startAfterPermission = true
            setActivationState(false, "Allow Nearby devices so this phone can broadcast the hub signal.", false)
            requestPermissions((missingPermissions + missingNotificationPermission()).toTypedArray(), HUB_PERMISSION_REQUEST)
            return
        }
        launchHubService()
    }

    private fun launchHubService() {
        val saved = store.load() ?: return
        store.setShouldRun(true)
        startForegroundService(Intent(this, HubForegroundService::class.java))
        setActivationState(false, "Hub service started. Waiting for Bluetooth broadcast status…", false)
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
            status.serviceRunning && status.bleAdvertising -> "Hub broadcasting offline"
            status.serviceRunning -> "Service running"
            status.provisioned -> "Provisioned"
            else -> "Not provisioned"
        }
        statusText.background = roundedBackground(
            when {
                status.bleAdvertising && status.backendConnected -> Color.rgb(5, 150, 105)
                status.bleAdvertising -> Color.rgb(2, 132, 199)
                status.serviceRunning -> Color.rgb(217, 119, 6)
                else -> Color.rgb(51, 65, 85)
            },
            radius = 18,
        )
        gymText.text = "Gym\n${status.gymName ?: "Not activated"}"
        branchText.text = "Branch\n${status.branchName ?: "Gym-wide or not activated"}"
        publicIdText.text = "Hub public ID\n${status.publicId ?: "Not activated"}"
        bleText.text = "Entrance BLE signal\n${if (status.bleAdvertising) "Active${status.advertisingMode?.let { " · $it" }.orEmpty()}" else "Off"}"
        backendText.text = "Backend connectivity\n${when {
            status.backendConnected -> "Connected"
            status.bleAdvertising -> "Offline — BLE continues"
            else -> "Not connected"
        }}"
        heartbeatText.text = "Last heartbeat\n${status.lastHeartbeatAt ?: "Never"}"
        statusUpdatedText.text = "Status updated\n${status.lastStatusAt ?: "Never"}"
        batteryText.text = "Entrance phone battery\n${status.batteryPercent?.let { "$it%" } ?: "Unknown"}"
        errorText.text = status.lastError?.let { "\n$it" } ?: ""
        publicIdText.setOnClickListener {
            status.publicId?.let {
                copy(it)
                setActivationState(false, "Public ID copied.", false)
            }
        }
        if (!activationInProgress) {
            when {
                status.lastError != null -> setActivationState(false, status.lastError, true)
                status.bleAdvertising && status.backendConnected -> setActivationState(false, "Active: Bluetooth broadcasting and backend connected.", false)
                status.bleAdvertising -> setActivationState(false, "Active offline: Bluetooth broadcasting continues.", false)
            }
        }
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
        if (requestCode != HUB_PERMISSION_REQUEST) return
        renderDeviceChecks()
        if (!startAfterPermission) return
        startAfterPermission = false
        if (missingBluetoothPermissions().isEmpty()) {
            launchHubService()
        } else {
            setActivationState(false, "Bluetooth access was not granted. Allow Nearby devices in Android Settings, then tap Start saved hub.", true)
            renderStatus(HubRuntimeStatus(provisioned = store.load() != null, lastError = "Bluetooth permissions are required to keep the hub running."))
        }
    }

    private fun setActivationState(inProgress: Boolean, message: String, isError: Boolean) {
        activationInProgress = inProgress
        provisionButton.isEnabled = !inProgress
        provisionButton.text = if (inProgress) "Activating…" else "Activate and start hub"
        actionText.text = message
        actionText.setTextColor(
            when {
                isError -> Color.rgb(252, 165, 165)
                inProgress -> Color.rgb(147, 197, 253)
                message.startsWith("Active") || message.startsWith("Hub activated") -> Color.rgb(110, 231, 183)
                else -> Color.rgb(202, 211, 226)
            }
        )
    }

    private fun bluetoothState(): String {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED
        ) return "Permission required"
        val adapter = getSystemService(BluetoothManager::class.java)?.adapter ?: return "Unavailable"
        return if (adapter.isEnabled) "On" else "Off"
    }

    private fun permissionState(): String =
        if (missingBluetoothPermissions().isEmpty()) "Allowed" else "Permission required"

    private fun batteryOptimizationState(): String {
        val power = getSystemService(android.os.PowerManager::class.java)
        return if (power?.isIgnoringBatteryOptimizations(packageName) == true) "Unrestricted" else "Restricted — review recommended"
    }

    private fun renderDeviceChecks() {
        if (!::bluetoothCheckText.isInitialized) return
        bluetoothCheckText.text = "Bluetooth\n${bluetoothState()}"
        permissionCheckText.text = "Nearby devices\n${permissionState()}"
        batteryCheckText.text = "Battery background access\n${batteryOptimizationState()}"
    }

    private fun openBatterySettings() {
        val intent = Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:$packageName"))
        startActivity(intent)
    }

    private fun label(text: String, size: Int, color: Int, bold: Boolean): TextView = TextView(this).apply {
        this.text = text
        textSize = size.toFloat()
        setTextColor(color)
        if (bold) typeface = Typeface.DEFAULT_BOLD
        setLineSpacing(dp(2).toFloat(), 1.0f)
    }

    private fun valueLine(title: String, value: String): TextView = label("$title\n$value", 14, Color.rgb(226, 232, 240), false).apply {
        setPadding(0, dp(11), 0, dp(2))
    }

    private fun card(): LinearLayout = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        setPadding(dp(16), dp(16), dp(16), dp(16))
        background = roundedBackground(Color.rgb(14, 25, 45), stroke = Color.rgb(38, 54, 82), radius = 24)
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
        setPadding(dp(14), dp(12), dp(14), dp(12))
        background = roundedBackground(Color.rgb(8, 18, 34), stroke = Color.rgb(51, 65, 85), radius = 14)
    }

    private fun field(title: String, input: EditText): LinearLayout = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        setPadding(0, dp(8), 0, 0)
        addView(label(title, 12, Color.rgb(148, 163, 184), true).apply { setPadding(dp(2), 0, 0, dp(6)) })
        addView(input, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))
    }

    private fun primaryButton(text: String, action: () -> Unit): Button = button(text, Color.rgb(54, 65, 245), Color.WHITE, action)
    private fun secondaryButton(text: String, action: () -> Unit): Button = button(text, Color.rgb(30, 41, 59), Color.WHITE, action)
    private fun dangerButton(text: String, action: () -> Unit): Button = button(text, Color.rgb(127, 29, 29), Color.WHITE, action)

    private fun button(text: String, bg: Int, fg: Int, action: () -> Unit): Button = Button(this).apply {
        this.text = text
        setTextColor(fg)
        background = roundedBackground(bg, radius = 14)
        gravity = Gravity.CENTER
        setOnClickListener { action() }
        val params = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
        params.setMargins(0, dp(10), 0, 0)
        layoutParams = params
    }

    private fun roundedBackground(color: Int, stroke: Int? = null, radius: Int): GradientDrawable = GradientDrawable().apply {
        shape = GradientDrawable.RECTANGLE
        setColor(color)
        cornerRadius = dp(radius).toFloat()
        stroke?.let { setStroke(dp(1), it) }
    }

    private fun copy(value: String) {
        getSystemService(ClipboardManager::class.java).setPrimaryClip(ClipData.newPlainText("Atlas Smart Hub", value))
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    companion object {
        private const val HUB_PERMISSION_REQUEST = 42
    }
}
