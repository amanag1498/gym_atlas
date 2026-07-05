import 'package:flutter/material.dart';

import '../theme/app_gradients.dart';

class AnimatedPageWrapper extends StatelessWidget {
  const AnimatedPageWrapper({
    super.key,
    required this.child,
    this.appBar,
    this.floatingActionButton,
    this.bottomNavigationBar,
  });

  final Widget child;
  final PreferredSizeWidget? appBar;
  final Widget? floatingActionButton;
  final Widget? bottomNavigationBar;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: false,
      appBar: appBar,
      floatingActionButton: floatingActionButton,
      bottomNavigationBar: bottomNavigationBar,
      body: Stack(
        children: [
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(gradient: AppGradients.pageBackground),
            ),
          ),
          Positioned(
            top: -120,
            right: -70,
            child: _SoftBlob(
              size: 260,
              color: const Color(0xFF92A3FD).withValues(alpha: 0.18),
            ),
          ),
          Positioned(
            bottom: -130,
            left: -90,
            child: _SoftBlob(
              size: 300,
              color: const Color(0xFFC58BF2).withValues(alpha: 0.16),
            ),
          ),
          SafeArea(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 260),
              switchInCurve: Curves.easeOutCubic,
              switchOutCurve: Curves.easeInCubic,
              transitionBuilder: (child, animation) {
                final curved = CurvedAnimation(
                  parent: animation,
                  curve: Curves.easeOutCubic,
                  reverseCurve: Curves.easeInCubic,
                );

                return FadeTransition(
                  opacity: curved,
                  child: SlideTransition(
                    position: Tween<Offset>(
                      begin: const Offset(0.03, 0.02),
                      end: Offset.zero,
                    ).animate(curved),
                    child: child,
                  ),
                );
              },
              child: child,
            ),
          ),
        ],
      ),
    );
  }
}

class _SoftBlob extends StatelessWidget {
  const _SoftBlob({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: color,
          boxShadow: [
            BoxShadow(color: color, blurRadius: 90, spreadRadius: 30),
          ],
        ),
      ),
    );
  }
}
