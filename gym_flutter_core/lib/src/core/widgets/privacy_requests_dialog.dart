import 'package:flutter/material.dart';

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
    'erasure': 'Request deletion',
    'grievance': 'Raise a privacy concern',
  };

  final _details = TextEditingController();
  String _type = 'access';
  bool _saving = false;
  String? _error;
  late Future<List<Map<String, dynamic>>> _requests;

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
    if ((_type == 'correction' || _type == 'grievance') &&
        _details.text.trim().isEmpty) {
      setState(() => _error = 'Please tell us what you need help with.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.submitRequest(
        _type,
        _details.text.trim().isEmpty ? null : _details.text.trim(),
      );
      if (!mounted) return;
      _details.clear();
      setState(() => _requests = widget.fetchRequests());
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Your request has been received.')),
      );
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Could not send your request. Please try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Your privacy requests'),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Ask for a copy, correction or deletion of your information, or raise a concern.',
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _type,
                decoration: const InputDecoration(
                  labelText: 'What would you like to do?',
                ),
                items: _types.entries
                    .map(
                      (entry) => DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value),
                      ),
                    )
                    .toList(),
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _type = value ?? 'access'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _details,
                maxLines: 3,
                maxLength: 2000,
                decoration: InputDecoration(
                  labelText: _type == 'correction' || _type == 'grievance'
                      ? 'Tell us more (required)'
                      : 'Anything else we should know? (optional)',
                  border: const OutlineInputBorder(),
                ),
              ),
              if (_error != null)
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              const SizedBox(height: 12),
              const Text(
                'Recent requests',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              FutureBuilder<List<Map<String, dynamic>>>(
                future: _requests,
                builder: (context, snapshot) {
                  if (snapshot.hasError) {
                    return TextButton(
                      onPressed: () =>
                          setState(() => _requests = widget.fetchRequests()),
                      child: const Text('Could not load requests. Try again'),
                    );
                  }
                  if (!snapshot.hasData) {
                    return const Padding(
                      padding: EdgeInsets.all(12),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  }
                  final items = snapshot.data!;
                  if (items.isEmpty) return const Text('No requests yet.');
                  return Column(
                    children: items
                        .map(
                          (item) => ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(
                              _types[item['type']] ?? 'Privacy request',
                            ),
                            subtitle: Text(
                              item['status']?.toString() ?? 'Pending',
                            ),
                          ),
                        )
                        .toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Close'),
        ),
        FilledButton(
          onPressed: _saving ? null : _submit,
          child: _saving
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Send request'),
        ),
      ],
    );
  }
}
