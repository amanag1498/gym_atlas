import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:flutter_trainer_app/core/theme/app_theme.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';
import 'package:flutter_trainer_app/src/core/models.dart';
import 'package:flutter_trainer_app/src/core/token_storage.dart';
import 'package:flutter_trainer_app/src/features/auth/auth_service.dart';
import 'package:flutter_trainer_app/src/features/auth/session_controller.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_home_screen.dart';

const _previewData = <String, dynamic>{
  'context': <String, dynamic>{
    'user': <String, dynamic>{
      'id': 11,
      'name': 'Riya Kapoor',
      'trainer_onboarding_completed': true,
    },
    'trainer_profile': <String, dynamic>{
      'name': 'Riya Kapoor',
      'specialization': 'Strength & Conditioning',
      'experience_years': 7,
      'client_count': 12,
      'assigned_branch': <String, dynamic>{'name': 'Central Studio'},
    },
    'assigned_gym': <String, dynamic>{'name': 'Atlas Fitness Club'},
  },
  'tasks': <String, dynamic>{
    'today_clients': 4,
    'pending_follow_ups': 3,
    'active_plans': 10,
    'unread_messages': 2,
  },
  'members': <Map<String, dynamic>>[
    <String, dynamic>{
      'member_id': 1,
      'member': <String, dynamic>{
        'id': 1,
        'name': 'Aarav Sharma',
        'email': 'aarav@example.com',
      },
      'membership_status': 'active',
      'fitness_goal': 'Build strength',
      'progress_summary': <String, dynamic>{
        'weight_kg': 74.2,
        'latest_note': 'New personal best',
      },
    },
    <String, dynamic>{
      'member_id': 2,
      'member': <String, dynamic>{
        'id': 2,
        'name': 'Meera Nair',
        'email': 'meera@example.com',
      },
      'membership_status': 'active',
      'fitness_goal': 'Improve endurance',
      'progress_summary': <String, dynamic>{
        'weight_kg': 61.8,
        'latest_note': 'Consistency improving',
      },
    },
    <String, dynamic>{
      'member_id': 3,
      'member': <String, dynamic>{
        'id': 3,
        'name': 'Kabir Singh',
        'email': 'kabir@example.com',
      },
      'membership_status': 'active',
      'fitness_goal': 'Mobility',
    },
  ],
  'today_clients': <Map<String, dynamic>>[
    <String, dynamic>{
      'member_id': 1,
      'member': <String, dynamic>{'name': 'Aarav Sharma'},
      'scheduled_at': '2026-07-28T07:00:00Z',
      'workout_name': 'Upper Body',
    },
    <String, dynamic>{
      'member_id': 2,
      'member': <String, dynamic>{'name': 'Meera Nair'},
      'scheduled_at': '2026-07-28T09:00:00Z',
      'workout_name': 'Conditioning',
    },
  ],
  'follow_ups': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 201,
      'member_id': 2,
      'member': <String, dynamic>{'name': 'Meera Nair'},
      'note': 'Review weekly endurance target',
      'follow_up_date': '2026-07-28',
    },
  ],
  'templates': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 301,
      'name': 'Strength Foundation',
      'days_count': 4,
      'exercise_count': 18,
    },
    <String, dynamic>{
      'id': 302,
      'name': 'Mobility Reset',
      'days_count': 2,
      'exercise_count': 8,
    },
  ],
  'plans': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 401,
      'name': 'Aarav — Strength',
      'member_id': 1,
      'member': <String, dynamic>{'name': 'Aarav Sharma'},
      'status': 'active',
      'days_count': 4,
    },
    <String, dynamic>{
      'id': 402,
      'name': 'Meera — Conditioning',
      'member_id': 2,
      'member': <String, dynamic>{'name': 'Meera Nair'},
      'status': 'active',
      'days_count': 3,
    },
  ],
  'exercises': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 501,
      'name': 'Barbell Squat',
      'category': 'Strength',
    },
    <String, dynamic>{
      'id': 502,
      'name': 'Dumbbell Press',
      'category': 'Strength',
    },
  ],
  'notifications': <Map<String, dynamic>>[
    <String, dynamic>{
      'id': 601,
      'type': 'member_progress',
      'title': 'Progress update',
      'body': 'Aarav logged a new personal best.',
      'read_at': null,
      'created_at': '2026-07-28T08:10:00Z',
    },
    <String, dynamic>{
      'id': 602,
      'type': 'follow_up',
      'title': 'Follow-up due',
      'body': 'Review Meera’s weekly endurance target.',
      'read_at': null,
      'created_at': '2026-07-28T07:45:00Z',
    },
  ],
  'chat_conversations': <Map<String, dynamic>>[
    <String, dynamic>{
      'member_id': 1,
      'member': <String, dynamic>{'name': 'Aarav Sharma'},
      'last_message': <String, dynamic>{'body': 'Completed today’s session!'},
      'unread_count': 1,
    },
  ],
};

TrainerSessionController _session() {
  final client = TrainerApiClient(token: null, onUnauthorized: () async {});
  final session = TrainerSessionController(
    storage: const TrainerTokenStorage(),
    apiClient: client,
    authService: TrainerAuthService(client),
  );
  session
    ..user = const TrainerUser(
      id: 11,
      name: 'Riya Kapoor',
      email: 'riya@example.com',
      activeRole: 'trainer',
      isActive: true,
      roles: <String>['trainer'],
      permissions: <String>[],
    )
    ..token = 'store-preview'
    ..initializing = false;
  return session;
}

Widget _app({required int initialIndex}) {
  return ChangeNotifierProvider<TrainerSessionController>.value(
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
      home: TrainerHomeScreen(
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

  const screenshots = <int, String>{
    0: '01-dashboard',
    1: '02-clients',
    2: '03-workout-builder',
    4: '04-notifications',
  };

  for (final entry in screenshots.entries) {
    testWidgets('trainer ${entry.value} store screenshot', (tester) async {
      await _setPhoneSurface(tester);
      await tester.pumpWidget(_app(initialIndex: entry.key));
      await tester.pump(const Duration(milliseconds: 100));
      await tester.pump(const Duration(seconds: 1));
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile(
          '../../play_store_assets/trainer/screenshots-real-ui/${entry.value}.png',
        ),
      );
    });
  }
}
