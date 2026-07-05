import 'package:flutter/material.dart';

class AppColors {
  static const background = Color(0xFFFFFFFF);
  static const backgroundAlt = Color(0xFFF7F8F8);
  static const backgroundDeep = backgroundAlt;
  static const surface = Color(0xFFFFFFFF);
  static const surfaceStrong = backgroundAlt;
  static const surfaceSoft = backgroundAlt;
  static const surfaceOverlay = Color(0xF6FFFFFF);
  static const cardHighlight = Color(0xCCFFFFFF);
  static const stroke = Color(0x1F92A3FD);
  static const strokeStrong = Color(0x3392A3FD);
  static const textPrimary = Color(0xFF1D1617);
  static const textSecondary = Color(0xFF786F72);
  static const textMuted = Color(0xFFA39A9D);
  static const primary = Color(0xFF92A3FD);
  static const primaryBright = Color(0xFF9DCEFF);
  static const accentPurple = Color(0xFFC58BF2);
  static const accentNeon = Color(0xFFEEA4CE);
  static const accentAmber = accentNeon;
  static const accent = accentPurple;
  static const success = Color(0xFF92A3FD);
  static const warning = Color(0xFFEEA4CE);
  static const error = Color(0xFFC58BF2);
  static const info = Color(0xFF9DCEFF);
  static const shadow = Color(0x141D1617);

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
