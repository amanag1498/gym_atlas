import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'app_colors.dart';

class AppTextStyles {
  static TextTheme buildTextTheme() {
    final base = ThemeData.light().textTheme;

    return base.copyWith(
      displaySmall: GoogleFonts.poppins(
        fontSize: 38,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.8,
        color: AppColors.textPrimary,
      ),
      displayMedium: GoogleFonts.poppins(
        fontSize: 46,
        fontWeight: FontWeight.w800,
        letterSpacing: -1.0,
        color: AppColors.textPrimary,
      ),
      headlineLarge: GoogleFonts.poppins(
        fontSize: 34,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.7,
        color: AppColors.textPrimary,
      ),
      headlineMedium: GoogleFonts.poppins(
        fontSize: 28,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.5,
        color: AppColors.textPrimary,
      ),
      headlineSmall: GoogleFonts.poppins(
        fontSize: 22,
        fontWeight: FontWeight.w800,
        color: AppColors.textPrimary,
      ),
      titleLarge: GoogleFonts.poppins(
        fontSize: 22,
        fontWeight: FontWeight.w800,
        color: AppColors.textPrimary,
      ),
      titleMedium: GoogleFonts.poppins(
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: AppColors.textPrimary,
      ),
      titleSmall: GoogleFonts.poppins(
        fontSize: 14,
        fontWeight: FontWeight.w700,
        color: AppColors.textPrimary,
      ),
      bodyLarge: GoogleFonts.poppins(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        color: AppColors.textPrimary,
      ),
      bodyMedium: GoogleFonts.poppins(
        fontSize: 14,
        color: AppColors.textSecondary,
      ),
      bodySmall: GoogleFonts.poppins(fontSize: 12, color: AppColors.textMuted),
      labelLarge: GoogleFonts.poppins(
        fontSize: 14,
        fontWeight: FontWeight.w800,
        color: AppColors.textPrimary,
      ),
      labelMedium: GoogleFonts.poppins(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        letterSpacing: 0.2,
        color: AppColors.textSecondary,
      ),
    );
  }
}
