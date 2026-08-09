String? gymDiscoveryHeroImage(Map<String, dynamic> gym) {
  final cover = _nonEmpty(gym['cover_image_url']);
  if (cover != null) return cover;

  final photos = gymDiscoveryGallery(gym);
  if (photos.isNotEmpty) return photos.first;

  return _nonEmpty(gym['logo_url']);
}

List<String> gymDiscoveryGallery(Map<String, dynamic> gym) {
  final urls = <String>[];
  void add(dynamic value) {
    final url = _nonEmpty(value);
    if (url != null && !urls.contains(url)) urls.add(url);
  }

  add(gym['cover_image_url']);
  for (final item in gym['photo_urls'] as List<dynamic>? ?? const []) {
    add(item);
  }
  for (final item in gym['gallery_photos'] as List<dynamic>? ?? const []) {
    if (item is Map) {
      add(item['image_url'] ?? item['thumbnail_url'] ?? item['image_path']);
    } else {
      add(item);
    }
  }
  return urls;
}

String? trainerDiscoveryPhotoUrl(Map<String, dynamic> trainer) {
  for (final key in const [
    'profile_photo_url',
    'photo_url',
    'photo',
    'avatar',
  ]) {
    final url = _nonEmpty(trainer[key]);
    if (url != null) return url;
  }
  return null;
}

String? _nonEmpty(dynamic value) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? null : text;
}
