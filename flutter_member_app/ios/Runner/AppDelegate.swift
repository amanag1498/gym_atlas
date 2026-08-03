import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {
  private let workoutLiveActivityManager = WorkoutLiveActivityManager()

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    GeneratedPluginRegistrant.register(with: self)

    if let controller = window?.rootViewController as? FlutterViewController {
      workoutLiveActivityManager.register(with: controller.binaryMessenger)
    }

    NotificationCenter.default.addObserver(
      workoutLiveActivityManager,
      selector: #selector(WorkoutLiveActivityManager.applicationDidBecomeActive),
      name: UIApplication.didBecomeActiveNotification,
      object: nil
    )
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  override func application(
    _ app: UIApplication,
    open url: URL,
    options: [UIApplication.OpenURLOptionsKey: Any] = [:]
  ) -> Bool {
    if workoutLiveActivityManager.handle(url: url) {
      return true
    }
    return super.application(app, open: url, options: options)
  }
}
