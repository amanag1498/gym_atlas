import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/guides.dart';

const guide = GuideDefinition('test_v1', 'Test guide', 'Home', [
  GuideStep('first', 'First action', 'Learn the first action.'),
  GuideStep('missing', 'Missing action', 'This should be skipped.'),
  GuideStep('last', 'Last action', 'Learn the last action.'),
]);
Widget harness({
  String? account = 'member:1',
  bool ready = true,
  bool offscreen = false,
  bool reduced = false,
  double scale = 1,
  GuideEventSink? events,
}) => MaterialApp(
  builder: (context, child) => MediaQuery(
    data: MediaQuery.of(context).copyWith(
      disableAnimations: reduced,
      accessibleNavigation: reduced,
      textScaler: TextScaler.linear(scale),
      padding: const EdgeInsets.only(top: 24, bottom: 24),
    ),
    child: GuideScope(
      account: account,
      guides: const [guide],
      onEvent: events,
      child: child!,
    ),
  ),
  home: Scaffold(
    body: GuideAvailability(
      enabled: ready,
      child: SingleChildScrollView(
        child: Column(
          children: [
            const GuideTarget(
              id: 'test_v1/first',
              child: SizedBox(height: 64, child: Text('First control')),
            ),
            SizedBox(height: offscreen ? 1100 : 50),
            const GuideTarget(
              id: 'test_v1/last',
              child: SizedBox(height: 64, child: Text('Last control')),
            ),
            const GuideSettingsTile(),
          ],
        ),
      ),
    ),
  ),
);
Future<void> start(WidgetTester tester) async {
  await tester.pump(const Duration(milliseconds: 700));
  await tester.pumpAndSettle();
}

class _FailingStore extends GuideStateStore {
  @override
  Future<bool> disabled(String account) async =>
      throw StateError('Storage unavailable');
}

void main() {
  testWidgets('storage failures never block the app', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        builder: (_, child) => GuideScope(
          account: 'member:failure',
          guides: const [guide],
          store: _FailingStore(),
          child: child!,
        ),
        home: const Scaffold(
          body: GuideTarget(id: 'test_v1/first', child: Text('Usable control')),
        ),
      ),
    );
    await start(tester);
    expect(find.byType(GuideOverlay), findsNothing);
    expect(find.text('Usable control'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'keyboard closes, unrelated taps are blocked, system back skips',
    (tester) async {
      var taps = 0;
      await tester.pumpWidget(
        MaterialApp(
          builder: (_, child) => GuideScope(
            account: 'member:keyboard',
            guides: const [guide],
            child: child!,
          ),
          home: Scaffold(
            body: Column(
              children: [
                const TextField(),
                const GuideTarget(
                  id: 'test_v1/first',
                  child: SizedBox(height: 48, child: Text('Target')),
                ),
                const Spacer(),
                TextButton(
                  onPressed: () => taps++,
                  child: const Text('Unrelated action'),
                ),
              ],
            ),
          ),
        ),
      );
      await tester.showKeyboard(find.byType(TextField));
      await start(tester);
      expect(tester.testTextInput.isVisible, isFalse);
      await tester.tapAt(tester.getCenter(find.text('Unrelated action')));
      await tester.pump();
      expect(taps, 0);
      final tooltip = tester.getRect(find.byType(BackdropFilter));
      expect(tooltip.bottom, lessThanOrEqualTo(512));
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.byType(GuideOverlay), findsNothing);
    },
  );

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));
  test('account, role, version isolation and metadata persistence', () async {
    final store = GuideStateStore();
    await store.mark('member:1', guide, 'completed');
    expect(await store.seen('member:1', guide), isTrue);
    expect(await store.seen('member:2', guide), isFalse);
    expect(await store.seen('trainer:1', guide), isFalse);
    expect(
      await store.seen(
        'member:1',
        const GuideDefinition('test_v2', 'Test', 'Home', []),
      ),
      isFalse,
    );
    final data =
        jsonDecode(
              (await store.storage.read(key: store.key('member:1', guide.id)))!,
            )
            as Map;
    expect(
      data.keys,
      unorderedEquals(['guide_id', 'version', 'status', 'last_shown_at']),
    );
    expect(data['status'], 'completed');
    expect(data['version'], 1);
    await store.setDisabled('member:1', true);
    expect(await store.disabled('member:2'), isFalse);
    await store.reset('member:1', guide);
    expect(await store.seen('member:1', guide), isFalse);
  });
  testWidgets('next/back skip missing targets; completion suppresses return', (
    tester,
  ) async {
    final events = <String>[];
    await tester.pumpWidget(
      harness(events: (event, id, step) => events.add('$event:$id:$step')),
    );
    await start(tester);
    expect(find.text('First action'), findsOneWidget);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Last action'), findsOneWidget);
    expect(find.text('3 / 3'), findsOneWidget);
    expect(
      find.bySemanticsLabel('3 of 3. Last action. Learn the last action.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Back'));
    await tester.pumpAndSettle();
    expect(find.text('First action'), findsOneWidget);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Finish'));
    await tester.pumpAndSettle();
    expect(await GuideStateStore().seen('member:1', guide), isTrue);
    expect(events, contains('guide_completed:test_v1:null'));
    await tester.pumpWidget(const SizedBox());
    await tester.pumpWidget(harness());
    await start(tester);
    expect(find.byType(GuideOverlay), findsNothing);
  });
  testWidgets('skip persists; account change and logout clear active tour', (
    tester,
  ) async {
    await tester.pumpWidget(harness());
    await start(tester);
    await tester.tap(find.text('Skip'));
    await tester.pumpAndSettle();
    final store = GuideStateStore();
    final raw = await store.storage.read(key: store.key('member:1', guide.id));
    expect(jsonDecode(raw!)['status'], 'skipped');
    await tester.pumpWidget(harness(account: 'member:2'));
    await start(tester);
    expect(find.text('First action'), findsOneWidget);
    await tester.pumpWidget(harness(account: null));
    await tester.pumpAndSettle();
    expect(find.byType(GuideOverlay), findsNothing);
  });
  testWidgets('auth, readiness and disabled gates', (tester) async {
    await tester.pumpWidget(harness(account: null));
    await start(tester);
    expect(find.byType(GuideOverlay), findsNothing);
    await tester.pumpWidget(harness(ready: false));
    await start(tester);
    expect(find.byType(GuideOverlay), findsNothing);
    await GuideStateStore().setDisabled('member:1', true);
    await tester.pumpWidget(harness());
    await start(tester);
    expect(find.byType(GuideOverlay), findsNothing);
  });
  testWidgets('scrolls to offscreen target', (tester) async {
    await tester.pumpWidget(harness(offscreen: true));
    await start(tester);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Last action'), findsOneWidget);
    final rect = tester.getRect(find.text('Last control'));
    expect(rect.top, greaterThanOrEqualTo(0));
    expect(rect.bottom, lessThan(600));
    expect(tester.takeException(), isNull);
  });
  testWidgets('small screen, large text, reduced motion and live semantics', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 480));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final semantics = tester.ensureSemantics();

    await tester.pumpWidget(harness(reduced: true, scale: 2));
    await start(tester);
    expect(
      find.bySemanticsLabel('1 of 3. First action. Learn the first action.'),
      findsOneWidget,
    );
    semantics.dispose();
    expect(find.text('Skip').hitTestable(), findsOneWidget);
    await tester.tap(find.text('Skip'));
    await tester.pumpAndSettle();
    expect(find.byType(GuideOverlay), findsNothing);
    expect(tester.takeException(), isNull);
  });
  testWidgets('settings replay re-enables completed guide', (tester) async {
    await GuideStateStore().mark('member:1', guide, 'completed');
    await tester.pumpWidget(harness());
    await start(tester);
    await tester.tap(find.text('Replay guides'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Test guide'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Done'));
    await start(tester);
    expect(find.text('First action'), findsOneWidget);
  });
  testWidgets('all missing targets leave app usable', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        builder: (_, child) => GuideScope(
          account: 'member:1',
          guides: const [guide],
          child: child!,
        ),
        home: const Scaffold(body: Text('Usable app')),
      ),
    );
    await start(tester);
    expect(find.byType(GuideOverlay), findsNothing);
    expect(find.text('Usable app'), findsOneWidget);
  });
}
