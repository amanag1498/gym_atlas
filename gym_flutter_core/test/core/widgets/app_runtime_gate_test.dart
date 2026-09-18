import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  test('parses maintenance and force-upgrade app config', () {
    final config = AppRuntimeConfig.fromJson(<String, dynamic>{
      'maintenance_mode_enabled': true,
      'maintenance_title': 'Back soon',
      'maintenance_message': 'Planned maintenance is in progress.',
      'update_required': true,
      'update_title': 'Update Atlas',
      'update_message': 'Install the latest version.',
      'minimum_version': '1.2.0',
      'minimum_build_number': 20,
      'store_url': 'https://example.com/store',
    });

    expect(config.maintenanceEnabled, isTrue);
    expect(config.maintenanceTitle, 'Back soon');
    expect(config.updateRequired, isTrue);
    expect(config.minimumVersion, '1.2.0');
    expect(config.minimumBuild, 20);
    expect(config.storeUrl, 'https://example.com/store');
  });

  test('uses safe copy and build defaults for incomplete config', () {
    final config = AppRuntimeConfig.fromJson(const <String, dynamic>{});

    expect(config.maintenanceEnabled, isFalse);
    expect(config.updateRequired, isFalse);
    expect(config.minimumBuild, 1);
    expect(config.minimumVersion, 'latest');
    expect(config.storeUrl, isNull);
  });
}
