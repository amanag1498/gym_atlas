import 'package:flutter/material.dart';

class AppColors {
  static const background = Color(0xFFF8FAFC);
  static const backgroundAlt = Color(0xFFFFFFFF);
  static const backgroundDeep = backgroundAlt;
  static const surface = Color(0xFFFFFFFF);
  static const surfaceStrong = Color(0xFFFFFFFF);
  static const surfaceSoft = Color(0xFFF8FAFC);
  static const surfaceOverlay = Color(0xF2FFFFFF);
  static const cardHighlight = Color(0xCCFFFFFF);
  static const stroke = Color(0xFFE2E8F0);
  static const strokeStrong = Color(0xFFCBD5E1);
  static const textPrimary = Color(0xFF0F172A);
  static const textSecondary = Color(0xFF475569);
  static const textMuted = Color(0xFF64748B);
  static const primary = Color(0xFF465FFF);
  static const primaryBright = Color(0xFF3641F5);
  static const accentPurple = Color(0xFF7C3AED);
  static const accentNeon = Color(0xFFF43F5E);
  static const accentAmber = Color(0xFFF59E0B);
  static const accent = accentPurple;
  static const success = Color(0xFF059669);
  static const warning = Color(0xFFD97706);
  static const error = Color(0xFFE11D48);
  static const info = Color(0xFF0284C7);
  static const shadow = Color(0x140F172A);

  static const statusActive = success;
  static const statusExpired = error;
  static const statusDue = warning;
  static const statusPaid = success;
  static const statusPending = info;
  static const statusRejected = error;
  static const statusCompleted = accentNeon;

  static Color statusColor(String value) {
    final normalized = value.trim().toLowerCase().replaceAll('_', ' ');

    if ({
      'active',
      'paid',
      'accepted',
      'completed',
      'converted',
      'verified',
      'public',
      'open',
    }.contains(normalized)) {
      return primary;
    }

    if ({
      'expired',
      'cancelled',
      'rejected',
      'closed',
      'inactive',
      'suspended',
      'private',
      'failed',
      'overdue',
    }.contains(normalized)) {
      return accentPurple;
    }

    if ({
      'due',
      'partial',
      'unpaid',
      'expiring soon',
      'trial',
      'frozen',
    }.contains(normalized)) {
      return accentNeon;
    }

    if ({
      'pending',
      'processing',
      'queued',
      'unverified',
    }.contains(normalized)) {
      return primaryBright;
    }

    if (normalized == 'featured') {
      return accentPurple;
    }

    if (normalized == 'promoted') {
      return accentAmber;
    }

    return primary;
  }
}
