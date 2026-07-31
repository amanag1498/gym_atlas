import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:flutter_member_app/core/theme/app_theme.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/core/models.dart';
import 'package:flutter_member_app/src/core/secure_storage_service.dart';
import 'package:flutter_member_app/src/features/auth/auth_service.dart';
import 'package:flutter_member_app/src/features/auth/session_controller.dart';
import 'package:flutter_member_app/src/features/member/member_home_screen.dart';

const _previewData = <String, dynamic>{
  'context': <String, dynamic>{
    'user_state': 'gym_member_with_trainer',
    'user': <String, dynamic>{
      'id': 1,
      'name': 'Aarav Sharma',
      'member_onboarding_completed': true,
    },
    'member_profile': <String, dynamic>{
      'member_onboarding_completed': true,
      'fitness_goal': 'Build strength',
    },
    'current_membership': <String, dynamic>{
      'status': 'active',
      'plan_name': 'Elite Monthly',
      'gym_name': 'Atlas Fitness Club',
      'expiry_date': '2026-08-28',
      'due_amount': 0,
    },
    'trainer_connection': <String, dynamic>{
      'assigned_trainer': <String, dynamic>{
        'id': 11,
        'name': 'Riya Kapoor',
        'specialization': 'Strength Coach',
      },
    },
    'attendance_status': <String, dynamic>{
      'checked_in_today': true,
      'total_visits': 18,
    },
    'steps': <String, dynamic>{
      'today': 8420,
      'goal': 10000,
      'progressPercent': 84,
      'distanceKm': 6.4,
      'calories': 332,
      'streakDays': 5,
      'lastSyncedAt': '2026-07-28T08:30:00Z',
    },
  },
  'attendance': <Map<String, dynamic>>[
    <String, dynamic>{
      'checked_in_at': '2026-07-28T06:45:00Z',
      'gym_name': 'Atlas Fitness Club',
    },
  ],
  'plans': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 101,
      'name': 'Strength Foundation',
      'status': 'active',
      'days_count': 4,
      'exercise_count': 18,
      'trainer': <String, dynamic>{'name': 'Riya Kapoor'},
      'days': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 1001,
          'name': 'Upper Body',
          'day_number': 1,
          'exercises_count': 5,
        },
        <String, dynamic>{
          'id': 1002,
          'name': 'Lower Body',
          'day_number': 2,
          'exercises_count': 5,
        },
      ],
    },
    <String, dynamic>{
      'id': 102,
      'name': 'Mobility Reset',
      'status': 'active',
      'days_count': 2,
      'exercise_count': 8,
      'trainer': <String, dynamic>{'name': 'Riya Kapoor'},
    },
  ],
  'history': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 501,
      'name': 'Upper Body',
      'completed_at': '2026-07-27T07:30:00Z',
      'duration_minutes': 52,
      'total_volume': 4280,
    },
    <String, dynamic>{
      'id': 502,
      'name': 'Lower Body',
      'completed_at': '2026-07-25T07:15:00Z',
      'duration_minutes': 58,
      'total_volume': 5120,
    },
  ],
  'progress_summary': <String, dynamic>{
    'latest_weight_log': <String, dynamic>{
      'weight_kg': 74.2,
      'logged_at': '2026-07-27',
    },
    'workout_streak': 5,
  },
  'logbook_summary': <String, dynamic>{
    'total_volume': 18450,
    'personal_records': <Map<String, dynamic>>[
      <String, dynamic>{
        'exercise_name': 'Barbell Squat',
        'weight_kg': 105,
        'achieved_at': '2026-07-25',
      },
    ],
    'session_count': 14,
  },
  'notifications': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 1,
      'title': 'Workout updated',
      'body': 'Riya updated your Strength Foundation plan.',
      'read_at': null,
      'created_at': '2026-07-28T08:00:00Z',
    },
  ],
};

MemberSessionController _session() {
  final client = MemberApiClient();
  final session = MemberSessionController(
    storage: const SecureStorageService(),
    apiClient: client,
    authService: AuthService(client),
  );
  session
    ..user = const MemberUser(
      id: 1,
      name: 'Aarav Sharma',
      email: 'aarav@example.com',
      activeRole: 'member',
      isActive: true,
      roles: <String>['member'],
    )
    ..token = 'store-preview'
    ..initializing = false;
  return session;
}

Widget _app({required int initialIndex}) {
  return ChangeNotifierProvider<MemberSessionController>.value(
    value: _session(),
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: AppTheme.build(),
      builder: (context, child) {
        final mediaQuery = MediaQuery.of(context);
        return MediaQuery(
          data: mediaQuery.copyWith(
            padding: mediaQuery.padding.copyWith(top: 24),
          ),
          child: child!,
        );
      },
      home: MemberHomeScreen(
        initialIndex: initialIndex,
        storePreviewData: _previewData,
      ),
    ),
  );
}

Future<void> _setPhoneSurface(WidgetTester tester) async {
  tester.view
    ..physicalSize = const Size(1080, 1920)
    ..devicePixelRatio = 2;
  addTearDown(tester.view.reset);
}

Future<ByteData> _fontData(String path) async {
  final bytes = await File(path).readAsBytes();
  return ByteData.view(bytes.buffer);
}

Future<void> _loadFonts() async {
  final outfit = FontLoader('Outfit');
  for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold']) {
    outfit.addFont(_fontData('assets/fonts/Outfit-$weight.ttf'));
  }
  await outfit.load();

  final flutterRoot =
      Platform.environment['FLUTTER_ROOT'] ??
      '/Users/amanagarwal/Desktop/flutter';
  final materialIcons = FontLoader('MaterialIcons')
    ..addFont(
      _fontData(
        '$flutterRoot/bin/cache/artifacts/material_fonts/'
        'MaterialIcons-Regular.otf',
      ),
    );
  await materialIcons.load();
}

void main() {
  setUpAll(_loadFonts);

  testWidgets('member dashboard store screenshot', (tester) async {
    await _setPhoneSurface(tester);
    await tester.pumpWidget(_app(initialIndex: 0));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(seconds: 1));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        '../../play_store_assets/member/screenshots-real-ui/01-dashboard.png',
      ),
    );
  });

  testWidgets('member activity store screenshot', (tester) async {
    await _setPhoneSurface(tester);
    await tester.pumpWidget(_app(initialIndex: 0));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(seconds: 1));
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -620));
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump(const Duration(milliseconds: 500));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        '../../play_store_assets/member/screenshots-real-ui/02-activity.png',
      ),
    );
  });

  testWidgets('member workouts store screenshot', (tester) async {
    await _setPhoneSurface(tester);
    await tester.pumpWidget(_app(initialIndex: 1));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(seconds: 1));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        '../../play_store_assets/member/screenshots-real-ui/03-workouts.png',
      ),
    );
  });

  testWidgets('member workout history store screenshot', (tester) async {
    await _setPhoneSurface(tester);
    await tester.pumpWidget(_app(initialIndex: 1));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(seconds: 1));
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -560));
    await tester.pump(const Duration(milliseconds: 500));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        '../../play_store_assets/member/screenshots-real-ui/04-workout-history.png',
      ),
    );
  });
}
