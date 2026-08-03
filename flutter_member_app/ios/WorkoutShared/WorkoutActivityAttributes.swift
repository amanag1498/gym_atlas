import ActivityKit
import Foundation

@available(iOS 16.1, *)
struct WorkoutActivityAttributes: ActivityAttributes {
  struct ContentState: Codable, Hashable {
    var exerciseId: String?
    var exerciseName: String
    var setNumber: Int
    var totalSets: Int
    var reps: Int?
    var weight: Double?
    var weightUnit: String
    var startedAt: Date
    var restEndsAt: Date?
    var isPaused: Bool
  }

  var sessionId: String
  var workoutName: String
}
