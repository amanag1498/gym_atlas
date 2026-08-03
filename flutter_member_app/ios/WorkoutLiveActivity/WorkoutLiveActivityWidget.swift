import ActivityKit
import SwiftUI
import WidgetKit

@main
struct WorkoutLiveActivityBundle: WidgetBundle {
  var body: some Widget {
    WorkoutLiveActivityWidget()
  }
}

struct WorkoutLiveActivityWidget: Widget {
  var body: some WidgetConfiguration {
    ActivityConfiguration(for: WorkoutActivityAttributes.self) { context in
      lockScreenView(context)
        .activityBackgroundTint(Color(red: 0.04, green: 0.07, blue: 0.12))
        .activitySystemActionForegroundColor(.white)
    } dynamicIsland: { context in
      DynamicIsland {
        DynamicIslandExpandedRegion(.leading) {
          Image(systemName: "figure.strengthtraining.traditional")
            .foregroundStyle(.orange)
        }
        DynamicIslandExpandedRegion(.trailing) {
          timer(context.state)
            .font(.caption.monospacedDigit())
            .foregroundStyle(.white)
        }
        DynamicIslandExpandedRegion(.center) {
          Text(context.state.exerciseName)
            .font(.headline)
            .lineLimit(1)
        }
        DynamicIslandExpandedRegion(.bottom) {
          compactActions(context)
        }
      } compactLeading: {
        Image(systemName: "figure.strengthtraining.traditional")
          .foregroundStyle(.orange)
      } compactTrailing: {
        Text("\(context.state.setNumber)/\(context.state.totalSets)")
          .font(.caption2.monospacedDigit())
      } minimal: {
        Image(systemName: "figure.strengthtraining.traditional")
          .foregroundStyle(.orange)
      }
      .widgetURL(URL(string: "gymatlas-member://workout/\(context.attributes.sessionId)"))
      .keylineTint(.orange)
    }
  }

  private func lockScreenView(
    _ context: ActivityViewContext<WorkoutActivityAttributes>
  ) -> some View {
    VStack(alignment: .leading, spacing: 12) {
      HStack {
        Label(context.attributes.workoutName, systemImage: "figure.strengthtraining.traditional")
          .font(.headline)
          .foregroundStyle(.white)
          .lineLimit(1)
        Spacer()
        timer(context.state)
          .font(.subheadline.monospacedDigit().weight(.semibold))
          .foregroundStyle(.orange)
      }

      HStack(alignment: .firstTextBaseline) {
        VStack(alignment: .leading, spacing: 3) {
          Text(context.state.exerciseName)
            .font(.title3.weight(.semibold))
            .foregroundStyle(.white)
            .lineLimit(1)
          Text(setSummary(context.state))
            .font(.subheadline)
            .foregroundStyle(.secondary)
        }
        Spacer()
        Text("Set \(context.state.setNumber) of \(context.state.totalSets)")
          .font(.caption.weight(.medium))
          .foregroundStyle(.white.opacity(0.75))
      }

      compactActions(context)
    }
    .padding(16)
    .widgetURL(URL(string: "gymatlas-member://workout/\(context.attributes.sessionId)"))
  }

  @ViewBuilder
  private func compactActions(
    _ context: ActivityViewContext<WorkoutActivityAttributes>
  ) -> some View {
    if #available(iOSApplicationExtension 17.0, *) {
      HStack(spacing: 10) {
        Button(intent: CompleteWorkoutSetIntent(
          sessionId: context.attributes.sessionId,
          exerciseId: context.state.exerciseId,
          setNumber: context.state.setNumber
        )) {
          Label("Complete", systemImage: "checkmark.circle.fill")
        }
        .tint(.green)

        Button(intent: ToggleWorkoutRestIntent(
          sessionId: context.attributes.sessionId,
          value: context.state.restEndsAt == nil ? "start" : "skip"
        )) {
          Image(systemName: context.state.restEndsAt == nil ? "timer" : "forward.fill")
        }
        .tint(.orange)

        Button(intent: NextWorkoutExerciseIntent(
          sessionId: context.attributes.sessionId
        )) {
          Image(systemName: "chevron.right")
        }
        .tint(.blue)

        Button(intent: EndWorkoutIntent(sessionId: context.attributes.sessionId)) {
          Image(systemName: "stop.fill")
        }
        .tint(.red)
      }
      .buttonStyle(.borderedProminent)
      .labelStyle(.titleAndIcon)
    } else {
      // iOS 16 Live Activities are display-only. Tapping anywhere opens the
      // workout; iOS 17 adds authenticated App Intent controls above.
      Link(destination: URL(string: "gymatlas-member://workout/\(context.attributes.sessionId)")!) {
        Label("Open workout", systemImage: "arrow.up.forward.app")
          .frame(maxWidth: .infinity)
      }
      .buttonStyle(.borderedProminent)
      .tint(.orange)
    }
  }

  @ViewBuilder
  private func timer(_ state: WorkoutActivityAttributes.ContentState) -> some View {
    if let restEndsAt = state.restEndsAt, restEndsAt > Date() {
      Text(timerInterval: Date()...restEndsAt, countsDown: true)
    } else if state.isPaused {
      Text("Paused")
    } else {
      Text(timerInterval: state.startedAt...Date.distantFuture, countsDown: false)
    }
  }

  private func setSummary(_ state: WorkoutActivityAttributes.ContentState) -> String {
    var parts: [String] = []
    if let reps = state.reps { parts.append("\(reps) reps") }
    if let weight = state.weight {
      parts.append("\(weight.formatted(.number.precision(.fractionLength(0...1)))) \(state.weightUnit)")
    }
    return parts.isEmpty ? "Ready for the next set" : parts.joined(separator: " · ")
  }
}
