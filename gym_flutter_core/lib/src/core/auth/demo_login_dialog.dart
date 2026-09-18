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
    Navigator.of(context).pop(_emailController.text);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: TextField(
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
        onSubmitted: (_) => _submit(),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(onPressed: _submit, child: const Text('Continue')),
      ],
    );
  }
}
