import CoreBluetooth
import CoreLocation
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

private final class SmartAttendanceBleScanner: NSObject, FlutterStreamHandler, CBCentralManagerDelegate, CLLocationManagerDelegate {
  private let serviceUuid = CBUUID(string: "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1")
  private let beaconUuid = UUID(uuidString: "8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1")!
  private let beaconMonitorIdentifier = "com.techybugs.gymatlas.member.atlas-hubs"
  private let beaconMonitoringEnabledKey = "smartAttendance.beaconMonitoringEnabled"
  private let lastBeaconHubIdKey = "smartAttendance.lastBeaconHubId"
  private let locationManager = CLLocationManager()
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
  private var rangingGeneration = 0

  override init() {
    super.init()
    locationManager.delegate = self
    if UserDefaults.standard.bool(forKey: beaconMonitoringEnabledKey) {
      DispatchQueue.main.async { [weak self] in
        self?.startBeaconMonitoring()
      }
    }
  }

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
    UserDefaults.standard.set(true, forKey: beaconMonitoringEnabledKey)
    startBeaconMonitoring()
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
    UserDefaults.standard.set(false, forKey: beaconMonitoringEnabledKey)
    stopBeaconMonitoring()
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

  func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
    if UserDefaults.standard.bool(forKey: beaconMonitoringEnabledKey) {
      startBeaconMonitoring()
    }
  }

  func locationManager(_ manager: CLLocationManager, didEnterRegion region: CLRegion) {
    guard region.identifier == beaconMonitorIdentifier else { return }
    NSLog("[SmartAttendance] entered Atlas beacon region")
    beginBackgroundProcessingWindow()
    startBeaconRanging()
  }

  func locationManager(_ manager: CLLocationManager, didExitRegion region: CLRegion) {
    guard region.identifier == beaconMonitorIdentifier else { return }
    NSLog("[SmartAttendance] exited Atlas beacon region")
    beginBackgroundProcessingWindow()
    guard let hubId = UserDefaults.standard.object(forKey: lastBeaconHubIdKey) as? NSNumber else {
      emitDiagnostic("Atlas beacon exit detected, but no prior Hub identity was cached.", source: beaconSource())
      return
    }
    emit([
      "eventType": "beacon_exit",
      "hubId": hubId.int64Value,
      "detectedAt": Int(Date().timeIntervalSince1970 * 1000),
      "source": beaconSource(),
    ])
  }

  func locationManager(_ manager: CLLocationManager, didDetermineState state: CLRegionState, for region: CLRegion) {
    guard region.identifier == beaconMonitorIdentifier, state == .inside else { return }
    NSLog("[SmartAttendance] current state is inside Atlas beacon region")
    beginBackgroundProcessingWindow()
    startBeaconRanging()
  }

  func locationManager(
    _ manager: CLLocationManager,
    didRange beacons: [CLBeacon],
    satisfying beaconConstraint: CLBeaconIdentityConstraint
  ) {
    for beacon in beacons where beacon.rssi != 0 {
      let hubId = (beacon.major.int64Value << 16) | beacon.minor.int64Value
      guard hubId > 0 else { continue }
      UserDefaults.standard.set(NSNumber(value: hubId), forKey: lastBeaconHubIdKey)
      emit([
        "eventType": "beacon_presence",
        "hubId": hubId,
        "rssi": beacon.rssi,
        "detectedAt": Int(Date().timeIntervalSince1970 * 1000),
        "source": beaconSource(),
      ])
    }
  }

  func locationManager(_ manager: CLLocationManager, monitoringDidFailFor region: CLRegion?, withError error: Error) {
    emitDiagnostic("Atlas beacon monitoring failed: \(error.localizedDescription)", source: beaconSource())
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
    emit(event)
  }

  private func startBeaconMonitoring() {
    guard CLLocationManager.isMonitoringAvailable(for: CLBeaconRegion.self) else {
      emitDiagnostic("This iPhone does not support Atlas beacon monitoring.", source: beaconSource())
      return
    }
    guard locationManager.authorizationStatus == .authorizedAlways else {
      emitDiagnostic(
        "Always Location access is required so iOS can wake Gym Atlas for the entrance beacon.",
        source: beaconSource()
      )
      return
    }
    let region = CLBeaconRegion(uuid: beaconUuid, identifier: beaconMonitorIdentifier)
    region.notifyOnEntry = true
    region.notifyOnExit = true
    locationManager.startMonitoring(for: region)
    locationManager.requestState(for: region)
    NSLog("[SmartAttendance] Atlas beacon region monitoring active")
  }

  private func stopBeaconMonitoring() {
    rangingGeneration += 1
    let region = CLBeaconRegion(uuid: beaconUuid, identifier: beaconMonitorIdentifier)
    locationManager.stopRangingBeacons(satisfying: region.beaconIdentityConstraint)
    locationManager.stopMonitoring(for: region)
  }

  private func startBeaconRanging() {
    let region = CLBeaconRegion(uuid: beaconUuid, identifier: beaconMonitorIdentifier)
    rangingGeneration += 1
    let generation = rangingGeneration
    locationManager.startRangingBeacons(satisfying: region.beaconIdentityConstraint)
    DispatchQueue.main.asyncAfter(deadline: .now() + 12) { [weak self] in
      guard let self, self.rangingGeneration == generation else { return }
      self.locationManager.stopRangingBeacons(satisfying: region.beaconIdentityConstraint)
    }
  }

  private func beaconSource() -> String {
    let isBackground = backgroundMode || UIApplication.shared.applicationState != .active
    return isBackground ? "ios_background_beacon" : "ios_foreground_beacon"
  }

  private func emitDiagnostic(_ message: String, source: String) {
    emit([
      "diagnostic": message,
      "detectedAt": Int(Date().timeIntervalSince1970 * 1000),
      "source": source,
    ])
  }

  private func emit(_ event: [String: Any]) {
    if let eventSink {
      eventSink(event)
    } else {
      pendingEvents.append(event)
      if pendingEvents.count > 12 {
        pendingEvents.removeFirst(pendingEvents.count - 12)
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
