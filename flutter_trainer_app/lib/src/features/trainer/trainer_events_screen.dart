import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

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
      final responses = await Future.wait([
        _fetchAll(hostedOnly: false),
        _fetchAll(hostedOnly: true),
      ]);
      if (!mounted) return;
      _all = responses[0];
      _hosted = responses[1];
      setState(() => _loading = false);
      final id = widget.initialEventId;
      if (id != null) {
        final match = _all.where((e) => _int(e['id']) == id);
        if (match.isNotEmpty) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) {
              _showEvent(match.first);
            }
          });
        } else {
          final detailResponse = await widget.repository.fetchEvent(id);
          final detail = _map(detailResponse['data']);
          if (mounted && detail.isNotEmpty) {
            WidgetsBinding.instance.addPostFrameCallback(
              (_) => _showEvent(detail),
            );
          }
        }
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<List<Map<String, dynamic>>> _fetchAll({
    required bool hostedOnly,
  }) async {
    final events = <Map<String, dynamic>>[];
    var page = 1;
    var lastPage = 1;
    do {
      final response = await widget.repository.fetchEvents(
        hostedOnly: hostedOnly,
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
    final events = _tab == 0 ? _all : _hosted;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Upcoming events'),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: SegmentedButton<int>(
              segments: const [
                ButtonSegment(
                  value: 0,
                  label: Text('Full schedule'),
                  icon: Icon(Icons.calendar_month),
                ),
                ButtonSegment(
                  value: 1,
                  label: Text('I am hosting'),
                  icon: Icon(Icons.groups_2_outlined),
                ),
              ],
              selected: {_tab},
              onSelectionChanged: (value) => setState(() => _tab = value.first),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? Center(child: Text(_error!))
                : RefreshIndicator(
                    onRefresh: _load,
                    child: events.isEmpty
                        ? ListView(
                            children: const [
                              SizedBox(height: 180),
                              Center(child: Text('No upcoming events.')),
                            ],
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 30),
                            itemCount: events.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 12),
                            itemBuilder: (_, index) {
                              final event = events[index];
                              final hosted = _hosted.any(
                                (item) => item['id'] == event['id'],
                              );
                              return Card(
                                child: ListTile(
                                  contentPadding: const EdgeInsets.all(16),
                                  leading: CircleAvatar(
                                    child: Text(_day(event['starts_at'])),
                                  ),
                                  title: Text(
                                    event['title']?.toString() ?? 'Event',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  subtitle: Padding(
                                    padding: const EdgeInsets.only(top: 6),
                                    child: Text(
                                      '${_date(event['starts_at'])}\n${event['location_name'] ?? 'Location TBA'}${hosted ? ' · You are hosting' : ''}',
                                    ),
                                  ),
                                  isThreeLine: true,
                                  trailing: const Icon(Icons.chevron_right),
                                  onTap: () => _showEvent(event),
                                ),
                              );
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Future<void> _showEvent(Map<String, dynamic> event) async {
    final hosted = _hosted.any((item) => item['id'] == event['id']);
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
    await widget.repository.updateEventAttendance(
      _int(widget.event['id'])!,
      _int(booking['id'])!,
      status,
    );
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final attendanceOpen = widget.event['attendance_open'] == true;
    return Scaffold(
      appBar: AppBar(title: Text('${widget.event['title']} roster')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
          ? Center(child: Text(error!))
          : RefreshIndicator(
              onRefresh: load,
              child: bookings.isEmpty
                  ? ListView(
                      children: const [
                        SizedBox(height: 180),
                        Center(child: Text('No bookings yet.')),
                      ],
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: bookings.length,
                      separatorBuilder: (_, __) => const Divider(),
                      itemBuilder: (_, index) {
                        final booking = bookings[index];
                        final user = _map(booking['user']);
                        return ListTile(
                          title: Text(user['name']?.toString() ?? 'Member'),
                          subtitle: Text(
                            '${booking['status']} · booked ${_date(booking['booked_at'])}',
                          ),
                          trailing:
                              attendanceOpen &&
                                  [
                                    'reserved',
                                    'attended',
                                    'no_show',
                                  ].contains(booking['status'])
                              ? PopupMenuButton<String>(
                                  onSelected: (value) => mark(booking, value),
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
                              : const Text(
                                  'Unavailable',
                                  style: TextStyle(fontSize: 12),
                                ),
                        );
                      },
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
