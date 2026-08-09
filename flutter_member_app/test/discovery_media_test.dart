import 'package:flutter_member_app/src/features/member/discovery_media.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('gym hero falls back from cover to uploaded gallery before logo', () {
    expect(
      gymDiscoveryHeroImage({
        'photo_urls': ['/storage/gyms/gallery.jpg'],
        'logo_url': '/storage/gyms/logo.jpg',
      }),
      '/storage/gyms/gallery.jpg',
    );
  });

  test('gym gallery accepts relation resources and removes duplicates', () {
    expect(
      gymDiscoveryGallery({
        'cover_image_url': '/storage/cover.jpg',
        'photo_urls': ['/storage/gallery.jpg'],
        'gallery_photos': [
          {'image_url': '/storage/gallery.jpg'},
          {'thumbnail_url': '/storage/second-thumb.jpg'},
        ],
      }),
      [
        '/storage/cover.jpg',
        '/storage/gallery.jpg',
        '/storage/second-thumb.jpg',
      ],
    );
  });

  test('trainer photo uses canonical profile photo with legacy fallbacks', () {
    expect(
      trainerDiscoveryPhotoUrl({
        'profile_photo_url': '/storage/trainers/profile.jpg',
        'photo_url': '/storage/trainers/legacy.jpg',
      }),
      '/storage/trainers/profile.jpg',
    );
    expect(
      trainerDiscoveryPhotoUrl({'photo_url': '/storage/trainers/legacy.jpg'}),
      '/storage/trainers/legacy.jpg',
    );
  });
}
