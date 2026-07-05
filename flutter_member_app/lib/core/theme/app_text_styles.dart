import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'app_colors.dart';

class AppTextStyles {
  static TextTheme buildTextTheme() {
    return TextTheme(
      displayLarge: GoogleFonts.poppins(
        fontSize: 48,
        fontWeight: FontWeight.w700,
        height: 1.02,
        letterSpacing: -1.2,
        color: AppColors.textPrimary,
      ),
      displayMedium: GoogleFonts.poppins(
        fontSize: 40,
        fontWeight: FontWeight.w700,
        height: 1.05,
        letterSpacing: -0.9,
        color: AppColors.textPrimary,
      ),
      displaySmall: GoogleFonts.poppins(
        fontSize: 34,
        fontWeight: FontWeight.w700,
        height: 1.08,
        letterSpacing: -0.8,
        color: AppColors.textPrimary,
      ),
      headlineLarge: GoogleFonts.poppins(
        fontSize: 30,
        fontWeight: FontWeight.w700,
        height: 1.12,
        letterSpacing: -0.5,
        color: AppColors.textPrimary,
      ),
      headlineMedium: GoogleFonts.poppins(
        fontSize: 24,
        fontWeight: FontWeight.w700,
        height: 1.18,
        letterSpacing: -0.25,
        color: AppColors.textPrimary,
      ),
      headlineSmall: GoogleFonts.poppins(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        height: 1.2,
        color: AppColors.textPrimary,
      ),
      titleLarge: GoogleFonts.poppins(
        fontSize: 18,
        fontWeight: FontWeight.w700,
        height: 1.25,
        color: AppColors.textPrimary,
      ),
      titleMedium: GoogleFonts.poppins(
        fontSize: 15,
        fontWeight: FontWeight.w700,
        height: 1.28,
        color: AppColors.textPrimary,
      ),
      titleSmall: GoogleFonts.poppins(
        fontSize: 13,
        fontWeight: FontWeight.w700,
        height: 1.3,
        color: AppColors.textPrimary,
      ),
      bodyLarge: GoogleFonts.poppins(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        height: 1.55,
        color: AppColors.textPrimary,
      ),
      bodyMedium: GoogleFonts.poppins(
        fontSize: 14,
        fontWeight: FontWeight.w500,
        height: 1.52,
        color: AppColors.textSecondary,
      ),
      bodySmall: GoogleFonts.poppins(
        fontSize: 12,
        fontWeight: FontWeight.w500,
        height: 1.45,
        color: AppColors.textMuted,
      ),
      labelLarge: GoogleFonts.poppins(
        fontSize: 14,
        fontWeight: FontWeight.w700,
        height: 1.2,
        letterSpacing: 0.1,
        color: AppColors.textPrimary,
      ),
      labelMedium: GoogleFonts.poppins(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        height: 1.2,
        letterSpacing: 0.2,
        color: AppColors.textSecondary,
      ),
      labelSmall: GoogleFonts.poppins(
        fontSize: 11,
        fontWeight: FontWeight.w700,
        height: 1.2,
        letterSpacing: 0.35,
        color: AppColors.textMuted,
      ),
    );
  }
}
