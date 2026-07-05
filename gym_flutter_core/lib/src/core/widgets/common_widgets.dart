import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class AppGradientScaffold extends StatelessWidget {
  const AppGradientScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.floatingActionButton,
    this.bottomNavigationBar,
  });

  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Widget? floatingActionButton;
  final Widget? bottomNavigationBar;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        title: Text(title),
        backgroundColor: Colors.transparent,
        actions: actions,
      ),
      floatingActionButton: floatingActionButton,
      bottomNavigationBar: bottomNavigationBar,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              Color(0xFF05121D),
              Color(0xFF0A1F1E),
              Color(0xFF090C11),
            ],
          ),
        ),
        child: SafeArea(
          child: body,
        ),
      ),
    );
  }
}

class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
  });

  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: padding,
        child: child,
      ),
    );
  }
}

class MetricTile extends StatelessWidget {
  const MetricTile({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? Theme.of(context).colorScheme.secondary;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          colors: <Color>[
            accent.withValues(alpha: 0.22),
            const Color(0xFF11161D),
          ],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: accent),
          const SizedBox(height: 16),
          Text(value, style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    );
  }
}

class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(20),
    this.gradient,
    this.borderRadius = 28,
  });

  final Widget child;
  final EdgeInsets padding;
  final Gradient? gradient;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(borderRadius),
        gradient: gradient ??
            LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[
                Colors.white.withValues(alpha: 0.08),
                const Color(0xFF121A23),
                const Color(0xFF0B1118),
              ],
            ),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x33000000),
            blurRadius: 24,
            offset: Offset(0, 16),
          ),
        ],
      ),
      child: Padding(padding: padding, child: child),
    );
  }
}

class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.label,
    this.color,
    this.icon,
  });

  final String label;
  final Color? color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? Theme.of(context).colorScheme.secondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: accent.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (icon != null) ...<Widget>[
            Icon(icon, size: 14, color: accent),
            const SizedBox(width: 6),
          ],
          Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: accent,
            ),
          ),
        ],
      ),
    );
  }
}

class AppNetworkImage extends StatelessWidget {
  const AppNetworkImage({
    super.key,
    required this.imageUrl,
    required this.height,
    this.width,
    this.borderRadius = 24,
    this.fit = BoxFit.cover,
    this.placeholderIcon = Icons.image_outlined,
  });

  final String? imageUrl;
  final double height;
  final double? width;
  final double borderRadius;
  final BoxFit fit;
  final IconData placeholderIcon;

  @override
  Widget build(BuildContext context) {
    final url = imageUrl?.trim();

    Widget child;
    if (url == null || url.isEmpty) {
      child = _ImagePlaceholder(icon: placeholderIcon);
    } else {
      child = Image.network(
        url,
        fit: fit,
        errorBuilder: (context, error, stackTrace) =>
            _ImagePlaceholder(icon: placeholderIcon),
        loadingBuilder: (context, widget, progress) {
          if (progress == null) {
            return widget;
          }

          return const _ImagePlaceholder(icon: Icons.photo_size_select_actual);
        },
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: SizedBox(
        height: height,
        width: width,
        child: child,
      ),
    );
  }
}

class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[Color(0xFF17212B), Color(0xFF0F151D)],
        ),
      ),
      child: Center(
        child: Icon(
          icon,
          size: 34,
          color: Colors.white.withValues(alpha: 0.55),
        ),
      ),
    );
  }
}

class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.secondary;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            Theme.of(context).colorScheme.primary,
            accent,
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: accent.withValues(alpha: 0.26),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Icon(
            Icons.fitness_center_rounded,
            size: size * 0.34,
            color: const Color(0xFF061019),
          ),
          Positioned(
            right: size * 0.18,
            bottom: size * 0.16,
            child: Container(
              width: size * 0.18,
              height: size * 0.18,
              decoration: const BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class EmptyStateView extends StatelessWidget {
  const EmptyStateView({
    super.key,
    required this.title,
    required this.message,
    this.icon = Icons.inbox_outlined,
    this.action,
  });

  final String title;
  final String message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: GlassCard(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(
                icon,
                size: 52,
                color: Theme.of(context).colorScheme.secondary,
              ),
              const SizedBox(height: 16),
              Text(title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center),
              if (action != null) ...<Widget>[
                const SizedBox(height: 20),
                action!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class ErrorStateView extends StatelessWidget {
  const ErrorStateView({
    super.key,
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return EmptyStateView(
      title: 'Something needs attention',
      message: message,
      icon: Icons.error_outline_rounded,
      action: FilledButton(onPressed: onRetry, child: const Text('Retry')),
    );
  }
}

class LoadingStateView extends StatelessWidget {
  const LoadingStateView({super.key, this.label});

  final String? label;

  @override
  Widget build(BuildContext context) {
    return EmptyStateView(
      title: 'Loading',
      message: label ?? 'Preparing your workspace.',
      icon: Icons.bolt_rounded,
      action: const SizedBox.square(
        dimension: 28,
        child: CircularProgressIndicator(strokeWidth: 3),
      ),
    );
  }
}

class RecordListTile extends StatelessWidget {
  const RecordListTile({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      title: Text(title),
      subtitle: subtitle == null ? null : Text(subtitle!),
      trailing: trailing,
      contentPadding: const EdgeInsets.symmetric(horizontal: 0, vertical: 2),
    );
  }
}

class AppSearchField extends StatelessWidget {
  const AppSearchField({
    super.key,
    required this.controller,
    required this.hint,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      textInputAction: TextInputAction.search,
      onSubmitted: onSubmitted,
      decoration: InputDecoration(
        hintText: hint,
        prefixIcon: const Icon(Icons.search),
      ),
    );
  }
}

String prettyDate(dynamic raw) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  if (date == null) return '--';
  return DateFormat('dd MMM yyyy').format(date);
}

String prettyDateTime(dynamic raw) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  if (date == null) return '--';
  return DateFormat('dd MMM, hh:mm a').format(date);
}
