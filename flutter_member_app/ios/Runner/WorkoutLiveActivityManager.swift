import ActivityKit
import Flutter
import Foundation

/// Native half of the Flutter workout lock-screen contract.
///
/// Live Activity controls intentionally set `openAppWhenRun` in the widget
/// extension. iOS requires the member to authenticate before these controls
/// can launch the app from a locked device. The queued action is emitted only
/// after the app becomes active; Flutter remains the single place that applies
/// and persists the workout mutation.
final class WorkoutLiveActivityManager: NSObject, FlutterStreamHandler {
  private static let methodChannelName =
    "com.techybugs.gymatlas.member/workout_lock_screen"
  private static let eventChannelName =
    "com.techybugs.gymatlas.member/workout_lock_screen_actions"
  private static let appGroup = "group.com.techybugs.gymatlas.member"
  private static let pendingActionsKey = "workout.pending-lock-screen-actions"

  private var eventSink: FlutterEventSink?

  func register(with messenger: FlutterBinaryMessenger) {
    let methods = FlutterMethodChannel(
      name: Self.methodChannelName,
      binaryMessenger: messenger
    )
    methods.setMethodCallHandler { [weak self] call, result in
      self?.handle(call, result: result)
    }

    let events = FlutterEventChannel(
      name: Self.eventChannelName,
      binaryMessenger: messenger
    )
    events.setStreamHandler(self)
  }

  func onListen(
    withArguments arguments: Any?,
    eventSink events: @escaping FlutterEventSink
  ) -> FlutterError? {
    eventSink = events
    drainPendingActions()
    return nil
  }

  func onCancel(withArguments arguments: Any?) -> FlutterError? {
    eventSink = nil
    return nil
  }

  @objc func applicationDidBecomeActive() {
    drainPendingActions()
  }

  func handle(url: URL) -> Bool {
    guard url.scheme == "gymatlas-member", url.host == "workout" else {
      return false
    }
    let sessionId = url.pathComponents.dropFirst().first ?? ""
    guard !sessionId.isEmpty else { return false }
    enqueue(["action": "open", "sessionId": sessionId])
    drainPendingActions()
    return true
  }

  private func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    switch call.method {
    case "isSupported":
      if #available(iOS 16.1, *) {
        result(ActivityAuthorizationInfo().areActivitiesEnabled)
      } else {
        result(false)
      }
    case "start":
      guard #available(iOS 16.1, *) else {
        result(false)
        return
      }
      guard let values = call.arguments as? [String: Any],
            let sessionId = string(values["sessionId"]),
            !sessionId.isEmpty else {
        result(FlutterError(
          code: "invalid_arguments",
          message: "start requires a non-empty sessionId",
          details: nil
        ))
        return
      }
      Task { @MainActor in
        do {
          try await start(values: values, sessionId: sessionId)
          result(true)
        } catch {
          result(activityError(error))
        }
      }
    case "update":
      guard #available(iOS 16.1, *) else {
        result(false)
        return
      }
      guard let values = call.arguments as? [String: Any],
            let sessionId = string(values["sessionId"]),
            !sessionId.isEmpty else {
        result(FlutterError(
          code: "invalid_arguments",
          message: "update requires a non-empty sessionId",
          details: nil
        ))
        return
      }
      Task { @MainActor in
        await update(values: values, sessionId: sessionId)
        result(true)
      }
    case "end":
      guard #available(iOS 16.1, *) else {
        result(false)
        return
      }
      let values = call.arguments as? [String: Any] ?? [:]
      let sessionId = string(values["sessionId"])
      Task { @MainActor in
        await end(sessionId: sessionId, values: values)
        result(true)
      }
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  @available(iOS 16.1, *)
  @MainActor
  private func start(values: [String: Any], sessionId: String) async throws {
    guard ActivityAuthorizationInfo().areActivitiesEnabled else {
      throw WorkoutLiveActivityError.disabled
    }

    // Only one ongoing workout should be visible. End a stale activity before
    // creating a replacement, including after an app process restart.
    for activity in Activity<WorkoutActivityAttributes>.activities {
      if activity.attributes.sessionId != sessionId {
        await activity.end(dismissalPolicy: .immediate)
      } else {
        await activity.update(using: state(from: values, fallback: activity.contentState))
        return
      }
    }

    let attributes = WorkoutActivityAttributes(
      sessionId: sessionId,
      workoutName: string(values["workoutName"]) ?? "Workout"
    )
    _ = try Activity.request(
      attributes: attributes,
      contentState: state(from: values, fallback: nil),
      pushType: nil
    )
  }

  @available(iOS 16.1, *)
  @MainActor
  private func update(values: [String: Any], sessionId: String) async {
    guard let activity = Activity<WorkoutActivityAttributes>.activities.first(where: {
      $0.attributes.sessionId == sessionId
    }) else { return }
    await activity.update(using: state(from: values, fallback: activity.contentState))
  }

  @available(iOS 16.1, *)
  @MainActor
  private func end(sessionId: String?, values: [String: Any]) async {
    let activities = Activity<WorkoutActivityAttributes>.activities.filter {
      sessionId == nil || $0.attributes.sessionId == sessionId
    }
    for activity in activities {
      let finalState = state(from: values, fallback: activity.contentState)
      await activity.end(using: finalState, dismissalPolicy: .immediate)
    }
  }

  @available(iOS 16.1, *)
  private func state(
    from values: [String: Any],
    fallback: WorkoutActivityAttributes.ContentState?
  ) -> WorkoutActivityAttributes.ContentState {
    let restEnd = date(values["restEndsAtEpochMillis"])
      ?? date(values["restEndsAt"])
    let startedAt = date(values["startedAtEpochMillis"])
      ?? date(values["startedAt"])
      ?? fallback?.startedAt
      ?? Date()
    return WorkoutActivityAttributes.ContentState(
      exerciseId: string(values["exerciseId"]) ?? fallback?.exerciseId,
      exerciseName: string(values["exerciseName"])
        ?? fallback?.exerciseName
        ?? "Workout in progress",
      setNumber: integer(values["setNumber"]) ?? fallback?.setNumber ?? 1,
      totalSets: integer(values["totalSets"]) ?? fallback?.totalSets ?? 1,
      reps: integer(values["reps"]) ?? fallback?.reps,
      weight: double(values["weight"]) ?? fallback?.weight,
      weightUnit: string(values["weightUnit"]) ?? fallback?.weightUnit ?? "kg",
      startedAt: startedAt,
      restEndsAt: restEnd ?? (values.keys.contains("restEndsAt") ? nil : fallback?.restEndsAt),
      isPaused: boolean(values["isPaused"]) ?? fallback?.isPaused ?? false
    )
  }

  private func drainPendingActions() {
    guard let defaults = UserDefaults(suiteName: Self.appGroup),
          let actions = defaults.array(forKey: Self.pendingActionsKey) as? [[String: Any]],
          !actions.isEmpty,
          let sink = eventSink else { return }

    // Clear before dispatch so a Flutter restart cannot apply an action twice.
    defaults.removeObject(forKey: Self.pendingActionsKey)
    for action in actions {
      sink(action)
    }
  }

  private func enqueue(_ action: [String: Any]) {
    guard let defaults = UserDefaults(suiteName: Self.appGroup) else { return }
    var actions = defaults.array(forKey: Self.pendingActionsKey) as? [[String: Any]] ?? []
    actions.append(action)
    defaults.set(actions, forKey: Self.pendingActionsKey)
  }

  private func string(_ value: Any?) -> String? {
    if let value = value as? String { return value }
    if let value = value as? NSNumber { return value.stringValue }
    return nil
  }

  private func integer(_ value: Any?) -> Int? {
    if let value = value as? Int { return value }
    if let value = value as? NSNumber { return value.intValue }
    if let value = value as? String { return Int(value) }
    return nil
  }

  private func double(_ value: Any?) -> Double? {
    if let value = value as? Double { return value }
    if let value = value as? NSNumber { return value.doubleValue }
    if let value = value as? String { return Double(value) }
    return nil
  }

  private func boolean(_ value: Any?) -> Bool? {
    if let value = value as? Bool { return value }
    if let value = value as? NSNumber { return value.boolValue }
    return nil
  }

  private func date(_ value: Any?) -> Date? {
    if let milliseconds = double(value) {
      return Date(timeIntervalSince1970: milliseconds / 1_000)
    }
    guard let text = value as? String else { return nil }
    return ISO8601DateFormatter().date(from: text)
  }

  private func activityError(_ error: Error) -> FlutterError {
    FlutterError(
      code: "live_activity_error",
      message: error.localizedDescription,
      details: nil
    )
  }
}

private enum WorkoutLiveActivityError: LocalizedError {
  case disabled

  var errorDescription: String? {
    switch self {
    case .disabled:
      return "Live Activities are disabled on this device."
    }
  }
}
