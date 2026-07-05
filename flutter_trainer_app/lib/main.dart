import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/widgets.dart';

import 'src/trainer_app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  runApp(const TrainerApp());
}
