import 'package:flutter/material.dart';

import '../../app/app.dart';

class AdminApp extends StatelessWidget {
  const AdminApp({super.key});

  static void bootstrap() {
    runApp(const GymAdminApp());
  }

  @override
  Widget build(BuildContext context) {
    return const GymAdminApp();
  }
}
