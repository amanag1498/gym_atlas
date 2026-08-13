import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_gradients.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/empty_state.dart';
import '../../../core/widgets/premium_app_bar.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_repository.dart';

class TrainerEventsScreen extends StatefulWidget {
  const TrainerEventsScreen({
    super.key,
    required this.repository,
    this.initialEventId,
  });
  final TrainerRepository repository;
  final int? initialEventId;
  @override
  State<TrainerEventsScreen> createState() => _TrainerEventsScreenState();
}

class _TrainerEventsScreenState extends State<TrainerEventsScreen> {
  bool _loading = true;
  String? _error;
  int _tab = 0;
  List<Map<String, dynamic>> _all = const [];
  List<Map<String, dynamic>> _hosted = const [];
  List<Map<String, dynamic>> _managed = const [];
  bool _canManage = false;
  bool _initialEventHandled = false;
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
      final contextResponse = await widget.repository.fetchContext();
      final contextData = _map(contextResponse['data']);
      final profile = _map(contextData['trainer_profile']);
      final user = _map(contextData['user']);
      final permissions = (user['permissions'] as List? ?? const [])
          .map((value) => value.toString())
          .toSet();
      final canManage =
          profile['gym_id'] != null && permissions.contains('event.manage');
      final responses = await Future.wait([
        _fetchAll(hostedOnly: false),
        _fetchAll(hostedOnly: true),
        if (canManage) _fetchAll(managedOnly: true),
      ]);
      if (!mounted) return;
      _all = responses[0];
      _hosted = responses[1];
      _managed = canManage ? responses[2] : const [];
      setState(() {
        _canManage = canManage;
        if (!canManage && _tab == 2) _tab = 0;
        _loading = false;
      });
      await _openInitialEventOnce(_all);
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<void> _openInitialEventOnce(List<Map<String, dynamic>> events) async {
    final target = widget.initialEventId;
    if (_initialEventHandled || target == null) return;
    _initialEventHandled = true;

    Map<String, dynamic>? event;
    final matches = events.where((item) => _int(item['id']) == target);
    if (matches.isNotEmpty) {
      event = matches.first;
    } else {
      try {
        final response = await widget.repository.fetchEvent(target);
        final detail = _map(response['data']);
        if (detail.isNotEmpty) event = detail;
      } catch (_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('This event is no longer available.')),
          );
        }
        return;
      }
    }

    if (!mounted || event == null) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _showEvent(event!);
    });
  }

  Future<List<Map<String, dynamic>>> _fetchAll({
    bool hostedOnly = false,
    bool managedOnly = false,
  }) async {
    final events = <Map<String, dynamic>>[];
    var page = 1;
    var lastPage = 1;
    do {
      final response = await widget.repository.fetchEvents(
        hostedOnly: hostedOnly,
        managedOnly: managedOnly,
        page: page,
      );
      events.addAll(_records(response));
      lastPage = _lastPage(response, page);
      page++;
    } while (page <= lastPage);
    return events;
  }

  @override
  Widget build(BuildContext context) {
    final events = _tab == 0 ? _all : (_tab == 1 ? _hosted : _managed);
    return Scaffold(
      appBar: PremiumAppBar(
        title: 'Events',
        subtitle: _canManage
            ? 'Discover, host and manage gym experiences'
            : 'Classes, workshops and hosted rosters',
        actions: [
          IconButton(
            tooltip: 'Refresh events',
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.pageBackground),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.sm,
                AppSpacing.lg,
                AppSpacing.md,
              ),
              child: SizedBox(
                width: double.infinity,
                child: SegmentedButton<int>(
                  segments: [
                    ButtonSegment(
                      value: 0,
                      label: const Text('Schedule'),
                      icon: const Icon(Icons.calendar_month),
                    ),
                    ButtonSegment(
                      value: 1,
                      label: const Text('Hosting'),
                      icon: const Icon(Icons.groups_2_outlined),
                    ),
                    if (_canManage)
                      const ButtonSegment(
                        value: 2,
                        label: Text('Manage'),
                        icon: Icon(Icons.edit_calendar_outlined),
                      ),
                  ],
                  selected: {_tab},
                  onSelectionChanged: (value) =>
                      setState(() => _tab = value.first),
                ),
              ),
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                  ? EmptyState(
                      title: 'Events could not load',
                      message: _error!,
                      icon: Icons.cloud_off_outlined,
                      action: FilledButton(
                        onPressed: _load,
                        child: const Text('Try again'),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: events.isEmpty
                          ? ListView(
                              children: [
                                const SizedBox(height: 96),
                                EmptyState(
                                  title: _tab == 2
                                      ? 'No hosted events assigned'
                                      : _tab == 1
                                      ? 'You are not hosting an event yet'
                                      : 'No upcoming events',
                                  message: _tab == 2
                                      ? 'Events created by your gym will appear here after you are selected as host.'
                                      : _tab == 1
                                      ? 'Events will appear here when a gym or platform admin assigns you as host.'
                                      : 'New gym and global events will appear here.',
                                  icon: Icons.event_available_outlined,
                                ),
                              ],
                            )
                          : ListView.separated(
                              padding: const EdgeInsets.fromLTRB(
                                AppSpacing.lg,
                                0,
                                AppSpacing.lg,
                                96,
                              ),
                              itemCount: events.length,
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 12),
                              itemBuilder: (_, index) {
                                final event = events[index];
                                final managed = _managed.any(
                                  (item) => item['id'] == event['id'],
                                );
                                final hosted =
                                    managed ||
                                    _hosted.any(
                                      (item) => item['id'] == event['id'],
                                    );
                                return _TrainerEventCard(
                                  event: event,
                                  hosted: hosted,
                                  managed: managed,
                                  onTap: () => _showEvent(event),
                                );
                              },
                            ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _showEvent(Map<String, dynamic> event) async {
    final managed = _managed.any((item) => item['id'] == event['id']);
    final hosted = managed || _hosted.any((item) => item['id'] == event['id']);
    final editable =
        managed && ['draft', 'published'].contains(event['status']);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheet) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if ((event['cover_image_url']?.toString() ?? '').isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(18),
                      child: Image.network(
                        event['cover_image_url'].toString(),
                        height: 190,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                      ),
                    ),
                  ),
                Text(
                  event['title']?.toString() ?? 'Event',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  '${_date(event['starts_at'])} – ${_time(event['ends_at'])}',
                ),
                const SizedBox(height: 6),
                Text(
                  event['location_name']?.toString() ??
                      'Location to be announced',
                ),
                const SizedBox(height: 6),
                Text(
                  event['pricing_type'] == 'pay_at_venue'
                      ? '${_money(event['price_amount'], event['currency'])} · pay at venue'
                      : 'Free booking',
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if ((event['address']?.toString() ?? '').isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(event['address'].toString()),
                  ),
                if (event['latitude'] != null && event['longitude'] != null)
                  TextButton.icon(
                    onPressed: () => launchUrl(
                      Uri.parse(
                        'https://www.google.com/maps/search/?api=1&query=${event['latitude']},${event['longitude']}',
                      ),
                      mode: LaunchMode.externalApplication,
                    ),
                    icon: const Icon(Icons.directions_outlined),
                    label: const Text('Open directions'),
                  ),
                if ((event['description']?.toString() ?? '').isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(event['description'].toString()),
                  ),
                if (managed) ...[
                  const SizedBox(height: 18),
                  PremiumCard(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    glowColor: AppColors.accentPurple,
                    child: Row(
                      children: [
                        const Icon(
                          Icons.admin_panel_settings_outlined,
                          color: AppColors.accentPurple,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Your gym assigned you as host. You can manage this event and its roster.',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                if (hosted)
                  Padding(
                    padding: const EdgeInsets.only(top: 18),
                    child: SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: () {
                          Navigator.pop(sheet);
                          _openRoster(event);
                        },
                        icon: const Icon(Icons.groups_2_outlined),
                        label: const Text('View attendee & waitlist roster'),
                      ),
                    ),
                  ),
                if (editable) ...[
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () {
                            Navigator.pop(sheet);
                            _openEditor(event);
                          },
                          icon: const Icon(Icons.edit_outlined),
                          label: const Text('Edit event'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () {
                            Navigator.pop(sheet);
                            _cancelEvent(event);
                          },
                          icon: const Icon(Icons.event_busy_outlined),
                          label: const Text('Cancel'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.error,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openRoster(Map<String, dynamic> event) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            _EventRosterScreen(repository: widget.repository, event: event),
      ),
    );
  }

  Future<void> _openEditor(Map<String, dynamic> event) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _TrainerEventEditorScreen(
          repository: widget.repository,
          event: event,
        ),
      ),
    );
    if (changed == true && mounted) await _load();
  }

  Future<void> _cancelEvent(Map<String, dynamic> event) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cancel hosted event?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Confirmed members will be notified and booking history will be preserved.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: controller,
              maxLength: 500,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Cancellation reason',
                hintText: 'For example: instructor unavailable',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Keep event'),
          ),
          FilledButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.isNotEmpty) Navigator.pop(dialogContext, value);
            },
            child: const Text('Cancel event'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (reason == null || !mounted) return;
    try {
      await widget.repository.cancelHostedEvent(_int(event['id'])!, reason);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Event cancelled and attendees notified.'),
          ),
        );
        await _load();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }
}

class _TrainerEventCard extends StatelessWidget {
  const _TrainerEventCard({
    required this.event,
    required this.hosted,
    required this.managed,
    required this.onTap,
  });

  final Map<String, dynamic> event;
  final bool hosted;
  final bool managed;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final status = event['status']?.toString() ?? 'published';
    final statusColor = AppColors.statusColor(status);
    return PremiumCard(
      onTap: onTap,
      glowColor: managed ? AppColors.accentPurple : AppColors.primary,
      child: Row(
        children: [
          Container(
            width: 58,
            padding: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AppColors.primary.withValues(alpha: 0.16),
                  AppColors.accentPurple.withValues(alpha: 0.08),
                ],
              ),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.strokeStrong),
            ),
            child: Column(
              children: [
                Text(
                  _month(event['starts_at']),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                Text(
                  _day(event['starts_at']),
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ],
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 7,
                  runSpacing: 6,
                  children: [
                    if (hosted)
                      _EventBadge(
                        label: managed ? 'Gym host' : 'Host',
                        color: AppColors.accentPurple,
                      ),
                    if (status != 'published')
                      _EventBadge(label: status, color: statusColor),
                  ],
                ),
                if (hosted || status != 'published') const SizedBox(height: 8),
                Text(
                  event['title']?.toString() ?? 'Event',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_time(event['starts_at'])} · ${event['location_name'] ?? 'Location TBA'}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AppColors.textMuted),
        ],
      ),
    );
  }
}

class _EventBadge extends StatelessWidget {
  const _EventBadge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.10),
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: color.withValues(alpha: 0.25)),
    ),
    child: Text(
      label.replaceAll('_', ' '),
      style: Theme.of(context).textTheme.labelSmall?.copyWith(
        color: color,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _TrainerEventEditorScreen extends StatefulWidget {
  const _TrainerEventEditorScreen({
    required this.repository,
    required this.event,
  });

  final TrainerRepository repository;
  final Map<String, dynamic> event;

  @override
  State<_TrainerEventEditorScreen> createState() =>
      _TrainerEventEditorScreenState();
}

class _TrainerEventEditorScreenState extends State<_TrainerEventEditorScreen> {
  late final TextEditingController _title;
  late final TextEditingController _category;
  late final TextEditingController _description;
  late final TextEditingController _location;
  late final TextEditingController _address;
  late final TextEditingController _cover;
  late final TextEditingController _capacity;
  late final TextEditingController _price;
  late final TextEditingController _paymentNote;
  late DateTime _startsAt;
  late DateTime _endsAt;
  late String _status;
  late String _pricingType;
  late bool _waitlistEnabled;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final event = widget.event;
    _title = TextEditingController(text: event['title']?.toString() ?? '');
    _category = TextEditingController(
      text: event['category']?.toString() ?? '',
    );
    _description = TextEditingController(
      text: event['description']?.toString() ?? '',
    );
    _location = TextEditingController(
      text: event['location_name']?.toString() ?? '',
    );
    _address = TextEditingController(text: event['address']?.toString() ?? '');
    _cover = TextEditingController(
      text: event['cover_image_url']?.toString() ?? '',
    );
    _capacity = TextEditingController(
      text: event['capacity']?.toString() ?? '',
    );
    _price = TextEditingController(
      text: event['price_amount']?.toString() ?? '',
    );
    _paymentNote = TextEditingController(
      text: event['payment_note']?.toString() ?? '',
    );
    _startsAt =
        _dt(event['starts_at']) ?? DateTime.now().add(const Duration(days: 1));
    _endsAt = _dt(event['ends_at']) ?? _startsAt.add(const Duration(hours: 1));
    _status = event['status']?.toString() ?? 'draft';
    _pricingType = event['pricing_type']?.toString() ?? 'free';
    _waitlistEnabled = event['waitlist_enabled'] != false;
  }

  @override
  void dispose() {
    for (final controller in [
      _title,
      _category,
      _description,
      _location,
      _address,
      _cover,
      _capacity,
      _price,
      _paymentNote,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  String get _timezone =>
      widget.event['timezone']?.toString() ?? 'Asia/Kolkata';

  Future<void> _pickDateTime({required bool start}) async {
    final current = start ? _startsAt : _endsAt;
    final date = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(current),
    );
    if (time == null) return;
    final selected = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );
    setState(() {
      if (start) {
        final duration = _endsAt.difference(_startsAt);
        _startsAt = selected;
        _endsAt = selected.add(
          duration.isNegative ? const Duration(hours: 1) : duration,
        );
      } else {
        _endsAt = selected;
      }
    });
  }

  Future<void> _save() async {
    if (_title.text.trim().isEmpty) {
      _message('Enter an event title.');
      return;
    }
    if (!_endsAt.isAfter(_startsAt)) {
      _message('End time must be after start time.');
      return;
    }
    if (_status == 'published' && !_startsAt.isAfter(DateTime.now())) {
      _message('A published event must start in the future.');
      return;
    }
    final capacity = _capacity.text.trim().isEmpty
        ? null
        : int.tryParse(_capacity.text.trim());
    if (_capacity.text.trim().isNotEmpty &&
        (capacity == null || capacity < 1)) {
      _message('Capacity must be a positive whole number.');
      return;
    }
    final price = _pricingType == 'pay_at_venue'
        ? double.tryParse(_price.text.trim())
        : null;
    if (_pricingType == 'pay_at_venue' && (price == null || price < 0)) {
      _message('Enter a valid amount to be paid at the venue.');
      return;
    }
    final locationChanged =
        _location.text.trim() !=
            (widget.event['location_name']?.toString() ?? '').trim() ||
        _address.text.trim() !=
            (widget.event['address']?.toString() ?? '').trim();
    setState(() => _saving = true);
    try {
      await widget.repository.updateEvent(_int(widget.event['id'])!, {
        'title': _title.text.trim(),
        'category': _nullable(_category.text),
        'description': _nullable(_description.text),
        'cover_image_url': _nullable(_cover.text),
        'starts_at': _startsAt.toIso8601String(),
        'ends_at': _endsAt.toIso8601String(),
        'timezone': _timezone,
        'capacity': capacity,
        'waitlist_enabled': _waitlistEnabled,
        'pricing_type': _pricingType,
        'price_amount': price,
        'currency': widget.event['currency']?.toString() ?? 'INR',
        'payment_note': _nullable(_paymentNote.text),
        'location_name': _nullable(_location.text),
        'address': _nullable(_address.text),
        if (locationChanged) 'latitude': null,
        if (locationChanged) 'longitude': null,
        'status': _status,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _message(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));

  @override
  Widget build(BuildContext context) {
    final wasPublished = widget.event['status'] == 'published';
    return Scaffold(
      appBar: PremiumAppBar(
        title: 'Manage event',
        subtitle: 'Editing as the gym-assigned host',
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.pageBackground),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.sm,
            AppSpacing.lg,
            AppSpacing.xxl,
          ),
          children: [
            PremiumCard(
              glowColor: AppColors.accentPurple,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Event details',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextField(
                    controller: _title,
                    decoration: const InputDecoration(labelText: 'Title *'),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  TextField(
                    controller: _category,
                    decoration: const InputDecoration(labelText: 'Category'),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  TextField(
                    controller: _description,
                    maxLines: 4,
                    decoration: const InputDecoration(labelText: 'Description'),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  TextField(
                    controller: _cover,
                    keyboardType: TextInputType.url,
                    decoration: const InputDecoration(
                      labelText: 'Cover image URL',
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Schedule & venue',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _DateTimeTile(
                    label: 'Starts',
                    value: _startsAt,
                    onTap: () => _pickDateTime(start: true),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  _DateTimeTile(
                    label: 'Ends',
                    value: _endsAt,
                    onTap: () => _pickDateTime(start: false),
                  ),
                  if (widget.event['branch_id'] != null) ...[
                    const SizedBox(height: AppSpacing.sm),
                    InputDecorator(
                      decoration: const InputDecoration(
                        labelText: 'Gym branch',
                      ),
                      child: Text(
                        _map(widget.event['branch'])['name']?.toString() ??
                            'Assigned branch',
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.sm),
                  TextField(
                    controller: _location,
                    decoration: const InputDecoration(
                      labelText: 'Location name',
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  TextField(
                    controller: _address,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'Address'),
                  ),
                  if (widget.event['latitude'] != null &&
                      widget.event['longitude'] != null)
                    const Padding(
                      padding: EdgeInsets.only(top: AppSpacing.sm),
                      child: Text(
                        'Changing the venue clears the old map pin. Your gym can add an updated pin from the dashboard.',
                        style: TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Booking',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextField(
                    controller: _capacity,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Capacity',
                      hintText: 'Leave empty for unlimited',
                    ),
                  ),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Enable waitlist'),
                    value: _waitlistEnabled,
                    onChanged: (value) =>
                        setState(() => _waitlistEnabled = value),
                  ),
                  DropdownButtonFormField<String>(
                    initialValue: _pricingType,
                    decoration: const InputDecoration(
                      labelText: 'Booking price',
                    ),
                    items: const [
                      DropdownMenuItem(value: 'free', child: Text('Free')),
                      DropdownMenuItem(
                        value: 'pay_at_venue',
                        child: Text('Pay at venue'),
                      ),
                    ],
                    onChanged: (value) =>
                        setState(() => _pricingType = value ?? 'free'),
                  ),
                  if (_pricingType == 'pay_at_venue') ...[
                    const SizedBox(height: AppSpacing.sm),
                    TextField(
                      controller: _price,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText:
                            'Amount (${widget.event['currency'] ?? 'INR'})',
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    TextField(
                      controller: _paymentNote,
                      maxLines: 2,
                      decoration: const InputDecoration(
                        labelText: 'Payment note',
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.sm),
                  DropdownButtonFormField<String>(
                    initialValue: _status,
                    decoration: const InputDecoration(
                      labelText: 'Event status',
                    ),
                    items: [
                      if (!wasPublished)
                        const DropdownMenuItem(
                          value: 'draft',
                          child: Text('Draft'),
                        ),
                      const DropdownMenuItem(
                        value: 'published',
                        child: Text('Published'),
                      ),
                    ],
                    onChanged: wasPublished
                        ? null
                        : (value) => setState(() => _status = value ?? 'draft'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            FilledButton.icon(
              onPressed: _saving ? null : _save,
              icon: _saving
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.save_outlined),
              label: Text(_saving ? 'Saving…' : 'Save event changes'),
            ),
          ],
        ),
      ),
    );
  }
}

class _DateTimeTile extends StatelessWidget {
  const _DateTimeTile({
    required this.label,
    required this.value,
    required this.onTap,
  });
  final String label;
  final DateTime value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.surfaceStrong,
    borderRadius: BorderRadius.circular(18),
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Row(
          children: [
            const Icon(Icons.schedule_outlined, color: AppColors.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: Theme.of(context).textTheme.labelMedium),
                  const SizedBox(height: 2),
                  Text(DateFormat('EEE, d MMM yyyy · h:mm a').format(value)),
                ],
              ),
            ),
            const Icon(
              Icons.edit_outlined,
              size: 18,
              color: AppColors.textMuted,
            ),
          ],
        ),
      ),
    ),
  );
}

class _EventRosterScreen extends StatefulWidget {
  const _EventRosterScreen({required this.repository, required this.event});
  final TrainerRepository repository;
  final Map<String, dynamic> event;
  @override
  State<_EventRosterScreen> createState() => _EventRosterScreenState();
}

class _EventRosterScreenState extends State<_EventRosterScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> bookings = const [];
  final Set<int> updatingBookingIds = <int>{};

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final allBookings = <Map<String, dynamic>>[];
      var page = 1;
      var lastPage = 1;
      do {
        final response = await widget.repository.fetchEventRoster(
          _int(widget.event['id'])!,
          page: page,
        );
        allBookings.addAll(_records(response));
        lastPage = _lastPage(response, page);
        page++;
      } while (page <= lastPage);
      if (mounted) {
        setState(() {
          bookings = allBookings;
          loading = false;
        });
      }
    } catch (exception) {
      if (mounted) {
        setState(() {
          loading = false;
          error = exception.toString();
        });
      }
    }
  }

  Future<void> mark(Map<String, dynamic> booking, String status) async {
    final bookingId = _int(booking['id']);
    if (bookingId == null || updatingBookingIds.contains(bookingId)) return;
    setState(() => updatingBookingIds.add(bookingId));
    try {
      await widget.repository.updateEventAttendance(
        _int(widget.event['id'])!,
        bookingId,
        status,
      );
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              status == 'attended'
                  ? 'Attendance marked successfully.'
                  : 'Member marked as no-show.',
            ),
          ),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => updatingBookingIds.remove(bookingId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final attendanceOpen = _attendanceOpen(widget.event);
    return Scaffold(
      appBar: PremiumAppBar(
        title: 'Event roster',
        subtitle: widget.event['title']?.toString(),
        actions: [
          IconButton(
            tooltip: 'Refresh roster',
            onPressed: load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.pageBackground),
        child: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
            ? EmptyState(
                title: 'Roster could not load',
                message: error!,
                icon: Icons.cloud_off_outlined,
                action: FilledButton(
                  onPressed: load,
                  child: const Text('Try again'),
                ),
              )
            : RefreshIndicator(
                onRefresh: load,
                child: bookings.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 96),
                          EmptyState(
                            title: 'No bookings yet',
                            message:
                                'Confirmed members and waitlisted guests will appear here.',
                            icon: Icons.groups_2_outlined,
                          ),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(
                          AppSpacing.lg,
                          AppSpacing.sm,
                          AppSpacing.lg,
                          AppSpacing.xxl,
                        ),
                        itemCount: bookings.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (_, index) {
                          final booking = bookings[index];
                          final user = _map(booking['user']);
                          final bookingId = _int(booking['id']);
                          final updating =
                              bookingId != null &&
                              updatingBookingIds.contains(bookingId);
                          return PremiumCard(
                            padding: const EdgeInsets.all(AppSpacing.md),
                            child: Row(
                              children: [
                                CircleAvatar(
                                  backgroundColor: AppColors.primary.withValues(
                                    alpha: 0.12,
                                  ),
                                  foregroundColor: AppColors.primary,
                                  child: Text(
                                    _initials(user['name']?.toString()),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        user['name']?.toString() ?? 'Member',
                                        style: Theme.of(
                                          context,
                                        ).textTheme.titleMedium,
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        'Booked ${_date(booking['booked_at'])}',
                                        style: Theme.of(
                                          context,
                                        ).textTheme.bodySmall,
                                      ),
                                      const SizedBox(height: 7),
                                      _EventBadge(
                                        label:
                                            booking['status']?.toString() ??
                                            'reserved',
                                        color: AppColors.statusColor(
                                          booking['status']?.toString() ??
                                              'reserved',
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                if (attendanceOpen &&
                                    [
                                      'reserved',
                                      'attended',
                                      'no_show',
                                    ].contains(booking['status']))
                                  PopupMenuButton<String>(
                                    enabled: !updating,
                                    onSelected: (value) => mark(booking, value),
                                    icon: updating
                                        ? const SizedBox.square(
                                            dimension: 18,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                        : null,
                                    itemBuilder: (_) => const [
                                      PopupMenuItem(
                                        value: 'attended',
                                        child: Text('Mark attended'),
                                      ),
                                      PopupMenuItem(
                                        value: 'no_show',
                                        child: Text('Mark no-show'),
                                      ),
                                    ],
                                  )
                                else
                                  const Text(
                                    'Unavailable',
                                    style: TextStyle(fontSize: 12),
                                  ),
                              ],
                            ),
                          );
                        },
                      ),
              ),
      ),
    );
  }
}

List<Map<String, dynamic>> _records(Map<String, dynamic> response) {
  dynamic value = response['data'];
  if (value is Map) value = value['data'];
  return value is List
      ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
      : const [];
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
int? _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '');
int _lastPage(Map<String, dynamic> response, int fallback) {
  final meta = _map(response['meta']);
  final pagination = _map(meta['pagination']);
  return _int(pagination['last_page']) ?? fallback;
}

DateTime? _dt(dynamic value) =>
    DateTime.tryParse(value?.toString() ?? '')?.toLocal();
String _date(dynamic value) => _dt(value) == null
    ? 'Schedule TBA'
    : DateFormat('EEE, d MMM · h:mm a').format(_dt(value)!);
String _time(dynamic value) =>
    _dt(value) == null ? '--' : DateFormat('h:mm a').format(_dt(value)!);
String _day(dynamic value) =>
    _dt(value) == null ? '--' : DateFormat('d').format(_dt(value)!);
String _month(dynamic value) => _dt(value) == null
    ? '--'
    : DateFormat('MMM').format(_dt(value)!).toUpperCase();

bool _attendanceOpen(Map<String, dynamic> event) {
  if (!['published', 'completed'].contains(event['status'])) return false;
  final startsAt = _dt(event['starts_at']);
  final endsAt = _dt(event['ends_at']);
  if (startsAt == null || endsAt == null) return false;
  final now = DateTime.now();
  return !now.isBefore(startsAt.subtract(const Duration(hours: 2))) &&
      !now.isAfter(endsAt.add(const Duration(days: 1)));
}

String _money(dynamic amount, dynamic currency) {
  final code = currency?.toString().trim().toUpperCase();
  final value = amount is num
      ? amount.toDouble()
      : double.tryParse(amount?.toString() ?? '');
  final formatted = value == null
      ? amount?.toString() ?? '0'
      : value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(2);
  return code == 'INR' ? '₹$formatted' : '${code ?? 'INR'} $formatted';
}

String? _nullable(String value) => value.trim().isEmpty ? null : value.trim();
String _initials(String? value) {
  final parts = (value ?? '')
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();
  if (parts.isEmpty) return 'M';
  return parts.take(2).map((part) => part[0].toUpperCase()).join();
}
