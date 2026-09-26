import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'guide_definitions.dart';
import 'guide_state_store.dart';
import 'guide_tooltip.dart';

export 'guide_definitions.dart';
export 'guide_state_store.dart';

/// Analytics deliberately accepts only guide identifiers and step numbers.
const guideFeatureEnabled = bool.fromEnvironment(
  'ATLAS_GUIDES_ENABLED',
  defaultValue: true,
);

typedef GuideEventSink = void Function(String event, String guideId, int? step);

class GuideController {
  GuideController({
    required this.account,
    required this.guides,
    GuideStateStore? store,
    this.onEvent,
  }) : store = store ?? GuideStateStore();
  final String account;
  final List<GuideDefinition> guides;
  final GuideStateStore store;
  final GuideEventSink? onEvent;
  final Map<String, BuildContext> targets = {};
  final Set<String> _attempted = {};
  Timer? _timer;
  Route<void>? _route;
  ModalRoute<dynamic>? _sourceRoute;
  bool _busy = false;
  bool _disposed = false;
  bool _disabled = false;
  void event(String name, String id, [int? step]) {
    try {
      onEvent?.call(name, id, step);
    } catch (_) {
      /* Analytics never blocks help. */
    }
  }

  void register(String id, BuildContext context) {
    targets[id] = context;
    schedule();
  }

  void unregister(String id, BuildContext context) {
    if (identical(targets[id], context)) targets.remove(id);
  }

  void schedule() {
    if (_disposed || _busy) return;
    _timer?.cancel();
    _timer = Timer(const Duration(milliseconds: 650), _tryStart);
  }

  BuildContext? target(GuideDefinition guide, GuideStep step) {
    final context = targets['${guide.id}/${step.target}'];
    if (context == null || !context.mounted) return null;
    if (_route != null && ModalRoute.of(context) != _sourceRoute) return null;
    final box = context.findRenderObject();
    if (box is! RenderBox || !box.attached || !box.hasSize || box.size.isEmpty)
      return null;
    RenderObject? ancestor = box;
    while (ancestor != null) {
      if (ancestor is RenderOffstage && ancestor.offstage) return null;
      ancestor = ancestor.parent;
    }
    return context;
  }

  Future<void> _tryStart() async {
    if (_disposed || _busy || _disabled) return;
    _busy = true;
    try {
      if (await store.disabled(account)) return;
      for (final guide in guides) {
        if (_disposed) return;
        if (_attempted.contains(guide.id) || await store.seen(account, guide))
          continue;
        if (_disposed) return;
        BuildContext? origin;
        for (final step in guide.steps) {
          final candidate = target(guide, step);
          if (candidate != null &&
              ModalRoute.of(candidate)?.isCurrent == true) {
            origin = candidate;
            break;
          }
        }
        if (origin == null || !origin.mounted) continue;
        _attempted.add(guide.id);
        FocusManager.instance.primaryFocus?.unfocus();
        final reduced =
            MediaQuery.of(origin).disableAnimations ||
            MediaQuery.of(origin).accessibleNavigation;
        final route = RawDialogRoute<void>(
          barrierDismissible: false,
          barrierColor: Colors.transparent,
          transitionDuration: Duration(milliseconds: reduced ? 0 : 160),
          pageBuilder: (_, _, _) =>
              GuideOverlay(controller: this, guide: guide),
        );
        _sourceRoute = ModalRoute.of(origin);
        _route = route;
        event('guide_started', guide.id);
        await Navigator.of(origin, rootNavigator: true).push(route);
        _route = null;
        _sourceRoute = null;
        break; // One contextual guide per visit; do not chain interruptions.
      }
    } catch (_) {
      // Storage/plugin failures fail closed; the app remains fully usable.
    } finally {
      _busy = false;
    }
  }

  Future<void> finish(GuideDefinition guide, bool skipped) async {
    event(skipped ? 'guide_skipped' : 'guide_completed', guide.id);
    try {
      await store.mark(account, guide, skipped ? 'skipped' : 'completed');
    } catch (_) {}
  }

  Future<void> replay(List<GuideDefinition> selected) async {
    await store.setDisabled(account, false);
    _disabled = false;
    for (final guide in selected) {
      await store.reset(account, guide);
      _attempted.remove(guide.id);
      event('guide_replayed', guide.id);
    }
  }

  Future<void> setDisabled(bool disabled) async {
    await store.setDisabled(account, disabled);
    _disabled = disabled;
  }

  void dispose() {
    _disposed = true;
    _timer?.cancel();
    final route = _route;
    if (route != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (route.navigator != null) route.navigator!.removeRoute(route);
      });
    }
    targets.clear();
  }
}

/// Place above the app Navigator. Changing accounts disposes the active tour.
class GuideScope extends StatefulWidget {
  const GuideScope({
    super.key,
    required this.account,
    required this.guides,
    required this.child,
    this.store,
    this.onEvent,
  });
  final String? account;
  final List<GuideDefinition> guides;
  final Widget child;
  final GuideStateStore? store;
  final GuideEventSink? onEvent;
  static GuideController? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<_GuideInherited>()?.controller;
  @override
  State<GuideScope> createState() => _GuideScopeState();
}

class _GuideScopeState extends State<GuideScope> {
  GuideController? controller;
  void configure() {
    controller?.dispose();
    controller = widget.account == null || !guideFeatureEnabled
        ? null
        : GuideController(
            account: widget.account!,
            guides: widget.guides,
            store: widget.store,
            onEvent: widget.onEvent,
          );
  }

  @override
  void initState() {
    super.initState();
    configure();
  }

  @override
  void didUpdateWidget(GuideScope oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.account != widget.account) configure();
  }

  @override
  void dispose() {
    controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      _GuideInherited(controller: controller, child: widget.child);
}

class _GuideInherited extends InheritedWidget {
  const _GuideInherited({required this.controller, required super.child});
  final GuideController? controller;
  @override
  bool updateShouldNotify(_GuideInherited oldWidget) =>
      oldWidget.controller != controller;
}

/// Suppresses targets until their screen has finished loading/onboarding.
class GuideAvailability extends InheritedWidget {
  const GuideAvailability({
    super.key,
    required this.enabled,
    required super.child,
  });
  final bool enabled;
  static bool of(BuildContext context) =>
      context
          .dependOnInheritedWidgetOfExactType<GuideAvailability>()
          ?.enabled ??
      true;
  @override
  bool updateShouldNotify(GuideAvailability oldWidget) =>
      enabled != oldWidget.enabled;
}

class GuideTarget extends StatefulWidget {
  const GuideTarget({
    super.key,
    required this.id,
    required this.child,
    this.enabled = true,
  });
  final String id;
  final Widget child;
  final bool enabled;
  @override
  State<GuideTarget> createState() => _GuideTargetState();
}

class _GuideTargetState extends State<GuideTarget> {
  GuideController? controller;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    controller?.unregister(widget.id, context);
    controller = GuideScope.maybeOf(context);
    if (widget.enabled && GuideAvailability.of(context))
      controller?.register(widget.id, context);
  }

  @override
  void didUpdateWidget(GuideTarget oldWidget) {
    super.didUpdateWidget(oldWidget);
    controller?.unregister(oldWidget.id, context);
    if (widget.enabled && GuideAvailability.of(context))
      controller?.register(widget.id, context);
  }

  @override
  void dispose() {
    controller?.unregister(widget.id, context);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class GuideOverlay extends StatefulWidget {
  const GuideOverlay({
    super.key,
    required this.controller,
    required this.guide,
  });
  final GuideController controller;
  final GuideDefinition guide;
  @override
  State<GuideOverlay> createState() => _GuideOverlayState();
}

class _GuideOverlayState extends State<GuideOverlay>
    with WidgetsBindingObserver {
  int index = -1;
  Rect? rect;
  bool moving = false;
  bool closing = false;
  final GlobalKey _surface = GlobalKey();
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => move(1));
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeMetrics() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && index >= 0) measure();
    });
  }

  void measure() {
    final target = widget.controller.target(
      widget.guide,
      widget.guide.steps[index],
    );
    final box = target?.findRenderObject() as RenderBox?;
    final surface = _surface.currentContext?.findRenderObject() as RenderBox?;
    if (!mounted || box == null || surface == null) return;
    setState(
      () => rect =
          (surface.globalToLocal(box.localToGlobal(Offset.zero)) & box.size)
              .inflate(5),
    );
  }

  Future<void> move(int direction) async {
    if (moving || closing) return;
    setState(() => moving = true);
    var next = index + direction;
    while (next >= 0 && next < widget.guide.steps.length) {
      final target = widget.controller.target(
        widget.guide,
        widget.guide.steps[next],
      );
      if (target != null) {
        final media = MediaQuery.of(context);
        try {
          await Scrollable.ensureVisible(
            target,
            alignment: .25,
            duration: Duration(
              milliseconds:
                  media.disableAnimations || media.accessibleNavigation
                  ? 0
                  : 180,
            ),
          );
        } catch (_) {
          /* Detached scrollables can disappear during a refresh. */
        }
        if (!mounted || closing) return;
        await WidgetsBinding.instance.endOfFrame;
        if (!mounted || closing) return;
        if (widget.controller.target(widget.guide, widget.guide.steps[next]) ==
            null) {
          next += direction;
          continue;
        }
        setState(() {
          index = next;
          moving = false;
        });
        measure();
        widget.controller.event(
          'guide_step_viewed',
          widget.guide.id,
          index + 1,
        );
        return;
      }
      next += direction;
    }
    if (direction > 0) {
      await close(false);
    } else {
      setState(() => moving = false);
    }
  }

  Future<void> close(bool skipped) async {
    if (closing) return;
    closing = true;
    // Dismiss immediately even when persistence is unavailable or slow.
    unawaited(widget.controller.finish(widget.guide, skipped));
    if (mounted) {
      final route = ModalRoute.of(context);
      if (route?.isCurrent == true) {
        Navigator.of(context).pop();
      } else if (route?.navigator != null) {
        route!.navigator!.removeRoute(route);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final step = index < 0 ? null : widget.guide.steps[index];
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) close(true);
      },
      child: Material(
        type: MaterialType.transparency,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final screenSafe = Rect.fromLTRB(
              12 + media.padding.left,
              12 + media.padding.top,
              constraints.maxWidth - 12 - media.padding.right,
              constraints.maxHeight -
                  12 -
                  math.max(media.padding.bottom, media.viewInsets.bottom),
            );
            final safe = Rect.fromLTRB(
              screenSafe.left,
              screenSafe.top,
              screenSafe.right,
              math.max(screenSafe.top + 80, screenSafe.bottom - 88),
            );
            final hole = rect?.intersect(screenSafe);
            final above = hole == null
                ? 0.0
                : math.min(
                    safe.height,
                    math.max(0.0, hole.top - safe.top - 12),
                  );
            final below = hole == null
                ? safe.height
                : math.max(0.0, safe.bottom - hole.bottom - 12);
            // Compact screens use a scrollable card in the larger free area.
            final useAbove = above > below;
            final available = math.max(above, below);
            final minimumCardHeight = media.textScaler.scale(16) > 24
                ? 220
                : 148;
            final spotlight = available >= minimumCardHeight ? hole : null;
            final top = spotlight == null || useAbove
                ? safe.top
                : spotlight.bottom + 12;
            final maxHeight = spotlight == null ? safe.height : available;
            return Stack(
              key: _surface,
              fit: StackFit.expand,
              children: [
                ExcludeSemantics(
                  child: GestureDetector(
                    onTap: () {},
                    behavior: HitTestBehavior.opaque,
                    child: spotlight == null
                        ? CustomPaint(painter: _SpotlightPainter(null))
                        : TweenAnimationBuilder<Rect?>(
                            tween: RectTween(begin: spotlight, end: spotlight),
                            duration: Duration(
                              milliseconds:
                                  media.disableAnimations ||
                                      media.accessibleNavigation
                                  ? 0
                                  : 160,
                            ),
                            builder: (_, value, _) =>
                                CustomPaint(painter: _SpotlightPainter(value)),
                          ),
                  ),
                ),
                if (step != null)
                  AnimatedPositioned(
                    duration: Duration(
                      milliseconds:
                          media.disableAnimations || media.accessibleNavigation
                          ? 0
                          : 160,
                    ),
                    curve: Curves.easeOutCubic,
                    left: safe.left + math.max(0, (safe.width - 348) / 2),
                    top: top,
                    width: math.min(348, safe.width),
                    child: ConstrainedBox(
                      constraints: BoxConstraints(maxHeight: maxHeight),
                      child: GuideTooltip(
                        guide: widget.guide,
                        index: index,
                        onSkip: () => close(true),
                        onBack: moving ? null : () => move(-1),
                        onNext: moving ? null : () => move(1),
                      ),
                    ),
                  ),
                if (step == null)
                  SafeArea(
                    child: Align(
                      alignment: Alignment.topRight,
                      child: TextButton(
                        style: TextButton.styleFrom(
                          foregroundColor: GuideColors.text,
                          minimumSize: const Size(48, 48),
                        ),
                        onPressed: () => close(true),
                        child: const Text('Skip'),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SpotlightPainter extends CustomPainter {
  _SpotlightPainter(this.rect);
  final Rect? rect;
  @override
  void paint(Canvas canvas, Size size) {
    final path = Path()..addRect(Offset.zero & size);
    if (rect != null && !rect!.isEmpty) {
      path.addRRect(RRect.fromRectAndRadius(rect!, const Radius.circular(12)));
      path.fillType = PathFillType.evenOdd;
    }
    canvas.drawPath(
      path,
      Paint()..color = GuideColors.scrim.withValues(alpha: .54),
    );
    if (rect != null && !rect!.isEmpty) {
      final ring = RRect.fromRectAndRadius(rect!, const Radius.circular(12));
      for (final (width, opacity) in [(10.0, .05), (5.0, .10), (1.5, .88)]) {
        canvas.drawRRect(
          ring,
          Paint()
            ..color = GuideColors.spotlight.withValues(alpha: opacity)
            ..style = PaintingStyle.stroke
            ..strokeWidth = width,
        );
      }
    }
  }

  @override
  bool shouldRepaint(_SpotlightPainter oldDelegate) => rect != oldDelegate.rect;
}

class GuideSettingsTile extends StatelessWidget {
  const GuideSettingsTile({super.key});
  @override
  Widget build(BuildContext context) {
    final controller = GuideScope.maybeOf(context);
    if (controller == null) return const SizedBox.shrink();
    return ListTile(
      leading: const Icon(Icons.help_outline_rounded),
      title: const Text('Replay guides'),
      subtitle: const Text('Choose a guide or turn automatic guides off'),
      onTap: () => showDialog<void>(
        context: context,
        builder: (_) => _GuideSettings(controller: controller),
      ),
    );
  }
}

class _GuideSettings extends StatefulWidget {
  const _GuideSettings({required this.controller});
  final GuideController controller;
  @override
  State<_GuideSettings> createState() => _GuideSettingsState();
}

class _GuideSettingsState extends State<_GuideSettings> {
  bool busy = false;
  String? message;
  Future<void> run(Future<void> Function() action, String success) async {
    setState(() => busy = true);
    try {
      await action();
      if (mounted) setState(() => message = success);
    } catch (_) {
      if (mounted)
        setState(
          () => message = 'Could not save guide preferences. Please try again.',
        );
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Replay guides'),
    content: SizedBox(
      width: 420,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Choose a guide, then open the location shown below. It will replay when the controls are ready.',
            ),
            if (message != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Semantics(liveRegion: true, child: Text(message!)),
              ),
            ListTile(
              title: const Text('Replay all guides'),
              onTap: busy
                  ? null
                  : () => run(
                      () => widget.controller.replay(widget.controller.guides),
                      'Guides are ready to replay when you visit each feature.',
                    ),
            ),
            for (final guide in widget.controller.guides)
              ListTile(
                title: Text(guide.title),
                subtitle: Text(guide.destination),
                onTap: busy
                    ? null
                    : () => run(
                        () => widget.controller.replay([guide]),
                        'Ready. Open ${guide.destination} to replay.',
                      ),
              ),
            ListTile(
              title: const Text('Turn guides off'),
              onTap: busy
                  ? null
                  : () => run(
                      () => widget.controller.setDisabled(true),
                      'Automatic guides are off. Help remains available.',
                    ),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () {
          Navigator.pop(context);
          widget.controller.schedule();
        },
        child: const Text('Done'),
      ),
    ],
  );
}
