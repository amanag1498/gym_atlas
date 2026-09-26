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
        scanner.start(result: result)
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
  private lazy var centralManager = CBCentralManager(delegate: self, queue: nil)
  private var pendingStartResult: FlutterResult?
  private var wantsScanning = false

  func onListen(withArguments arguments: Any?, eventSink events: @escaping FlutterEventSink) -> FlutterError? {
    eventSink = events
    return nil
  }

  func onCancel(withArguments arguments: Any?) -> FlutterError? {
    eventSink = nil
    return nil
  }

  func start(result: @escaping FlutterResult) {
    wantsScanning = true
    if centralManager.state == .poweredOn {
      centralManager.scanForPeripherals(
        withServices: [serviceUuid],
        options: [CBCentralManagerScanOptionAllowDuplicatesKey: true]
      )
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
    centralManager.stopScan()
    pendingStartResult = nil
    result?(nil)
  }

  func centralManagerDidUpdateState(_ central: CBCentralManager) {
    guard wantsScanning else { return }
    if central.state == .poweredOn {
      central.scanForPeripherals(
        withServices: [serviceUuid],
        options: [CBCentralManagerScanOptionAllowDuplicatesKey: true]
      )
      pendingStartResult?(nil)
      pendingStartResult = nil
    } else if central.state != .unknown && central.state != .resetting {
      pendingStartResult?(FlutterError(code: "bluetooth_unavailable", message: stateMessage(central.state), details: nil))
      pendingStartResult = nil
    }
  }

  func centralManager(
    _ central: CBCentralManager,
    didDiscover peripheral: CBPeripheral,
    advertisementData: [String: Any],
    rssi RSSI: NSNumber
  ) {
    let serviceData = advertisementData[CBAdvertisementDataServiceDataKey] as? [CBUUID: Data]
    eventSink?([
      "serviceUuid": serviceUuid.uuidString.lowercased(),
      "serviceData": serviceData?[serviceUuid]?.map { Int($0) },
      "rssi": RSSI.intValue,
      "detectedAt": Int(Date().timeIntervalSince1970 * 1000),
      "source": "ios_foreground_ble",
    ])
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
