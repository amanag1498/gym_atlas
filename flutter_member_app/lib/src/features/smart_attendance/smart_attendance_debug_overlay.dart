import 'package:flutter/material.dart';

import 'smart_attendance_controller.dart';

class SmartAttendanceDebugOverlay extends StatefulWidget {
  const SmartAttendanceDebugOverlay({
    super.key,
    required this.controller,
    required this.navigatorKey,
    required this.child,
  });

  final SmartAttendanceController controller;
  final GlobalKey<NavigatorState> navigatorKey;
  final Widget child;

  @override
  State<SmartAttendanceDebugOverlay> createState() =>
      _SmartAttendanceDebugOverlayState();
}

class _SmartAttendanceDebugOverlayState
    extends State<SmartAttendanceDebugOverlay> {
  bool _expanded = true;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.child,
        Positioned(
          right: 12,
          bottom: 92,
          child: SafeArea(
            minimum: const EdgeInsets.only(bottom: 4),
            child: AnimatedBuilder(
              animation: widget.controller,
              builder: (context, _) {
                final controller = widget.controller;
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

                if (!_expanded) {
                  return _CollapsedDebugControl(
                    color: color,
                    mode: mode,
                    onTap: () => setState(() => _expanded = true),
                  );
                }

                final detection = controller.latestDetection;
                return Material(
                  color: Colors.transparent,
                  child: Container(
                    key: const ValueKey('smart-attendance-debug-control'),
                    width: 300,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xF5111827),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: color.withValues(alpha: .65)),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x40000000),
                          blurRadius: 22,
                          offset: Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 9,
                              height: 9,
                              decoration: BoxDecoration(
                                color: color,
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Expanded(
                              child: Text(
                                'SMART ATTENDANCE DEBUG',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: .7,
                                ),
                              ),
                            ),
                            InkWell(
                              borderRadius: BorderRadius.circular(16),
                              onTap: () => setState(() => _expanded = false),
                              child: const Padding(
                                padding: EdgeInsets.all(3),
                                child: Icon(
                                  Icons.remove_rounded,
                                  color: Colors.white70,
                                  size: 18,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Text(
                          '$mode scan · Bluetooth ${controller.bluetoothPermissionStatus}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          detection == null
                              ? 'Waiting for an Atlas hub signal'
                              : '${detection.publicId} · ${detection.rssi ?? '—'} dBm · ${detection.source}',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Color(0xFFCBD5E1),
                            fontSize: 11,
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _syncSummary(controller),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: controller.backendSyncStatus == 'failed'
                                ? const Color(0xFFFDA4AF)
                                : const Color(0xFF94A3B8),
                            fontSize: 11,
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 10),
                        SizedBox(
                          height: 34,
                          child: FilledButton.icon(
                            onPressed: () => _showDetails(context),
                            icon: const Icon(
                              Icons.bug_report_outlined,
                              size: 16,
                            ),
                            label: const Text('View live logic'),
                            style: FilledButton.styleFrom(
                              backgroundColor: const Color(0xFF3641F5),
                              foregroundColor: Colors.white,
                              textStyle: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ],
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
    final navigatorContext = widget.navigatorKey.currentState?.overlay?.context;
    if (navigatorContext == null) {
      return Future<void>.value();
    }
    return showModalBottomSheet<void>(
      context: navigatorContext,
      useRootNavigator: true,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (sheetContext) => SizedBox(
        height: MediaQuery.sizeOf(sheetContext).height * .82,
        child: AnimatedBuilder(
          animation: widget.controller,
          builder: (context, _) =>
              _SmartAttendanceDebugDetails(controller: widget.controller),
        ),
      ),
    );
  }

  String _syncSummary(SmartAttendanceController controller) {
    return switch (controller.backendSyncStatus) {
      'sending' => 'Backend report: sending…',
      'synced' =>
        'Backend report: synced ${_shortTime(controller.lastBackendSyncAt)}',
      'failed' =>
        'Backend report failed: ${controller.backendSyncError ?? 'unknown error'}',
      _ => 'Backend report: not sent yet',
    };
  }

  String _shortTime(DateTime? value) {
    if (value == null) return '';
    final local = value.toLocal();
    String two(int number) => number.toString().padLeft(2, '0');
    return '${two(local.hour)}:${two(local.minute)}:${two(local.second)}';
  }
}

class _CollapsedDebugControl extends StatelessWidget {
  const _CollapsedDebugControl({
    required this.color,
    required this.mode,
    required this.onTap,
  });

  final Color color;
  final String mode;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          decoration: BoxDecoration(
            color: const Color(0xF5111827),
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: color.withValues(alpha: .7)),
          ),
          child: Text(
            'Smart Attendance · $mode',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
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
          'Live test diagnostics for the background attendance scanner.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: () => _armBackgroundTest(context),
          icon: const Icon(Icons.restart_alt_rounded),
          label: const Text('Arm next background check-in'),
        ),
        const SizedBox(height: 20),
        if (controller.lastError != null || controller.backendSyncError != null)
          _DebugErrorSection(
            scannerError: controller.lastError,
            backendError: controller.backendSyncError,
          ),
        _DebugSection(
          title: 'Live decision',
          children: [
            _DebugValue(label: 'Current action', value: controller.logicState),
            _DebugValue(
              label: 'First check-in request',
              value: controller.lastAttendanceRequestResult,
            ),
            _DebugValue(
              label: 'Request time',
              value: controller.lastAttendanceRequestAt == null
                  ? 'Never'
                  : _time(controller.lastAttendanceRequestAt!),
            ),
            _DebugValue(
              label: 'Local updates',
              value: '${controller.localPresenceUpdateCount}',
            ),
            _DebugValue(
              label: 'Latest local update',
              value: controller.lastLocalPresenceAt == null
                  ? 'None yet'
                  : _time(controller.lastLocalPresenceAt!),
            ),
            _DebugValue(
              label: 'Out-time request',
              value: controller.lastCheckoutRequestResult,
            ),
            _DebugValue(
              label: 'Out-time attempt',
              value: controller.lastCheckoutRequestAt == null
                  ? 'Never'
                  : _time(controller.lastCheckoutRequestAt!),
            ),
          ],
        ),
        const _DebugSection(
          title: 'Rules in this build',
          children: [
            _DebugValue(label: 'Presence confirmation', value: '2.4 seconds'),
            _DebugValue(label: 'Minimum signal', value: '-78 dBm'),
            _DebugValue(label: 'Attendance window', value: '6 hours'),
            _DebugValue(label: 'No-signal out time', value: '2 hours'),
            _DebugValue(
              label: 'Backend check-ins',
              value: 'First confirmation and visit reopen only',
            ),
          ],
        ),
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
            _DebugValue(
              label: 'Backend report',
              value: controller.backendSyncStatus,
            ),
            _DebugValue(
              label: 'Last backend sync',
              value: controller.lastBackendSyncAt == null
                  ? 'Never'
                  : _time(controller.lastBackendSyncAt!),
            ),
            if (controller.backendSyncError != null)
              _DebugValue(
                label: 'Backend error',
                value: controller.backendSyncError!,
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

  Future<void> _armBackgroundTest(BuildContext context) async {
    try {
      await controller.armNextBackgroundDetectionForTesting();
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Test armed. Close this sheet, then press Home or lock the phone near the Hub.',
          ),
        ),
      );
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not arm test: $error')));
    }
  }

  String _time(DateTime value) {
    final local = value.toLocal();
    String two(int number) => number.toString().padLeft(2, '0');
    return '${two(local.day)}/${two(local.month)}/${local.year} '
        '${two(local.hour)}:${two(local.minute)}:${two(local.second)}';
  }
}

class _DebugErrorSection extends StatelessWidget {
  const _DebugErrorSection({this.scannerError, this.backendError});

  final String? scannerError;
  final String? backendError;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFE9EC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFDA4AF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Errors requiring attention',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: const Color(0xFF9F1239),
              fontWeight: FontWeight.w800,
            ),
          ),
          if (scannerError != null) ...[
            const SizedBox(height: 10),
            const Text('Scanner / attendance'),
            SelectableText(scannerError!),
          ],
          if (backendError != null) ...[
            const SizedBox(height: 10),
            const Text('Backend telemetry'),
            SelectableText(backendError!),
          ],
        ],
      ),
    );
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
