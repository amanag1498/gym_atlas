import 'package:flutter/material.dart';
import 'app_colors.dart';

class AppTextStyles {
  static TextTheme buildTextTheme() {
    return TextTheme(
      displayLarge: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 48,
        fontWeight: FontWeight.w700,
        height: 1.02,
        letterSpacing: -1.2,
        color: AppColors.textPrimary,
      ),
      displayMedium: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 40,
        fontWeight: FontWeight.w700,
        height: 1.05,
        letterSpacing: -0.9,
        color: AppColors.textPrimary,
      ),
      displaySmall: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 34,
        fontWeight: FontWeight.w700,
        height: 1.08,
        letterSpacing: -0.8,
        color: AppColors.textPrimary,
      ),
      headlineLarge: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 30,
        fontWeight: FontWeight.w600,
        height: 1.12,
        letterSpacing: -0.5,
        color: AppColors.textPrimary,
      ),
      headlineMedium: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 24,
        fontWeight: FontWeight.w600,
        height: 1.18,
        letterSpacing: -0.25,
        color: AppColors.textPrimary,
      ),
      headlineSmall: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 20,
        fontWeight: FontWeight.w600,
        height: 1.2,
        color: AppColors.textPrimary,
      ),
      titleLarge: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 18,
        fontWeight: FontWeight.w600,
        height: 1.25,
        color: AppColors.textPrimary,
      ),
      titleMedium: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 15,
        fontWeight: FontWeight.w600,
        height: 1.28,
        color: AppColors.textPrimary,
      ),
      titleSmall: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 13,
        fontWeight: FontWeight.w600,
        height: 1.3,
        color: AppColors.textPrimary,
      ),
      bodyLarge: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 16,
        fontWeight: FontWeight.w500,
        height: 1.55,
        color: AppColors.textSecondary,
      ),
      bodyMedium: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 14,
        fontWeight: FontWeight.w500,
        height: 1.52,
        color: AppColors.textSecondary,
      ),
      bodySmall: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 12,
        fontWeight: FontWeight.w500,
        height: 1.45,
        color: AppColors.textMuted,
      ),
      labelLarge: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 14,
        fontWeight: FontWeight.w600,
        height: 1.2,
        letterSpacing: 0.1,
        color: AppColors.textPrimary,
      ),
      labelMedium: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 12,
        fontWeight: FontWeight.w600,
        height: 1.2,
        letterSpacing: 0.2,
        color: AppColors.textSecondary,
      ),
      labelSmall: const TextStyle(
        fontFamily: 'Outfit',
        fontSize: 11,
        fontWeight: FontWeight.w600,
        height: 1.2,
        letterSpacing: 0.35,
        color: AppColors.textMuted,
      ),
    );
  }
}
