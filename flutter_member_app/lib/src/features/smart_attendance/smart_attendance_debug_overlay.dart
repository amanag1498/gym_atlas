import 'package:flutter/material.dart';

import 'smart_attendance_controller.dart';

class SmartAttendanceDebugOverlay extends StatelessWidget {
  const SmartAttendanceDebugOverlay({
    super.key,
    required this.controller,
    required this.child,
  });

  final SmartAttendanceController controller;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        child,
        Positioned(
          right: 12,
          bottom: 92,
          child: SafeArea(
            minimum: const EdgeInsets.only(bottom: 4),
            child: AnimatedBuilder(
              animation: controller,
              builder: (context, _) {
                final active = controller.scanning;
                final mode = !active
                    ? 'OFF'
                    : controller.backgroundScanning
                    ? 'BG'
                    : 'FG';
                final color = controller.permissionDenied
                    ? const Color(0xFFE05263)
                    : active
                    ? const Color(0xFF29C987)
                    : const Color(0xFFF5A524);

                return Semantics(
                  button: true,
                  label: 'Open Smart Attendance diagnostics',
                  child: Material(
                    color: Colors.transparent,
                    child: InkWell(
                      key: const ValueKey('smart-attendance-debug-control'),
                      borderRadius: BorderRadius.circular(22),
                      onTap: () => _showDetails(context),
                      child: Ink(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xED111827),
                          borderRadius: BorderRadius.circular(22),
                          border: Border.all(
                            color: color.withValues(alpha: .7),
                          ),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x33000000),
                              blurRadius: 16,
                              offset: Offset(0, 6),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                color: color,
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 7),
                            Text(
                              'SA $mode',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                letterSpacing: .8,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _showDetails(BuildContext context) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (_) => FractionallySizedBox(
        heightFactor: .82,
        child: AnimatedBuilder(
          animation: controller,
          builder: (context, _) =>
              _SmartAttendanceDebugDetails(controller: controller),
        ),
      ),
    );
  }
}

class _SmartAttendanceDebugDetails extends StatelessWidget {
  const _SmartAttendanceDebugDetails({required this.controller});

  final SmartAttendanceController controller;

  @override
  Widget build(BuildContext context) {
    final detection = controller.latestDetection;
    final session = controller.activeSession;
    final diagnostics = controller.diagnostics.reversed.take(6).toList();

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
      children: [
        Text(
          'Smart Attendance diagnostics',
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        Text(
          'Live debug-build state for the background attendance scanner.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 20),
        _DebugSection(
          title: 'Scanner',
          children: [
            _DebugValue(
              label: 'Bluetooth permission',
              value: controller.bluetoothPermissionStatus,
            ),
            _DebugValue(
              label: 'Scanning',
              value: controller.scanning ? 'Yes' : 'No',
            ),
            _DebugValue(
              label: 'Mode',
              value: controller.scanning
                  ? (controller.backgroundScanning
                        ? 'Background'
                        : 'Foreground')
                  : 'Stopped',
            ),
            _DebugValue(
              label: 'Attendance request',
              value: controller.checkInInFlight ? 'Sending' : 'Idle',
            ),
          ],
        ),
        _DebugSection(
          title: 'Latest hub signal',
          children: detection == null
              ? const [_DebugValue(label: 'Status', value: 'None detected')]
              : [
                  _DebugValue(label: 'Public ID', value: detection.publicId),
                  _DebugValue(
                    label: 'Protocol',
                    value: 'v${detection.protocolVersion}',
                  ),
                  _DebugValue(
                    label: 'Signal',
                    value: detection.rssi == null
                        ? 'Unknown'
                        : '${detection.rssi} dBm',
                  ),
                  _DebugValue(label: 'Source', value: detection.source),
                  _DebugValue(
                    label: 'Detected',
                    value: _time(detection.detectedAt),
                  ),
                ],
        ),
        _DebugSection(
          title: 'Attendance window',
          children: session == null
              ? const [_DebugValue(label: 'Status', value: 'No active visit')]
              : [
                  _DebugValue(
                    label: 'Attendance log',
                    value: '#${session.attendanceLogId}',
                  ),
                  _DebugValue(label: 'Hub', value: session.hubPublicId),
                  _DebugValue(
                    label: 'In time',
                    value: _time(session.checkedInAt),
                  ),
                  _DebugValue(
                    label: 'Last presence',
                    value: _time(session.lastPresenceAt),
                  ),
                  _DebugValue(
                    label: 'Window ends',
                    value: _time(session.windowEndsAt),
                  ),
                  _DebugValue(
                    label: 'Out time',
                    value: session.checkedOutAt == null
                        ? 'Not saved yet'
                        : _time(session.checkedOutAt!),
                  ),
                ],
        ),
        if (controller.lastCheckInMessage != null ||
            controller.lastError != null)
          _DebugSection(
            title: 'Last result',
            children: [
              if (controller.lastCheckInMessage != null)
                _DebugValue(
                  label: 'Success',
                  value: controller.lastCheckInMessage!,
                ),
              if (controller.lastError != null)
                _DebugValue(label: 'Error', value: controller.lastError!),
            ],
          ),
        _DebugSection(
          title: 'Recent scanner diagnostics',
          children: diagnostics.isEmpty
              ? const [_DebugValue(label: 'Status', value: 'No diagnostics')]
              : diagnostics
                    .map(
                      (diagnostic) => _DebugValue(
                        label: _time(diagnostic.detectedAt),
                        value: [
                          diagnostic.message,
                          if (diagnostic.rssi != null) '${diagnostic.rssi} dBm',
                          if (diagnostic.source?.isNotEmpty == true)
                            diagnostic.source!,
                        ].join(' · '),
                      ),
                    )
                    .toList(),
        ),
      ],
    );
  }

  String _time(DateTime value) {
    final local = value.toLocal();
    String two(int number) => number.toString().padLeft(2, '0');
    return '${two(local.day)}/${two(local.month)}/${local.year} '
        '${two(local.hour)}:${two(local.minute)}:${two(local.second)}';
  }
}

class _DebugSection extends StatelessWidget {
  const _DebugSection({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                title,
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 10),
              ...children,
            ],
          ),
        ),
      ),
    );
  }
}

class _DebugValue extends StatelessWidget {
  const _DebugValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(label, style: Theme.of(context).textTheme.bodySmall),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: SelectableText(
              value,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
