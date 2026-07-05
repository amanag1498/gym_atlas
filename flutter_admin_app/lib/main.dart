import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/widgets.dart';

import 'src/app/admin_app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  AdminApp.bootstrap();
}
