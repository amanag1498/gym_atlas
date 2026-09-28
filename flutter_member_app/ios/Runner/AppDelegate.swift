import CoreBluetooth
import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {
  private var smartAttendanceScanner: SmartAttendanceBleScanner?

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    GeneratedPluginRegistrant.register(with: self)

    guard let registrar = self.registrar(forPlugin: "SmartAttendanceBleScanner") else {
      return false
    }
    let scanner = SmartAttendanceBleScanner()
    smartAttendanceScanner = scanner
    FlutterMethodChannel(
      name: "com.techybugs.gymatlas.member/smart_attendance_ble",
      binaryMessenger: registrar.messenger()
    ).setMethodCallHandler { call, result in
      switch call.method {
      case "startForegroundScan":
        scanner.start(result: result, background: false)
      case "startBackgroundScan":
        scanner.start(result: result, background: true)
      case "stopScan":
        scanner.stop(result: result)
      default:
        result(FlutterMethodNotImplemented)
      }
    }
    FlutterEventChannel(
      name: "com.techybugs.gymatlas.member/smart_attendance_ble_events",
      binaryMessenger: registrar.messenger()
    ).setStreamHandler(scanner)

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}

private final class SmartAttendanceBleScanner: NSObject, FlutterStreamHandler, CBCentralManagerDelegate {
  private let serviceUuid = CBUUID(string: "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1")
  private var eventSink: FlutterEventSink?
  private lazy var centralManager = CBCentralManager(
    delegate: self,
    queue: nil,
    options: [CBCentralManagerOptionRestoreIdentifierKey: "com.techybugs.gymatlas.member.smart-attendance"]
  )
  private var pendingStartResult: FlutterResult?
  private var wantsScanning = false
  private var backgroundMode = false
  private var pendingEvents: [[String: Any]] = []
  private var backgroundTask: UIBackgroundTaskIdentifier = .invalid
  private let serviceDataCachePrefix = "smartAttendance.serviceData."

  func onListen(withArguments arguments: Any?, eventSink events: @escaping FlutterEventSink) -> FlutterError? {
    eventSink = events
    for event in pendingEvents {
      events(event)
    }
    pendingEvents.removeAll()
    return nil
  }

  func onCancel(withArguments arguments: Any?) -> FlutterError? {
    eventSink = nil
    return nil
  }

  func start(result: @escaping FlutterResult, background: Bool) {
    wantsScanning = true
    backgroundMode = background
    if centralManager.state == .poweredOn {
      restartScan()
      if background {
        beginBackgroundProcessingWindow()
      } else {
        endBackgroundProcessingWindow()
      }
      result(nil)
      return
    }

    if centralManager.state == .unknown || centralManager.state == .resetting {
      pendingStartResult = result
      _ = centralManager
      return
    }

    result(FlutterError(code: "bluetooth_unavailable", message: stateMessage(centralManager.state), details: nil))
  }

  func stop(result: FlutterResult?) {
    wantsScanning = false
    backgroundMode = false
    centralManager.stopScan()
    endBackgroundProcessingWindow()
    pendingStartResult = nil
    result?(nil)
  }

  func centralManagerDidUpdateState(_ central: CBCentralManager) {
    guard wantsScanning else { return }
    if central.state == .poweredOn {
      restartScan()
      pendingStartResult?(nil)
      pendingStartResult = nil
    } else if central.state != .unknown && central.state != .resetting {
      pendingStartResult?(FlutterError(code: "bluetooth_unavailable", message: stateMessage(central.state), details: nil))
      pendingStartResult = nil
    }
  }

  func centralManager(_ central: CBCentralManager, willRestoreState dict: [String: Any]) {
    wantsScanning = true
    backgroundMode = true
    if central.state == .poweredOn {
      restartScan()
    }
  }

  func centralManager(
    _ central: CBCentralManager,
    didDiscover peripheral: CBPeripheral,
    advertisementData: [String: Any],
    rssi RSSI: NSNumber
  ) {
    let serviceData = advertisementData[CBAdvertisementDataServiceDataKey] as? [CBUUID: Data]
    let peripheralCacheKey = serviceDataCachePrefix + peripheral.identifier.uuidString
    let receivedServiceData = serviceData?[serviceUuid]
    if let receivedServiceData {
      UserDefaults.standard.set(receivedServiceData, forKey: peripheralCacheKey)
    }
    let resolvedServiceData = receivedServiceData
      ?? UserDefaults.standard.data(forKey: peripheralCacheKey)
    let usedCachedServiceData = receivedServiceData == nil && resolvedServiceData != nil
    let source = backgroundMode
      ? (usedCachedServiceData ? "ios_background_ble_cached" : "ios_background_ble")
      : "ios_foreground_ble"
    var event: [String: Any] = [
      "serviceUuid": serviceUuid.uuidString.lowercased(),
      "rssi": RSSI.intValue,
      "detectedAt": Int(Date().timeIntervalSince1970 * 1000),
      "source": source,
    ]
    event["serviceData"] = resolvedServiceData?.map { Int($0) } ?? NSNull()
    NSLog(
      "[SmartAttendance] discovered peripheral=%@ mode=%@ rssi=%d serviceData=%@ cached=%@",
      peripheral.identifier.uuidString,
      backgroundMode ? "background" : "foreground",
      RSSI.intValue,
      receivedServiceData == nil ? "missing" : "present",
      usedCachedServiceData ? "yes" : "no"
    )

    if backgroundMode || UIApplication.shared.applicationState != .active {
      beginBackgroundProcessingWindow()
    }
    if let eventSink {
      eventSink(event)
    } else {
      pendingEvents.append(event)
      if pendingEvents.count > 8 {
        pendingEvents.removeFirst(pendingEvents.count - 8)
      }
    }
  }

  private func restartScan() {
    if centralManager.isScanning {
      centralManager.stopScan()
    }
    centralManager.scanForPeripherals(
      withServices: [serviceUuid],
      options: [CBCentralManagerScanOptionAllowDuplicatesKey: !backgroundMode]
    )
    NSLog(
      "[SmartAttendance] scan restarted mode=%@ appState=%ld",
      backgroundMode ? "background" : "foreground",
      UIApplication.shared.applicationState.rawValue
    )
  }

  private func beginBackgroundProcessingWindow() {
    endBackgroundProcessingWindow()
    let task = UIApplication.shared.beginBackgroundTask(
      withName: "GymAtlasSmartAttendance"
    ) { [weak self] in
      self?.endBackgroundProcessingWindow()
    }
    backgroundTask = task
    guard task != .invalid else { return }
    DispatchQueue.main.asyncAfter(deadline: .now() + 20) { [weak self] in
      guard self?.backgroundTask == task else { return }
      self?.endBackgroundProcessingWindow()
    }
  }

  private func endBackgroundProcessingWindow() {
    guard backgroundTask != .invalid else { return }
    UIApplication.shared.endBackgroundTask(backgroundTask)
    backgroundTask = .invalid
  }

  private func stateMessage(_ state: CBManagerState) -> String {
    switch state {
    case .poweredOff:
      return "Bluetooth is turned off."
    case .unauthorized:
      return "Bluetooth permission is required."
    case .unsupported:
      return "Bluetooth LE scanning is not supported on this device."
    default:
      return "Bluetooth is unavailable."
    }
  }
}
