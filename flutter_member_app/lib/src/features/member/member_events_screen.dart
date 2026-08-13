import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import 'member_repository.dart';

class MemberEventsScreen extends StatefulWidget {
  const MemberEventsScreen({
    super.key,
    required this.repository,
    this.initialEventId,
  });
  final MemberRepository repository;
  final int? initialEventId;
  @override
  State<MemberEventsScreen> createState() => _MemberEventsScreenState();
}

class _MemberEventsScreenState extends State<MemberEventsScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _events = const [];
  int _tab = 0;

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
      final events = <Map<String, dynamic>>[];
      var page = 1;
      var lastPage = 1;
      do {
        final response = await widget.repository.fetchEvents(page: page);
        events.addAll(_records(response));
        lastPage = _lastPage(response, page);
        page++;
      } while (page <= lastPage);
      if (!mounted) return;
      setState(() {
        _events = events;
        _loading = false;
      });
      final target = widget.initialEventId;
      if (target != null) {
        final matches = events.where((item) => _int(item['id']) == target);
        if (matches.isNotEmpty) {
          WidgetsBinding.instance.addPostFrameCallback(
            (_) => _showEvent(matches.first),
          );
        } else {
          final detailResponse = await widget.repository.fetchEvent(target);
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

  @override
  Widget build(BuildContext context) {
    final visible = _tab == 0
        ? _events
        : _events.where((event) => _map(event['booking']).isNotEmpty).toList();
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
                  label: Text('All upcoming'),
                  icon: Icon(Icons.calendar_month),
                ),
                ButtonSegment(
                  value: 1,
                  label: Text('My bookings'),
                  icon: Icon(Icons.confirmation_number_outlined),
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
                ? _Error(message: _error!, retry: _load)
                : RefreshIndicator(
                    onRefresh: _load,
                    child: visible.isEmpty
                        ? ListView(
                            children: const [
                              SizedBox(height: 180),
                              Center(child: Text('No upcoming events found.')),
                            ],
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
                            itemCount: visible.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 12),
                            itemBuilder: (_, index) => _EventCard(
                              event: visible[index],
                              onTap: () => _showEvent(visible[index]),
                            ),
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Future<void> _showEvent(Map<String, dynamic> event) async {
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) {
        final booking = _map(event['booking']);
        final booked = ['reserved', 'waitlisted'].contains(booking['status']);
        final canBook = event['can_book'] == true;
        final canCancel = event['can_cancel_booking'] == true;
        final actionEnabled = booked ? canCancel : canBook;
        final pricing = event['pricing_type'] == 'pay_at_venue'
            ? '₹${event['price_amount']} · pay at venue'
            : 'Free';
        return SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              20 + MediaQuery.viewInsetsOf(sheetContext).bottom,
            ),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
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
                    '${event['location_name'] ?? 'Location to be announced'} · $pricing',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.primary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if ((event['address']?.toString() ?? '').isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 5),
                      child: Text(event['address'].toString()),
                    ),
                  if ((event['payment_note']?.toString() ?? '').isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(event['payment_note'].toString()),
                    ),
                  if ((event['description']?.toString() ?? '').isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 14),
                      child: Text(event['description'].toString()),
                    ),
                  const SizedBox(height: 16),
                  Text(
                    event['capacity'] == null
                        ? 'Unlimited capacity'
                        : '${event['available_spots']} spots available',
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
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: !actionEnabled
                          ? null
                          : () async {
                              Navigator.pop(sheetContext);
                              try {
                                if (booked) {
                                  await widget.repository.cancelEventBooking(
                                    _int(event['id'])!,
                                  );
                                } else {
                                  await widget.repository.bookEvent(
                                    _int(event['id'])!,
                                  );
                                }
                                await _load();
                              } catch (error) {
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(content: Text(error.toString())),
                                  );
                                }
                              }
                            },
                      child: Text(
                        booked
                            ? (canCancel
                                  ? 'Cancel booking'
                                  : 'Cancellation closed')
                            : (canBook ? 'Reserve my spot' : 'Booking closed'),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event, required this.onTap});
  final Map<String, dynamic> event;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final booking = _map(event['booking']);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 58,
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  children: [
                    Text(
                      _month(event['starts_at']),
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      _day(event['starts_at']),
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      event['title']?.toString() ?? 'Event',
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${_time(event['starts_at'])} · ${event['location_name'] ?? 'Location TBA'}',
                    ),
                    if (booking.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          booking['status'] == 'waitlisted'
                              ? 'Waitlisted'
                              : 'Spot confirmed',
                          style: TextStyle(
                            color: booking['status'] == 'waitlisted'
                                ? Colors.orange
                                : Colors.green,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton(onPressed: retry, child: const Text('Try again')),
        ],
      ),
    ),
  );
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
String _month(dynamic value) => _dt(value) == null
    ? '--'
    : DateFormat('MMM').format(_dt(value)!).toUpperCase();
String _day(dynamic value) =>
    _dt(value) == null ? '--' : DateFormat('d').format(_dt(value)!);
