import 'package:flutter/material.dart';

Future<String?> showDemoLoginDialog({
  required BuildContext context,
  required String title,
  required String helperText,
}) {
  return showDialog<String>(
    context: context,
    builder: (_) => _DemoLoginDialog(title: title, helperText: helperText),
  );
}

class _DemoLoginDialog extends StatefulWidget {
  const _DemoLoginDialog({required this.title, required this.helperText});

  final String title;
  final String helperText;

  @override
  State<_DemoLoginDialog> createState() => _DemoLoginDialogState();
}

class _DemoLoginDialogState extends State<_DemoLoginDialog> {
  late final TextEditingController _emailController;
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _emailController = TextEditingController();
  }

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  void _submit() {
    if (_submitting || !mounted) {
      return;
    }

    final form = _formKey.currentState;
    if (form == null || !form.validate()) {
      return;
    }

    _submitting = true;
    FocusScope.of(context).unfocus();
    // Defer the route pop until the current text-field event has completed.
    // This avoids Navigator re-entrancy when the keyboard Done action and the
    // dialog button are delivered in the same frame on iOS.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        Navigator.of(context).pop(_emailController.text.trim());
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: Form(
        key: _formKey,
        child: TextFormField(
          controller: _emailController,
          autofocus: true,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.done,
          autofillHints: const <String>[AutofillHints.email],
          autocorrect: false,
          enableSuggestions: false,
          decoration: InputDecoration(
            labelText: 'Reviewer email',
            helperText: widget.helperText,
          ),
          validator: (value) {
            final email = value?.trim() ?? '';
            if (email.isEmpty) {
              return 'Enter the reviewer email.';
            }
            final valid = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email);
            return valid ? null : 'Enter a valid email address.';
          },
          onFieldSubmitted: (_) => _submit(),
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _submitting ? null : _submit,
          child: const Text('Continue'),
        ),
      ],
    );
  }
}
