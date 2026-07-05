import 'package:flutter/material.dart';

class RevealOnBuild extends StatefulWidget {
  const RevealOnBuild({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.offset = const Offset(0, 0.05),
    this.duration = const Duration(milliseconds: 420),
  });

  final Widget child;
  final Duration delay;
  final Offset offset;
  final Duration duration;

  @override
  State<RevealOnBuild> createState() => _RevealOnBuildState();
}

class _RevealOnBuildState extends State<RevealOnBuild> {
  bool _visible = false;

  @override
  void initState() {
    super.initState();
    Future<void>.delayed(widget.delay, () {
      if (mounted) {
        setState(() => _visible = true);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedSlide(
      duration: widget.duration,
      curve: Curves.easeOutCubic,
      offset: _visible ? Offset.zero : widget.offset,
      child: AnimatedOpacity(
        duration: widget.duration,
        curve: Curves.easeOutCubic,
        opacity: _visible ? 1 : 0,
        child: widget.child,
      ),
    );
  }
}
