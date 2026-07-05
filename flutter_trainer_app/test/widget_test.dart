import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/models.dart';

void main() {
  test('TrainerUser preserves trainer role and permissions round trip', () {
    final user = TrainerUser.fromJson(const {
      'id': 13,
      'name': 'Sparsh Agarwal',
      'email': 'sparsh@example.com',
      'active_role': 'trainer',
      'is_active': true,
      'roles': ['member', 'trainer'],
      'permissions': ['trainer.view', 'workout_plan.manage'],
    });

    expect(user.isTrainerRole, isTrue);
    expect(user.permissions, contains('workout_plan.manage'));
    expect(user.toJson(), containsPair('active_role', 'trainer'));
  });
}
