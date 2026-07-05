import 'package:flutter/material.dart';

ThemeData buildTrainerTheme() {
  const seed = Color(0xFF1D4ED8);
  return ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: seed,
      secondary: const Color(0xFF22C55E),
    ),
    scaffoldBackgroundColor: const Color(0xFFF4F8FF),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide.none,
      ),
    ),
  );
}
