import 'package:flutter/material.dart';

/// Canonical Gym Atlas branding shared by all apps.
class AtlasBrandLockup extends StatelessWidget {
  const AtlasBrandLockup({
    super.key,
    required this.audience,
    this.markSize = 64,
  });

  final String audience;
  final double markSize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final normalizedAudience = audience.toLowerCase();
    final productName = normalizedAudience == 'trainer'
        ? 'Gym Atlas Coach'
        : normalizedAudience == 'admin'
        ? 'Gym Atlas Admin'
        : 'Gym Atlas';
    final subline = normalizedAudience == 'trainer'
        ? 'COACH'
        : normalizedAudience == 'admin'
        ? 'ADMIN'
        : 'DISCIPLINE IN MOTION';

    return Semantics(
      container: true,
      label: '$productName app',
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          AtlasBrandMark(size: markSize),
          const SizedBox(width: 15),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'GYM ATLAS',
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: const Color(0xFF07152F),
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.4,
                  height: 1,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                subline,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: const Color(0xFF3567D9),
                  fontWeight: FontWeight.w800,
                  letterSpacing: normalizedAudience == 'member' ? 2.2 : 3.2,
                  height: 1,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class AtlasBrandMark extends StatelessWidget {
  const AtlasBrandMark({super.key, this.size = 64});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.28),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF07152F), Color(0xFF0C1D3D)],
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1D4ED8).withValues(alpha: 0.24),
            blurRadius: size * 0.42,
            offset: Offset(0, size * 0.18),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: EdgeInsets.all(size * 0.07),
        child: Image.asset(
          'assets/branding/gym_atlas_mark.png',
          package: 'gym_flutter_core',
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
        ),
      ),
    );
  }
}
