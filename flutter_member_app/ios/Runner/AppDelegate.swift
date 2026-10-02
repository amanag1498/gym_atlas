import CoreBluetooth
import CoreLocation
import Flutter
import Security
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
      case "configureBackgroundAttendance":
        scanner.configure(arguments: call.arguments, result: result)
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
  private let restorationEnabledKey = "smartAttendance.bluetoothRestorationEnabled"
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
  private let sessionKey = "smartAttendance.nativeSession"
  private let minimumRssi = -78
  private var requestInFlight = false
  private var lastRequestAttemptAt: Date?
  private var rangingGeneration = 0

  override init() {
    super.init()
    locationManager.delegate = self
    if UserDefaults.standard.bool(forKey: restorationEnabledKey) {
      DispatchQueue.main.async { [weak self] in
        _ = self?.centralManager
      }
    }
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
    UserDefaults.standard.set(true, forKey: restorationEnabledKey)
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

  func configure(arguments: Any?, result: FlutterResult) {
    guard
      let values = arguments as? [String: Any],
      let baseUrl = values["baseUrl"] as? String,
      baseUrl.hasPrefix("https://"),
      let accessToken = values["accessToken"] as? String,
      !accessToken.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
      let gymNumber = values["gymId"] as? NSNumber,
      gymNumber.int64Value > 0
    else {
      result(FlutterError(
        code: "invalid_background_config",
        message: "Valid API, session, and gym details are required.",
        details: nil
      ))
      return
    }

    let config = SmartAttendanceBackgroundConfig(
      baseUrl: baseUrl.trimmingCharacters(in: CharacterSet(charactersIn: "/")),
      accessToken: accessToken,
      gymId: gymNumber.int64Value
    )
    do {
      if let existing = try SmartAttendanceKeychain.load(), existing.gymId != config.gymId {
        clearNativeSession()
      }
      try SmartAttendanceKeychain.save(config)
      result(nil)
    } catch {
      result(FlutterError(
        code: "background_config_failed",
        message: "Could not securely save Smart Attendance configuration.",
        details: error.localizedDescription
      ))
    }
  }

  func stop(result: FlutterResult?) {
    wantsScanning = false
    backgroundMode = false
    centralManager.stopScan()
    UserDefaults.standard.set(false, forKey: restorationEnabledKey)
    UserDefaults.standard.set(false, forKey: beaconMonitoringEnabledKey)
    stopBeaconMonitoring()
    SmartAttendanceKeychain.clear()
    clearNativeSession()
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
    UserDefaults.standard.set(true, forKey: restorationEnabledKey)
    if central.state == .poweredOn && !central.isScanning {
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
    let detectedAt = Date()
    if UIApplication.shared.applicationState != .active {
      submitNativeCheckOut(detectedAt: detectedAt)
    }
    guard let hubId = UserDefaults.standard.object(forKey: lastBeaconHubIdKey) as? NSNumber else {
      emitDiagnostic("Atlas beacon exit detected, but no prior Hub identity was cached.", rssi: nil)
      return
    }
    emit([
      "eventType": "beacon_exit",
      "hubId": hubId.int64Value,
      "detectedAt": Int(detectedAt.timeIntervalSince1970 * 1000),
      "source": UIApplication.shared.applicationState == .active
        ? "ios_foreground_beacon" : "ios_background_native",
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
      let detectedAt = Date()
      UserDefaults.standard.set(NSNumber(value: hubId), forKey: lastBeaconHubIdKey)
      if UIApplication.shared.applicationState != .active {
        recordNativePresence(detectedAt: detectedAt)
        submitNativeCheckIn(
          publicId: nil,
          hubId: hubId,
          protocolVersion: 3,
          rssi: beacon.rssi,
          detectedAt: detectedAt,
          transport: "ios_ibeacon"
        )
      }
      emit([
        "eventType": "beacon_presence",
        "hubId": hubId,
        "rssi": beacon.rssi,
        "detectedAt": Int(detectedAt.timeIntervalSince1970 * 1000),
        "source": UIApplication.shared.applicationState == .active
          ? "ios_foreground_beacon" : "ios_background_native",
      ])
    }
  }

  func locationManager(_ manager: CLLocationManager, monitoringDidFailFor region: CLRegion?, withError error: Error) {
    emitDiagnostic("Atlas beacon monitoring failed: \(error.localizedDescription)", rssi: nil)
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
    if UIApplication.shared.applicationState != .active,
       let payload = parsePayload(resolvedServiceData),
       RSSI.intValue >= minimumRssi {
      submitNativeCheckIn(
        publicId: payload.publicId,
        hubId: nil,
        protocolVersion: payload.protocolVersion,
        rssi: RSSI.intValue,
        detectedAt: Date(),
        transport: "ios_core_bluetooth"
      )
      event["source"] = "ios_background_native"
    }
    emit(event)
  }

  private func submitNativeCheckIn(
    publicId: String?,
    hubId: Int64?,
    protocolVersion: Int,
    rssi: Int,
    detectedAt: Date,
    transport: String
  ) {
    guard !requestInFlight else { return }
    let hubKey = publicId ?? "BEACON_\(hubId ?? 0)"
    if let session = nativeSession(), detectedAt < session.windowEndsAt, session.checkedOutAt == nil {
      recordNativePresence(detectedAt: detectedAt)
      NSLog(
        "[SmartAttendance] native request suppressed existingAttendance=%lld windowEnds=%@",
        session.attendanceLogId,
        session.windowEndsAt.description
      )
      return
    }
    if let lastRequestAttemptAt, detectedAt.timeIntervalSince(lastRequestAttemptAt) < 15 {
      return
    }
    guard let config = try? SmartAttendanceKeychain.load() else {
      emitDiagnostic("Hub detected in the background, but native attendance is not configured.", rssi: rssi)
      return
    }
    guard let url = URL(string: config.baseUrl + "/member/attendance/smart-check-in") else {
      emitDiagnostic("Smart Attendance API address is invalid.", rssi: rssi)
      return
    }

    lastRequestAttemptAt = detectedAt
    requestInFlight = true
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.timeoutInterval = 12
    request.setValue("application/json", forHTTPHeaderField: "Accept")
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.setValue("Bearer \(config.accessToken)", forHTTPHeaderField: "Authorization")
    request.setValue(String(config.gymId), forHTTPHeaderField: "X-Gym-Id")
    request.setValue("member", forHTTPHeaderField: "X-Atlas-App")
    request.setValue("ios", forHTTPHeaderField: "X-Client-Platform")
    addAppVersionHeaders(to: &request)
    var body: [String: Any] = [
      "protocol_version": protocolVersion,
      "rssi": rssi,
      "detected_at": ISO8601DateFormatter().string(from: detectedAt),
      "source": transport == "ios_ibeacon"
        ? "ios_background_beacon_native" : "ios_background_native",
      "metadata": ["transport": transport],
    ]
    if let publicId { body["hub_public_id"] = publicId }
    if let hubId { body["hub_id"] = hubId }
    request.httpBody = try? JSONSerialization.data(withJSONObject: body)

    beginBackgroundProcessingWindow()
    URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
      DispatchQueue.main.async {
        guard let self else { return }
        self.requestInFlight = false
        defer { self.endBackgroundProcessingWindow() }
        if let error {
          NSLog("[SmartAttendance] native check-in failed error=%@", error.localizedDescription)
          self.emitDiagnostic("Native background check-in failed: \(error.localizedDescription)", rssi: rssi)
          return
        }
        let status = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(status), let data else {
          let message = self.responseMessage(data) ?? "HTTP \(status)"
          NSLog("[SmartAttendance] native check-in rejected status=%ld message=%@", status, message)
          self.emitDiagnostic("Native background check-in failed: \(message)", rssi: rssi)
          return
        }
        guard let session = self.parseSession(data: data, fallbackHubId: hubKey, detectedAt: detectedAt) else {
          self.emitDiagnostic("Native background check-in returned an unreadable response.", rssi: rssi)
          return
        }
        self.saveNativeSession(session)
        NSLog("[SmartAttendance] native check-in confirmed attendance=%lld", session.attendanceLogId)
        self.emitDiagnostic("Native background check-in confirmed (attendance #\(session.attendanceLogId)).", rssi: rssi)
      }
    }.resume()
  }

  private func submitNativeCheckOut(detectedAt: Date) {
    guard !requestInFlight, let session = nativeSession(), session.checkedOutAt == nil else { return }
    guard let config = try? SmartAttendanceKeychain.load(),
          let url = URL(string: config.baseUrl + "/member/attendance/smart-check-out")
    else {
      emitDiagnostic("Gym exit detected, but native attendance is not configured.", rssi: nil)
      return
    }
    requestInFlight = true
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.timeoutInterval = 12
    request.setValue("application/json", forHTTPHeaderField: "Accept")
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.setValue("Bearer \(config.accessToken)", forHTTPHeaderField: "Authorization")
    request.setValue(String(config.gymId), forHTTPHeaderField: "X-Gym-Id")
    request.setValue("member", forHTTPHeaderField: "X-Atlas-App")
    request.setValue("ios", forHTTPHeaderField: "X-Client-Platform")
    addAppVersionHeaders(to: &request)
    request.httpBody = try? JSONSerialization.data(withJSONObject: [
      "attendance_log_id": session.attendanceLogId,
      "last_presence_at": ISO8601DateFormatter().string(from: detectedAt),
    ])
    beginBackgroundProcessingWindow()
    URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
      DispatchQueue.main.async {
        guard let self else { return }
        self.requestInFlight = false
        defer { self.endBackgroundProcessingWindow() }
        if let error {
          self.emitDiagnostic("Native background checkout failed: \(error.localizedDescription)", rssi: nil)
          return
        }
        let status = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(status) else {
          self.emitDiagnostic(
            "Native background checkout failed: \(self.responseMessage(data) ?? "HTTP \(status)")",
            rssi: nil
          )
          return
        }
        var closed = session
        closed.lastPresenceAt = detectedAt
        closed.checkedOutAt = detectedAt
        self.saveNativeSession(closed)
        NSLog("[SmartAttendance] native checkout confirmed attendance=%lld", session.attendanceLogId)
        self.emitDiagnostic("Native background out time confirmed (attendance #\(session.attendanceLogId)).", rssi: nil)
      }
    }.resume()
  }

  private func recordNativePresence(detectedAt: Date) {
    guard var session = nativeSession(),
          detectedAt < session.windowEndsAt,
          session.checkedOutAt == nil,
          detectedAt >= session.lastPresenceAt
    else { return }
    session.lastPresenceAt = detectedAt
    saveNativeSession(session)
  }

  private func startBeaconMonitoring() {
    guard CLLocationManager.isMonitoringAvailable(for: CLBeaconRegion.self) else {
      emitDiagnostic("This iPhone does not support Atlas beacon monitoring.", rssi: nil)
      return
    }
    guard locationManager.authorizationStatus == .authorizedAlways else {
      emitDiagnostic(
        "Always Location access is required for reliable background out-time detection; GPS coordinates are not used.",
        rssi: nil
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

  private func parsePayload(_ data: Data?) -> SmartAttendancePayload? {
    guard let data, data.count >= 2 else { return nil }
    let bytes = [UInt8](data)
    if bytes[0] == 1 {
      guard bytes.count <= 21,
            let publicId = String(bytes: bytes.dropFirst(), encoding: .utf8),
            !publicId.isEmpty,
            publicId.range(of: "^[A-Z0-9_-]+$", options: .regularExpression) != nil
      else { return nil }
      return SmartAttendancePayload(protocolVersion: 1, publicId: publicId)
    }
    guard bytes[0] == 2, bytes.count == 10 else { return nil }
    var quotient = Array(bytes.dropFirst())
    let alphabet = Array("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ")
    var suffix = Array(repeating: Character("0"), count: 13)
    for index in stride(from: 12, through: 0, by: -1) {
      var remainder = 0
      for byteIndex in quotient.indices {
        let combined = remainder * 256 + Int(quotient[byteIndex])
        quotient[byteIndex] = UInt8(combined / 36)
        remainder = combined % 36
      }
      suffix[index] = alphabet[remainder]
    }
    guard quotient.allSatisfy({ $0 == 0 }) else { return nil }
    return SmartAttendancePayload(protocolVersion: 2, publicId: "SAH" + String(suffix))
  }

  private func parseSession(data: Data, fallbackHubId: String, detectedAt: Date) -> SmartAttendanceNativeSession? {
    guard
      let root = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
      let responseData = root["data"] as? [String: Any],
      let attendance = responseData["attendance"] as? [String: Any],
      let id = (attendance["id"] as? NSNumber)?.int64Value
    else { return nil }
    let checkedInAt = parseDate(attendance["checked_in_at"]) ?? detectedAt
    let windowEndsAt = parseDate(attendance["attendance_window_ends_at"])
      ?? checkedInAt.addingTimeInterval(6 * 60 * 60)
    return SmartAttendanceNativeSession(
      attendanceLogId: id,
      hubPublicId: fallbackHubId,
      checkedInAt: checkedInAt,
      lastPresenceAt: parseDate(attendance["last_presence_at"]) ?? detectedAt,
      windowEndsAt: windowEndsAt,
      checkedOutAt: parseDate(attendance["checked_out_at"])
    )
  }

  private func parseDate(_ value: Any?) -> Date? {
    guard let text = value as? String else { return nil }
    let fractional = ISO8601DateFormatter()
    fractional.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
    return fractional.date(from: text) ?? ISO8601DateFormatter().date(from: text)
  }

  private func responseMessage(_ data: Data?) -> String? {
    guard let data,
          let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any]
    else { return nil }
    if let message = json["message"] as? String, !message.isEmpty { return message }
    if let errors = json["errors"] as? [String: Any],
       let first = errors.values.first as? [String] {
      return first.first
    }
    return nil
  }

  private func addAppVersionHeaders(to request: inout URLRequest) {
    let bundle = Bundle.main
    let version = bundle.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String
    let build = bundle.object(forInfoDictionaryKey: "CFBundleVersion") as? String
    if let version, !version.isEmpty {
      request.setValue(version, forHTTPHeaderField: "X-App-Version")
    }
    if let build, !build.isEmpty {
      request.setValue(build, forHTTPHeaderField: "X-App-Version-Code")
    }
  }

  private func emitDiagnostic(_ message: String, rssi: Int?) {
    var event: [String: Any] = [
      "diagnostic": message,
      "detectedAt": Int(Date().timeIntervalSince1970 * 1000),
      "source": "ios_background_native",
    ]
    if let rssi { event["rssi"] = rssi }
    emit(event)
  }

  private func saveNativeSession(_ session: SmartAttendanceNativeSession) {
    var value: [String: Any] = [
      "attendanceLogId": session.attendanceLogId,
      "hubPublicId": session.hubPublicId,
      "checkedInAt": session.checkedInAt.timeIntervalSince1970,
      "lastPresenceAt": session.lastPresenceAt.timeIntervalSince1970,
      "windowEndsAt": session.windowEndsAt.timeIntervalSince1970,
    ]
    if let checkedOutAt = session.checkedOutAt {
      value["checkedOutAt"] = checkedOutAt.timeIntervalSince1970
    }
    UserDefaults.standard.set(value, forKey: sessionKey)
  }

  private func nativeSession() -> SmartAttendanceNativeSession? {
    guard let value = UserDefaults.standard.dictionary(forKey: sessionKey),
          let attendanceLogId = (value["attendanceLogId"] as? NSNumber)?.int64Value,
          let hubPublicId = value["hubPublicId"] as? String,
          let checkedInAt = value["checkedInAt"] as? TimeInterval,
          let lastPresenceAt = value["lastPresenceAt"] as? TimeInterval,
          let windowEndsAt = value["windowEndsAt"] as? TimeInterval
    else { return nil }
    return SmartAttendanceNativeSession(
      attendanceLogId: attendanceLogId,
      hubPublicId: hubPublicId,
      checkedInAt: Date(timeIntervalSince1970: checkedInAt),
      lastPresenceAt: Date(timeIntervalSince1970: lastPresenceAt),
      windowEndsAt: Date(timeIntervalSince1970: windowEndsAt),
      checkedOutAt: (value["checkedOutAt"] as? TimeInterval).map(Date.init(timeIntervalSince1970:))
    )
  }

  private func clearNativeSession() {
    UserDefaults.standard.removeObject(forKey: sessionKey)
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

private struct SmartAttendancePayload {
  let protocolVersion: Int
  let publicId: String
}

private struct SmartAttendanceNativeSession {
  let attendanceLogId: Int64
  let hubPublicId: String
  let checkedInAt: Date
  var lastPresenceAt: Date
  let windowEndsAt: Date
  var checkedOutAt: Date?
}

private struct SmartAttendanceBackgroundConfig: Codable {
  let baseUrl: String
  let accessToken: String
  let gymId: Int64
}

private enum SmartAttendanceKeychain {
  private static let service = "com.techybugs.gymatlas.member.smart-attendance"
  private static let account = "background-config-v1"

  static func save(_ config: SmartAttendanceBackgroundConfig) throws {
    let data = try JSONEncoder().encode(config)
    clear()
    let status = SecItemAdd([
      kSecClass: kSecClassGenericPassword,
      kSecAttrService: service,
      kSecAttrAccount: account,
      kSecAttrAccessible: kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly,
      kSecValueData: data,
    ] as CFDictionary, nil)
    guard status == errSecSuccess else { throw KeychainError.status(status) }
  }

  static func load() throws -> SmartAttendanceBackgroundConfig? {
    var value: CFTypeRef?
    let status = SecItemCopyMatching([
      kSecClass: kSecClassGenericPassword,
      kSecAttrService: service,
      kSecAttrAccount: account,
      kSecReturnData: true,
      kSecMatchLimit: kSecMatchLimitOne,
    ] as CFDictionary, &value)
    if status == errSecItemNotFound { return nil }
    guard status == errSecSuccess, let data = value as? Data else {
      throw KeychainError.status(status)
    }
    return try JSONDecoder().decode(SmartAttendanceBackgroundConfig.self, from: data)
  }

  static func clear() {
    SecItemDelete([
      kSecClass: kSecClassGenericPassword,
      kSecAttrService: service,
      kSecAttrAccount: account,
    ] as CFDictionary)
  }

  private enum KeychainError: Error {
    case status(OSStatus)
  }
}
