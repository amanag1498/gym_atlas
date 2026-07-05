import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:image_picker/image_picker.dart';

class TrainerPickedPhoto {
  const TrainerPickedPhoto({required this.bytes, required this.filename});

  final Uint8List bytes;
  final String filename;
}

class TrainerPhotoPicker {
  TrainerPhotoPicker({ImagePicker? picker}) : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  Future<TrainerPickedPhoto?> pickCompressedProfilePhoto() async {
    final picked = await _picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
      maxHeight: 1200,
      imageQuality: 88,
    );
    if (picked == null) {
      return null;
    }

    final compressed = await FlutterImageCompress.compressWithFile(
      picked.path,
      minWidth: 720,
      minHeight: 720,
      quality: 78,
      format: CompressFormat.jpeg,
      keepExif: false,
    );
    final bytes = compressed ?? await picked.readAsBytes();

    return TrainerPickedPhoto(
      bytes: bytes,
      filename: 'trainer_profile_${DateTime.now().millisecondsSinceEpoch}.jpg',
    );
  }

  Future<TrainerPickedPhoto?> pickCompressedCertificationImage() async {
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      withData: true,
    );
    if (picked == null) {
      return null;
    }

    final file = picked.files.single;
    final extension = (file.extension ?? '').toLowerCase();
    final filename = file.name.isNotEmpty
        ? file.name
        : 'trainer_certificate_${DateTime.now().millisecondsSinceEpoch}.${extension.isEmpty ? 'jpg' : extension}';

    if (extension == 'pdf') {
      final bytes = file.bytes;
      if (bytes == null) {
        return null;
      }

      return TrainerPickedPhoto(bytes: bytes, filename: filename);
    }

    Uint8List? bytes = file.bytes;
    if (file.path != null) {
      final compressed = await FlutterImageCompress.compressWithFile(
        file.path!,
        minWidth: 1100,
        minHeight: 1100,
        quality: 82,
        format: CompressFormat.jpeg,
        keepExif: false,
      );
      bytes = compressed ?? bytes;
    }

    if (bytes == null) {
      return null;
    }

    return TrainerPickedPhoto(bytes: bytes, filename: filename);
  }
}
