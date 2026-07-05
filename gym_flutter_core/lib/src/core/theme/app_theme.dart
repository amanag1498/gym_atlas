import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  static ThemeData build({
    required Color seed,
    required Color accent,
    required Brightness brightness,
  }) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF3AA0FF),
      brightness: brightness,
      primary: const Color(0xFF3AA0FF),
      secondary: const Color(0xFF86F3B8),
      surface: const Color(0xFF111A28),
      error: const Color(0xFFFF6B7A),
    );

    final textTheme = ThemeData.dark().textTheme.copyWith(
      displaySmall: GoogleFonts.bebasNeue(
        fontSize: 44,
        letterSpacing: 1.2,
        color: const Color(0xFFF5F7FB),
      ),
      headlineLarge: GoogleFonts.bebasNeue(
        fontSize: 40,
        letterSpacing: 1.0,
        color: const Color(0xFFF5F7FB),
      ),
      headlineMedium: GoogleFonts.bebasNeue(
        fontSize: 30,
        letterSpacing: 0.7,
        color: const Color(0xFFF5F7FB),
      ),
      headlineSmall: GoogleFonts.oswald(
        fontSize: 24,
        fontWeight: FontWeight.w600,
        color: const Color(0xFFF5F7FB),
      ),
      titleLarge: GoogleFonts.oswald(
        fontSize: 22,
        fontWeight: FontWeight.w600,
        color: const Color(0xFFF5F7FB),
      ),
      titleMedium: GoogleFonts.inter(
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: const Color(0xFFF5F7FB),
      ),
      bodyLarge: GoogleFonts.inter(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        color: const Color(0xFFF5F7FB),
      ),
      bodyMedium: GoogleFonts.inter(
        fontSize: 14,
        color: const Color(0xFF9CA8BA),
      ),
      bodySmall: GoogleFonts.inter(
        fontSize: 12,
        color: const Color(0xFF9CA8BA),
      ),
      labelLarge: GoogleFonts.inter(
        fontSize: 14,
        fontWeight: FontWeight.w700,
        color: const Color(0xFFF5F7FB),
      ),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: const Color(0xFF070B12),
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: const Color(0xFFF5F7FB),
        centerTitle: false,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: GoogleFonts.oswald(
          fontSize: 24,
          fontWeight: FontWeight.w600,
          color: const Color(0xFFF5F7FB),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFF172334),
        hintStyle: const TextStyle(color: Color(0xFF9CA8BA)),
        labelStyle: const TextStyle(color: Color(0xFF9CA8BA)),
        prefixIconColor: const Color(0xFF9CA8BA),
        suffixIconColor: const Color(0xFF9CA8BA),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(22),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(22),
          borderSide: const BorderSide(color: Color(0x2AFFFFFF)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(22),
          borderSide: const BorderSide(color: Color(0xFF3AA0FF)),
        ),
      ),
      cardTheme: CardThemeData(
        color: const Color(0xFF111A28),
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(28),
          side: const BorderSide(color: Color(0x2AFFFFFF)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: const Color(0xFF3AA0FF),
          foregroundColor: const Color(0xFFF5F7FB),
          textStyle: GoogleFonts.inter(
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: const Color(0xFF172334),
        labelStyle: GoogleFonts.inter(color: const Color(0xFFF5F7FB)),
        selectedColor: const Color(0xFF3AA0FF).withValues(alpha: 0.18),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: const Color(0xFF172334),
        contentTextStyle: textTheme.bodyMedium?.copyWith(
          color: const Color(0xFFF5F7FB),
        ),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(22),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: const Color(0xFF111A28),
        indicatorColor: const Color(0xFF3AA0FF).withValues(alpha: 0.18),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return GoogleFonts.inter(
            fontSize: 12,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            color: selected
                ? const Color(0xFFF5F7FB)
                : const Color(0xFF9CA8BA),
          );
        }),
      ),
    );
  }
}
