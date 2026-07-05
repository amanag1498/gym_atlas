import 'package:flutter/material.dart';

class AppGradients {
  static const pageBackground = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[
      Color(0xFFFFFFFF),
      Color(0xFFF7F8F8),
      Color(0xFFFFFFFF),
    ],
  );

  static const cardGlow = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[
      Color(0xFFFFFFFF),
      Color(0xFFF7F8F8),
    ],
  );

  static const primaryButton = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: <Color>[
      Color(0xFF9DCEFF),
      Color(0xFF92A3FD),
    ],
  );

  static const statAccent = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[
      Color(0x339DCEFF),
      Color(0x33C58BF2),
    ],
  );

  static const secondaryButton = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: <Color>[
      Color(0xFFEEA4CE),
      Color(0xFFC58BF2),
    ],
  );
}
