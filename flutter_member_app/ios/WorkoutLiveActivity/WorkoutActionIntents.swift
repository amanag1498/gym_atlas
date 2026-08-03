import AppIntents
import Foundation

private enum WorkoutActionQueue {
  static let appGroup = "group.com.techybugs.gymatlas.member"
  static let key = "workout.pending-lock-screen-actions"
  static let lock = NSLock()
  static let maximumPendingActions = 50

  static func append(
    action: String,
    sessionId: String,
    exerciseId: String? = nil,
    setNumber: Int? = nil,
    value: String? = nil
  ) {
    lock.lock()
    defer { lock.unlock() }
    guard let defaults = UserDefaults(suiteName: appGroup) else { return }
    var actions = defaults.array(forKey: key) as? [[String: Any]] ?? []
    var payload: [String: Any] = [
      "action": action,
      "sessionId": sessionId,
    ]
    if let exerciseId { payload["exerciseId"] = exerciseId }
    if let setNumber { payload["setNumber"] = setNumber }
    if let value { payload["value"] = value }
    actions.append(payload)
    if actions.count > maximumPendingActions {
      actions.removeFirst(actions.count - maximumPendingActions)
    }
    defaults.set(actions, forKey: key)
  }
}

/// These intents open the containing app. On a locked iPhone, iOS requires
/// authentication before doing so; workout data is never mutated silently
/// while the device remains locked.
struct CompleteWorkoutSetIntent: AppIntent {
  static var title: LocalizedStringResource = "Complete set"
  static var openAppWhenRun = true

  @Parameter(title: "Session") var sessionId: String
  @Parameter(title: "Exercise") var exerciseId: String?
  @Parameter(title: "Set") var setNumber: Int

  init() {}
  init(sessionId: String, exerciseId: String?, setNumber: Int) {
    self.sessionId = sessionId
    self.exerciseId = exerciseId
    self.setNumber = setNumber
  }

  func perform() async throws -> some IntentResult {
    WorkoutActionQueue.append(
      action: "complete_set",
      sessionId: sessionId,
      exerciseId: exerciseId,
      setNumber: setNumber
    )
    return .result()
  }
}

struct ToggleWorkoutRestIntent: AppIntent {
  static var title: LocalizedStringResource = "Rest timer"
  static var openAppWhenRun = true

  @Parameter(title: "Session") var sessionId: String
  @Parameter(title: "Action") var value: String

  init() {}
  init(sessionId: String, value: String) {
    self.sessionId = sessionId
    self.value = value
  }

  func perform() async throws -> some IntentResult {
    WorkoutActionQueue.append(
      action: value == "skip" ? "skip_rest" : "start_rest",
      sessionId: sessionId,
      value: value
    )
    return .result()
  }
}

struct NextWorkoutExerciseIntent: AppIntent {
  static var title: LocalizedStringResource = "Next exercise"
  static var openAppWhenRun = true

  @Parameter(title: "Session") var sessionId: String

  init() {}
  init(sessionId: String) { self.sessionId = sessionId }

  func perform() async throws -> some IntentResult {
    WorkoutActionQueue.append(action: "next_exercise", sessionId: sessionId)
    return .result()
  }
}

struct EndWorkoutIntent: AppIntent {
  static var title: LocalizedStringResource = "End workout"
  static var description = IntentDescription("Open Gym Atlas to confirm and end this workout.")
  static var openAppWhenRun = true

  @Parameter(title: "Session") var sessionId: String

  init() {}
  init(sessionId: String) { self.sessionId = sessionId }

  func perform() async throws -> some IntentResult {
    WorkoutActionQueue.append(action: "end", sessionId: sessionId)
    return .result()
  }
}

struct OpenWorkoutIntent: AppIntent {
  static var title: LocalizedStringResource = "Open workout"
  static var openAppWhenRun = true

  @Parameter(title: "Session") var sessionId: String

  init() {}
  init(sessionId: String) { self.sessionId = sessionId }

  func perform() async throws -> some IntentResult {
    WorkoutActionQueue.append(action: "open", sessionId: sessionId)
    return .result()
  }
}
