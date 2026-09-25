import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class PrivacyRequestsDialog extends StatefulWidget {
  const PrivacyRequestsDialog({
    super.key,
    required this.fetchRequests,
    required this.submitRequest,
  });

  final Future<List<Map<String, dynamic>>> Function() fetchRequests;
  final Future<void> Function(String type, String? details) submitRequest;

  @override
  State<PrivacyRequestsDialog> createState() => _PrivacyRequestsDialogState();
}

class _PrivacyRequestsDialogState extends State<PrivacyRequestsDialog> {
  static const _types = <String, String>{
    'access': 'Get a copy of my information',
    'correction': 'Correct my information',
    'erasure': 'Request account deletion',
    'grievance': 'Raise a privacy concern',
  };

  final _formKey = GlobalKey<FormState>();
  final _details = TextEditingController();
  String _type = 'access';
  bool _saving = false;
  String? _error;
  String? _success;
  late Future<List<Map<String, dynamic>>> _requests;

  bool get _detailsRequired => _type == 'correction' || _type == 'grievance';

  @override
  void initState() {
    super.initState();
    _requests = widget.fetchRequests();
  }

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
      _success = null;
    });
    try {
      await widget.submitRequest(
        _type,
        _details.text.trim().isEmpty ? null : _details.text.trim(),
      );
      if (!mounted) return;
      _details.clear();
      setState(() {
        _success = 'Request sent. You can track its status below.';
        _requests = widget.fetchRequests();
      });
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'We could not send your request. Please try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _retryRequests() {
    setState(() => _requests = widget.fetchRequests());
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final screenHeight = MediaQuery.sizeOf(context).height;

    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      clipBehavior: Clip.antiAlias,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 560,
          maxHeight: screenHeight > 48 ? screenHeight - 48 : screenHeight,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 24, 12, 16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ExcludeSemantics(
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: scheme.primary.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Icon(
                        Icons.privacy_tip_outlined,
                        color: scheme.primary,
                        size: 25,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Your privacy requests',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.4,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Ask for your information, a correction, deletion, or help with a privacy concern.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close privacy requests',
                    onPressed: _saving
                        ? null
                        : () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Send a new request',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _type,
                        isExpanded: true,
                        decoration: const InputDecoration(
                          labelText: 'What do you need?',
                          prefixIcon: Icon(Icons.tune_rounded),
                        ),
                        items: _types.entries
                            .map(
                              (entry) => DropdownMenuItem(
                                value: entry.key,
                                child: Text(
                                  entry.value,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            )
                            .toList(),
                        onChanged: _saving
                            ? null
                            : (value) {
                                if (value == null) return;
                                setState(() {
                                  _type = value;
                                  _error = null;
                                  _success = null;
                                });
                              },
                      ),
                      if (_type == 'erasure') ...[
                        const SizedBox(height: 10),
                        _InlineMessage(
                          icon: Icons.info_outline_rounded,
                          message:
                              'Deletion starts a review. We may contact you to verify the request before removing data.',
                          color: scheme.primary,
                        ),
                      ],
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _details,
                        minLines: 3,
                        maxLines: 5,
                        maxLength: 2000,
                        enabled: !_saving,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: InputDecoration(
                          labelText: _detailsRequired
                              ? 'Tell us what needs attention'
                              : 'Add a note (optional)',
                          alignLabelWithHint: true,
                          helperText: _detailsRequired
                              ? 'A short explanation helps us act on your request.'
                              : null,
                        ),
                        validator: (value) {
                          if (_detailsRequired &&
                              (value == null || value.trim().isEmpty)) {
                            return 'Please add a short explanation.';
                          }
                          return null;
                        },
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 10),
                        Semantics(
                          liveRegion: true,
                          child: _InlineMessage(
                            icon: Icons.error_outline_rounded,
                            message: _error!,
                            color: scheme.error,
                          ),
                        ),
                      ],
                      if (_success != null) ...[
                        const SizedBox(height: 10),
                        Semantics(
                          liveRegion: true,
                          child: _InlineMessage(
                            icon: Icons.check_circle_outline_rounded,
                            message: _success!,
                            color: const Color(0xFF059669),
                          ),
                        ),
                      ],
                      const SizedBox(height: 14),
                      FilledButton.icon(
                        onPressed: _saving ? null : _submit,
                        icon: _saving
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(Icons.send_rounded),
                        label: Text(_saving ? 'Sending…' : 'Send request'),
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(52),
                        ),
                      ),
                      const SizedBox(height: 26),
                      Divider(color: scheme.outlineVariant),
                      const SizedBox(height: 18),
                      Text(
                        'Recent requests',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 10),
                      FutureBuilder<List<Map<String, dynamic>>>(
                        future: _requests,
                        builder: (context, snapshot) {
                          if (snapshot.connectionState ==
                              ConnectionState.waiting) {
                            return const _RequestsLoading();
                          }
                          if (snapshot.hasError) {
                            return _RequestsError(onRetry: _retryRequests);
                          }
                          final items = snapshot.data ?? const [];
                          if (items.isEmpty) {
                            return const _EmptyRequests();
                          }
                          return DecoratedBox(
                            decoration: BoxDecoration(
                              border: Border.all(color: scheme.outlineVariant),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Column(
                              children: [
                                for (
                                  var index = 0;
                                  index < items.length;
                                  index++
                                ) ...[
                                  _RequestRow(item: items[index]),
                                  if (index < items.length - 1)
                                    Divider(
                                      height: 1,
                                      indent: 16,
                                      endIndent: 16,
                                      color: scheme.outlineVariant,
                                    ),
                                ],
                              ],
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RequestRow extends StatelessWidget {
  const _RequestRow({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final status = item['status']?.toString().toLowerCase() ?? 'pending';
    final createdAt = DateTime.tryParse(item['created_at']?.toString() ?? '');
    final dateLabel = createdAt == null
        ? null
        : DateFormat('d MMM yyyy').format(createdAt.toLocal());
    final color = _statusColor(status, scheme);

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
      leading: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: scheme.primary.withValues(alpha: 0.09),
          borderRadius: BorderRadius.circular(13),
        ),
        child: Icon(
          _typeIcon(item['type']?.toString()),
          color: scheme.primary,
          size: 21,
        ),
      ),
      title: Text(
        _PrivacyRequestsDialogState._types[item['type']] ?? 'Privacy request',
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: FontWeight.w800,
        ),
      ),
      subtitle: dateLabel == null ? null : Text('Sent $dateLabel'),
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          _statusLabel(status),
          style: theme.textTheme.labelSmall?.copyWith(
            color: color,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class _RequestsLoading extends StatelessWidget {
  const _RequestsLoading();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 24),
      child: Center(
        child: SizedBox.square(
          dimension: 26,
          child: CircularProgressIndicator(strokeWidth: 2.4),
        ),
      ),
    );
  }
}

class _RequestsError extends StatelessWidget {
  const _RequestsError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return _InlineMessage(
      icon: Icons.cloud_off_outlined,
      message: 'Recent requests could not be loaded.',
      color: scheme.error,
      action: TextButton(onPressed: onRetry, child: const Text('Try again')),
    );
  }
}

class _EmptyRequests extends StatelessWidget {
  const _EmptyRequests();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return _InlineMessage(
      icon: Icons.inbox_outlined,
      message: 'No requests yet. New requests will appear here.',
      color: scheme.primary,
    );
  }
}

class _InlineMessage extends StatelessWidget {
  const _InlineMessage({
    required this.icon,
    required this.message,
    required this.color,
    this.action,
  });

  final IconData icon;
  final String message;
  final Color color;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
          if (action != null) ...[const SizedBox(width: 8), action!],
        ],
      ),
    );
  }
}

IconData _typeIcon(String? type) => switch (type) {
  'access' => Icons.download_outlined,
  'correction' => Icons.edit_note_rounded,
  'erasure' => Icons.delete_outline_rounded,
  'grievance' => Icons.support_agent_rounded,
  _ => Icons.privacy_tip_outlined,
};

String _statusLabel(String status) => switch (status) {
  'fulfilled' || 'completed' => 'Completed',
  'processing' || 'in_progress' => 'In progress',
  'rejected' || 'cancelled' => 'Closed',
  _ => 'Pending',
};

Color _statusColor(String status, ColorScheme scheme) => switch (status) {
  'fulfilled' || 'completed' => const Color(0xFF059669),
  'rejected' || 'cancelled' => scheme.error,
  'processing' || 'in_progress' => const Color(0xFF0284C7),
  _ => const Color(0xFFD97706),
};
