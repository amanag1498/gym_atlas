import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/models/session_models.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/common_widgets.dart';
import 'admin_repository.dart';

class CommunicationsWorkspace extends StatefulWidget {
  const CommunicationsWorkspace({
    super.key,
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<CommunicationsWorkspace> createState() =>
      _CommunicationsWorkspaceState();
}

class _CommunicationsWorkspaceState extends State<CommunicationsWorkspace> {
  bool _loading = true;
  bool _busy = false;
  String? _error;
  Map<String, dynamic> _connection = const {};
  List<Map<String, dynamic>> _campaigns = const [];
  List<Map<String, dynamic>> _automations = const [];
  List<Map<String, dynamic>> _notificationTypes = const [];
  List<Map<String, dynamic>> _conversations = const [];

  String get _role => widget.appUser.activeRole;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        widget.repository.fetchWhatsAppConnection(_role),
        widget.repository.fetchCommunicationCampaigns(_role),
        widget.repository.fetchCommunicationAutomations(_role),
        widget.repository.fetchCommunicationNotificationTypes(_role),
        widget.repository.fetchWhatsAppInbox(_role),
      ]);
      _connection = results[0] as Map<String, dynamic>;
      _campaigns = results[1] as List<Map<String, dynamic>>;
      _automations = results[2] as List<Map<String, dynamic>>;
      _notificationTypes = results[3] as List<Map<String, dynamic>>;
      _conversations = results[4] as List<Map<String, dynamic>>;
    } catch (exception) {
      _error = exception.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _connect() async {
    await _run(() async {
      final url = await widget.repository.createWhatsAppOnboardingSession(
        _role,
      );
      if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
        throw StateError('Could not open secure Meta signup.');
      }
    }, reload: false);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Complete Meta signup, then return and tap Refresh.'),
      ),
    );
  }

  Future<void> _run(
    Future<void> Function() action, {
    bool reload = true,
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (reload) await _load();
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _compose() async {
    final account = Map<String, dynamic>.from(
      _connection['account'] as Map? ?? const {},
    );
    final templates = (account['templates'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    final approvedTemplates = templates
        .where((item) => item['status'] == 'approved')
        .toList();
    final name = TextEditingController();
    final title = TextEditingController();
    final body = TextEditingController();
    final templateParameters = TextEditingController();
    var includeInApp = true;
    int? templateId;
    final create = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Create campaign'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Campaign name'),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('In-app notification'),
                  value: includeInApp,
                  onChanged: (value) =>
                      setDialogState(() => includeInApp = value ?? true),
                ),
                if (includeInApp) ...[
                  TextField(
                    controller: title,
                    decoration: const InputDecoration(
                      labelText: 'Notification title',
                    ),
                  ),
                  TextField(
                    controller: body,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Message'),
                  ),
                ],
                if (approvedTemplates.isNotEmpty)
                  DropdownButtonFormField<int?>(
                    initialValue: templateId,
                    decoration: const InputDecoration(
                      labelText: 'WhatsApp template (optional)',
                    ),
                    items: [
                      const DropdownMenuItem<int?>(
                        value: null,
                        child: Text('No WhatsApp message'),
                      ),
                      ...approvedTemplates.map(
                        (item) => DropdownMenuItem<int?>(
                          value: (item['id'] as num).toInt(),
                          child: Text('${item['name']} (${item['language']})'),
                        ),
                      ),
                    ],
                    onChanged: (value) => setDialogState(() {
                      templateId = value;
                      templateParameters.clear();
                    }),
                  ),
                if (_templateVariableCount(approvedTemplates, templateId) >
                    0) ...[
                  TextField(
                    controller: templateParameters,
                    minLines: 2,
                    maxLines: 5,
                    decoration: InputDecoration(
                      labelText:
                          '${_templateVariableCount(approvedTemplates, templateId)} WhatsApp value(s), one per line',
                      hintText: '{member_name}\nYour membership expires soon',
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Available fields: {member_name}, {notification_title}, {notification_message}, {gym_name}, {branch_name}. Static text is also allowed.',
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Create draft'),
            ),
          ],
        ),
      ),
    );
    if (create != true ||
        name.text.trim().isEmpty ||
        (!includeInApp && templateId == null)) {
      return;
    }
    await _run(() async {
      await widget.repository.createCommunicationCampaign(_role, {
        'name': name.text.trim(),
        'audience_type': _role == 'platform_admin' ? 'all_members' : 'gym',
        'channels': {
          if (includeInApp)
            'in_app': {
              'notification_type': 'manual_campaign',
              'title': title.text.trim(),
              'body': body.text.trim(),
            },
          if (templateId != null)
            'whatsapp': {
              'whatsapp_template_id': templateId,
              'template_parameters': templateParameters.text
                  .split(RegExp(r'\r?\n'))
                  .map((value) => value.trim())
                  .where((value) => value.isNotEmpty)
                  .toList(),
            },
        },
      });
    });
  }

  Future<void> _sendCampaign(Map<String, dynamic> campaign) async {
    try {
      final id = (campaign['id'] as num).toInt();
      final preview = await widget.repository.previewCommunicationCampaign(
        _role,
        id,
      );
      if (!mounted) return;
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Confirm campaign send'),
          content: Text(
            '${preview['eligible'] ?? 0} delivery target(s) are eligible and '
            '${preview['excluded'] ?? 0} will be excluded by consent, sender, or template checks. Continue?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Review'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Send now'),
            ),
          ],
        ),
      );
      if (confirmed == true) {
        await _run(
          () => widget.repository.sendCommunicationCampaign(_role, id),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  Future<void> _editAutomation([Map<String, dynamic>? existing]) async {
    final account = Map<String, dynamic>.from(
      _connection['account'] as Map? ?? const {},
    );
    final templates = (account['templates'] as List? ?? const [])
        .whereType<Map>()
        .where(
          (item) =>
              item['status'] == 'approved' &&
              item['category']?.toString().toLowerCase() == 'utility',
        )
        .toList();
    var type =
        existing?['notification_type']?.toString() ??
        (_notificationTypes.isEmpty
            ? 'membership_expiry'
            : _notificationTypes.first['value'].toString());
    var inApp = existing?['in_app_enabled'] != false;
    var isEnabled = existing?['is_enabled'] != false;
    var whatsApp =
        existing?['whatsapp_enabled'] == true ||
        (existing == null && templates.isNotEmpty);
    final existingTemplateId = (existing?['whatsapp_template_id'] as num?)
        ?.toInt();
    int? templateId =
        templates.any(
          (item) => (item['id'] as num?)?.toInt() == existingTemplateId,
        )
        ? existingTemplateId
        : (templates.isEmpty ? null : (templates.first['id'] as num).toInt());
    if (templateId == null) whatsApp = false;
    final existingConfiguration = Map<String, dynamic>.from(
      existing?['configuration'] as Map? ?? const {},
    );
    final parameterValues = TextEditingController(
      text:
          (existingConfiguration['template_parameter_values'] as List? ??
                  const [])
              .join('\n'),
    );
    final save = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Lifecycle automation'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: const InputDecoration(labelText: 'Trigger'),
                  items: _notificationTypes
                      .map(
                        (item) => DropdownMenuItem<String>(
                          value: item['value']?.toString(),
                          child: Text(item['label']?.toString() ?? ''),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => type = value ?? type),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Rule active'),
                  subtitle: const Text('Pause without deleting its setup.'),
                  value: isEnabled,
                  onChanged: (value) => setDialogState(() => isEnabled = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('In-app notification'),
                  value: inApp,
                  onChanged: (value) => setDialogState(() => inApp = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('WhatsApp utility template'),
                  value: whatsApp,
                  onChanged: (value) {
                    if (value && templates.isEmpty) return;
                    setDialogState(() => whatsApp = value);
                  },
                ),
                if (whatsApp)
                  DropdownButtonFormField<int>(
                    initialValue: templateId,
                    decoration: const InputDecoration(labelText: 'Template'),
                    items: templates
                        .map(
                          (item) => DropdownMenuItem<int>(
                            value: (item['id'] as num).toInt(),
                            child: Text(
                              '${item['name']} (${item['language']})',
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setDialogState(() {
                      templateId = value;
                      parameterValues.clear();
                    }),
                  ),
                if (whatsApp &&
                    _templateVariableCount(templates, templateId) > 0) ...[
                  TextField(
                    controller: parameterValues,
                    minLines: 2,
                    maxLines: 5,
                    decoration: InputDecoration(
                      labelText:
                          '${_templateVariableCount(templates, templateId)} variable value(s), one per line',
                      hintText: '{member_name}\n{notification_message}',
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Available fields: {member_name}, {notification_title}, {notification_message}, {gym_name}, {branch_name}. Static text is also allowed.',
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(existing == null ? 'Enable' : 'Save'),
            ),
          ],
        ),
      ),
    );
    if (save != true) return;
    await _run(
      () => widget.repository.saveCommunicationAutomation(_role, {
        'notification_type': type,
        'recipient_role': 'member',
        'in_app_enabled': inApp,
        'whatsapp_enabled': whatsApp,
        'whatsapp_template_id': whatsApp ? templateId : null,
        'is_enabled': isEnabled,
        'configuration': <String, dynamic>{
          if (whatsApp)
            'template_parameter_values': parameterValues.text
                .split(RegExp(r'\r?\n'))
                .map((value) => value.trim())
                .where((value) => value.isNotEmpty)
                .toList(),
        },
      }),
    );
  }

  int _templateVariableCount(
    List<Map<dynamic, dynamic>> templates,
    int? templateId,
  ) {
    final selected = templates.where(
      (item) => (item['id'] as num?)?.toInt() == templateId,
    );
    if (selected.isEmpty) return 0;
    final components = (selected.first['components'] as List? ?? const [])
        .whereType<Map>();
    final bodies = components.where(
      (item) => item['type']?.toString().toUpperCase() == 'BODY',
    );
    if (bodies.isEmpty) return 0;
    final matches = RegExp(
      r'\{\{(\d+)\}\}',
    ).allMatches(bodies.first['text']?.toString() ?? '');
    return matches.map((match) => match.group(1)).toSet().length;
  }

  Future<void> _editTemplate([Map<String, dynamic>? existing]) async {
    final name = TextEditingController(text: existing?['name']?.toString());
    final language = TextEditingController(
      text: existing?['language']?.toString() ?? 'en_US',
    );
    final components = (existing?['components'] as List? ?? const [])
        .whereType<Map>();
    final bodyComponent = components.where(
      (item) => item['type']?.toString().toUpperCase() == 'BODY',
    );
    final body = TextEditingController(
      text: bodyComponent.isEmpty
          ? ''
          : bodyComponent.first['text']?.toString() ?? '',
    );
    final samples = TextEditingController();
    var category = existing?['category']?.toString().toLowerCase() ?? 'utility';
    final save = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            existing == null ? 'Create Meta template' : 'Edit template',
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  enabled: existing == null,
                  decoration: const InputDecoration(
                    labelText: 'Template name (lowercase_with_underscores)',
                  ),
                ),
                TextField(
                  controller: language,
                  enabled: existing == null,
                  decoration: const InputDecoration(labelText: 'Language'),
                ),
                DropdownButtonFormField<String>(
                  initialValue: category,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: const [
                    DropdownMenuItem(value: 'utility', child: Text('Utility')),
                    DropdownMenuItem(
                      value: 'marketing',
                      child: Text('Marketing'),
                    ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => category = value ?? category),
                ),
                TextField(
                  controller: body,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'Message body',
                    hintText: 'Hi {{1}}, your membership expires on {{2}}.',
                  ),
                ),
                TextField(
                  controller: samples,
                  decoration: const InputDecoration(
                    labelText: 'Sample values, comma separated',
                    hintText: 'Aman, 31 August',
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Meta reviews every new or edited template. It cannot be used until its status becomes approved.',
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Submit to Meta'),
            ),
          ],
        ),
      ),
    );
    if (save != true || body.text.trim().isEmpty) return;
    final payload = <String, dynamic>{
      if (existing == null) 'name': name.text.trim().toLowerCase(),
      if (existing == null) 'language': language.text.trim(),
      'category': category,
      'body': body.text.trim(),
      'sample_values': samples.text
          .split(',')
          .map((value) => value.trim())
          .where((value) => value.isNotEmpty)
          .toList(),
    };
    await _run(
      () => existing == null
          ? widget.repository.createWhatsAppTemplate(_role, payload)
          : widget.repository.updateWhatsAppTemplate(
              _role,
              (existing['id'] as num).toInt(),
              payload,
            ),
    );
  }

  Future<void> _openConversation(Map<String, dynamic> conversation) async {
    final id = (conversation['id'] as num).toInt();
    final detail = await widget.repository.fetchWhatsAppConversation(_role, id);
    if (!mounted) return;
    final envelope = Map<String, dynamic>.from(
      detail['messages'] as Map? ?? const {},
    );
    final messages = (envelope['data'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    final account = Map<String, dynamic>.from(
      _connection['account'] as Map? ?? const {},
    );
    final templates = (account['templates'] as List? ?? const [])
        .whereType<Map>()
        .where((item) => item['status'] == 'approved')
        .toList();
    final controller = TextEditingController();
    int? templateId;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          20,
          20,
          MediaQuery.viewInsetsOf(sheetContext).bottom + 20,
        ),
        child: StatefulBuilder(
          builder: (context, setSheetState) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Conversation',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 10),
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 260),
                child: ListView(
                  shrinkWrap: true,
                  children: messages.reversed
                      .map(
                        (message) => ListTile(
                          dense: true,
                          title: Text(
                            message['body']?.toString() ?? 'Template message',
                          ),
                          subtitle: Text(
                            message['direction']?.toString() ?? '',
                          ),
                        ),
                      )
                      .toList(),
                ),
              ),
              TextField(
                controller: controller,
                decoration: const InputDecoration(
                  labelText: 'Reply inside the 24-hour service window',
                ),
              ),
              if (templates.isNotEmpty)
                DropdownButtonFormField<int?>(
                  initialValue: templateId,
                  decoration: const InputDecoration(
                    labelText: 'Or use an approved template',
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Free-form reply'),
                    ),
                    ...templates.map(
                      (item) => DropdownMenuItem<int?>(
                        value: (item['id'] as num).toInt(),
                        child: Text('${item['name']} (${item['language']})'),
                      ),
                    ),
                  ],
                  onChanged: (value) => setSheetState(() => templateId = value),
                ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () async {
                  await widget.repository.replyToWhatsAppConversation(
                    _role,
                    id,
                    body: templateId == null ? controller.text : null,
                    templateId: templateId,
                  );
                  if (sheetContext.mounted) Navigator.pop(sheetContext);
                  await _load();
                },
                child: const Text('Send reply'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LoadingState(label: 'Loading communications...');
    if (_error != null) return ErrorState(message: _error!, onRetry: _load);
    final account = Map<String, dynamic>.from(
      _connection['account'] as Map? ?? const {},
    );
    final connected = account['status'] == 'connected';
    final phones = (account['phone_numbers'] as List? ?? const []).length;
    final templates = (account['templates'] as List? ?? const []).length;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        Icons.chat_rounded,
                        color: connected ? Colors.green : AppColors.primary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          connected
                              ? 'WhatsApp connected'
                              : 'Connect WhatsApp Business',
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    connected
                        ? '${account['business_name'] ?? 'Business account'} · $phones number(s) · $templates template(s)'
                        : 'Use secure Meta Embedded Signup. Atlas never asks you to paste an API token.',
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      FilledButton.icon(
                        onPressed: _busy
                            ? null
                            : connected
                            ? () => _run(
                                () => widget.repository.syncWhatsAppTemplates(
                                  _role,
                                ),
                              )
                            : _connect,
                        icon: Icon(connected ? Icons.sync : Icons.link),
                        label: Text(
                          connected ? 'Sync templates' : 'Connect with Meta',
                        ),
                      ),
                      if (connected)
                        OutlinedButton(
                          onPressed: _busy
                              ? null
                              : () => _run(
                                  () => widget.repository.disconnectWhatsApp(
                                    _role,
                                  ),
                                ),
                          child: const Text('Disconnect'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Message templates',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              TextButton.icon(
                onPressed: connected && !_busy ? () => _editTemplate() : null,
                icon: const Icon(Icons.add),
                label: const Text('New template'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if ((account['templates'] as List? ?? const []).isEmpty)
            const Card(
              child: ListTile(
                title: Text('No templates synced'),
                subtitle: Text(
                  'Create a template or sync approved templates from Meta.',
                ),
              ),
            )
          else
            ...(account['templates'] as List).whereType<Map>().map(
              (template) => Card(
                child: ListTile(
                  title: Text(template['name']?.toString() ?? 'Template'),
                  subtitle: Text(
                    '${template['category'] ?? 'utility'} · ${template['language'] ?? ''} · ${template['status'] ?? 'pending'}',
                  ),
                  trailing: const Icon(Icons.edit_outlined),
                  onTap: _busy
                      ? null
                      : () =>
                            _editTemplate(Map<String, dynamic>.from(template)),
                ),
              ),
            ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Campaigns',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              FilledButton.icon(
                onPressed: _busy ? null : _compose,
                icon: const Icon(Icons.add),
                label: const Text('New'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (_campaigns.isEmpty)
            const Card(
              child: ListTile(
                title: Text('No campaigns yet'),
                subtitle: Text('Create a draft, review it, then send it.'),
              ),
            )
          else
            ..._campaigns.map(
              (campaign) => Card(
                child: ListTile(
                  title: Text(campaign['name']?.toString() ?? 'Campaign'),
                  subtitle: Text(
                    '${campaign['status'] ?? 'draft'} · ${campaign['recipients_count'] ?? 0} deliveries',
                  ),
                  trailing: campaign['status'] == 'draft'
                      ? TextButton(
                          onPressed: _busy
                              ? null
                              : () => _sendCampaign(campaign),
                          child: const Text('Send now'),
                        )
                      : null,
                ),
              ),
            ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Automations',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              TextButton.icon(
                onPressed: _busy ? null : _editAutomation,
                icon: const Icon(Icons.add),
                label: const Text('Add rule'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Card(
            child: ListTile(
              leading: const Icon(Icons.auto_awesome_rounded),
              title: Text(
                '${_automations.where((item) => item['is_enabled'] == true).length} active rules',
              ),
              subtitle: const Text(
                'Lifecycle rules are kept separate from manual campaigns and respect member consent.',
              ),
            ),
          ),
          ..._automations.map(
            (automation) => Card(
              child: ListTile(
                title: Text(
                  automation['notification_type']?.toString().replaceAll(
                        '_',
                        ' ',
                      ) ??
                      'Notification rule',
                ),
                subtitle: Text(
                  [
                    if (automation['in_app_enabled'] == true) 'In-app',
                    if (automation['whatsapp_enabled'] == true) 'WhatsApp',
                  ].join(' + '),
                ),
                trailing: Switch.adaptive(
                  value: automation['is_enabled'] == true,
                  onChanged: null,
                ),
                onTap: _busy
                    ? null
                    : () => _editAutomation(
                        Map<String, dynamic>.from(automation),
                      ),
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text('WhatsApp inbox', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          if (_conversations.isEmpty)
            const Card(
              child: ListTile(
                leading: Icon(Icons.mark_chat_unread_outlined),
                title: Text('No conversations yet'),
                subtitle: Text(
                  'Member replies will appear here after webhook delivery.',
                ),
              ),
            )
          else
            ..._conversations
                .take(10)
                .map(
                  (conversation) => Card(
                    child: ListTile(
                      leading: const Icon(Icons.person_outline_rounded),
                      title: Text(
                        conversation['contact_name']?.toString() ??
                            conversation['contact_wa_id']?.toString() ??
                            'WhatsApp contact',
                      ),
                      subtitle: Text(
                        '${conversation['messages_count'] ?? 0} message(s) · ${conversation['status'] ?? 'open'}',
                      ),
                      onTap: () => _openConversation(conversation),
                    ),
                  ),
                ),
        ],
      ),
    );
  }
}
