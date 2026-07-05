import 'package:flutter/material.dart';

import 'app_colors.dart';

class AppGradients {
  static const pageBackground = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFFFFFFF), Color(0xFFF7F8F8), Color(0xFFEFF4FF)],
  );

  static const cardGlow = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFFFFFFF), Color(0xFFF7F8F8)],
  );

  static const primaryButton = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: <Color>[AppColors.primaryBright, AppColors.primary],
  );

  static const secondaryButton = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: <Color>[AppColors.accentRose, AppColors.accent],
  );

  static const statAccent = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFF1F5FF), Color(0xFFFDF2F8)],
  );
}
