import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_gradients.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/empty_state.dart';
import '../../../core/widgets/premium_app_bar.dart';
import '../../../core/widgets/premium_card.dart';
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
  List<Map<String, dynamic>> _bookedEvents = const [];
  int _tab = 0;
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
      final results = await Future.wait([_fetchUpcoming(), _fetchBookings()]);
      final events = results[0];
      final bookedEvents = results[1].map((bookedEvent) {
        final matches = events.where(
          (event) => _int(event['id']) == _int(bookedEvent['id']),
        );
        if (matches.isEmpty) return bookedEvent;
        return <String, dynamic>{
          ...matches.first,
          'booking': bookedEvent['booking'],
        };
      }).toList();
      if (!mounted) return;
      setState(() {
        _events = events;
        _bookedEvents = bookedEvents;
        _loading = false;
      });
      await _openInitialEventOnce(events);
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

  Future<List<Map<String, dynamic>>> _fetchUpcoming() async {
    final events = <Map<String, dynamic>>[];
    var page = 1;
    var lastPage = 1;
    do {
      final response = await widget.repository.fetchEvents(page: page);
      events.addAll(_records(response));
      lastPage = _lastPage(response, page);
      page++;
    } while (page <= lastPage);
    return events;
  }

  Future<List<Map<String, dynamic>>> _fetchBookings() async {
    final events = <Map<String, dynamic>>[];
    var page = 1;
    var lastPage = 1;
    do {
      final response = await widget.repository.fetchEventBookings(page: page);
      for (final booking in _records(response)) {
        final event = _map(booking['event']);
        if (event.isEmpty) continue;
        event['booking'] = {
          'id': booking['id'],
          'status': booking['status'],
          'booked_at': booking['booked_at'],
          'price_amount_snapshot': booking['price_amount_snapshot'],
          'currency_snapshot': booking['currency_snapshot'],
          'payment_note_snapshot': booking['payment_note_snapshot'],
        };
        events.add(event);
      }
      lastPage = _lastPage(response, page);
      page++;
    } while (page <= lastPage);
    return events;
  }

  @override
  Widget build(BuildContext context) {
    final visible = _tab == 0 ? _events : _bookedEvents;
    return Scaffold(
      appBar: PremiumAppBar(
        title: 'Events',
        subtitle: 'Classes, workshops and gym experiences',
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
                  onSelectionChanged: (value) =>
                      setState(() => _tab = value.first),
                  style: const ButtonStyle(
                    visualDensity: VisualDensity.comfortable,
                  ),
                ),
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
                              children: [
                                SizedBox(height: 96),
                                EmptyState(
                                  title: _tab == 0
                                      ? 'Nothing scheduled yet'
                                      : 'No upcoming bookings',
                                  message: _tab == 0
                                      ? 'Upcoming gym and global events will appear here.'
                                      : 'Reserve a spot from All upcoming and it will stay organised here.',
                                  icon: Icons.event_available_outlined,
                                ),
                              ],
                            )
                          : ListView.separated(
                              padding: const EdgeInsets.fromLTRB(
                                AppSpacing.lg,
                                0,
                                AppSpacing.lg,
                                AppSpacing.xxl,
                              ),
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
        final useBookingSnapshot = [
          'reserved',
          'waitlisted',
          'attended',
        ].contains(booking['status']);
        final payAtVenue = useBookingSnapshot
            ? booking['price_amount_snapshot'] != null
            : event['pricing_type'] == 'pay_at_venue';
        final priceAmount = useBookingSnapshot
            ? booking['price_amount_snapshot']
            : event['price_amount'];
        final currency = useBookingSnapshot
            ? booking['currency_snapshot']
            : event['currency'];
        final paymentNote = useBookingSnapshot
            ? booking['payment_note_snapshot']
            : event['payment_note'];
        final pricing = payAtVenue
            ? '${_money(priceAmount, currency)} · pay at venue'
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
                  if ((paymentNote?.toString() ?? '').isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(paymentNote.toString()),
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
                                Map<String, dynamic> response;
                                if (booked) {
                                  response = await widget.repository
                                      .cancelEventBooking(_int(event['id'])!);
                                } else {
                                  response = await widget.repository.bookEvent(
                                    _int(event['id'])!,
                                  );
                                }
                                await _load();
                                if (mounted) {
                                  final status = _map(
                                    response['data'],
                                  )['status']?.toString();
                                  final message = booked
                                      ? 'Your event booking was cancelled.'
                                      : status == 'waitlisted'
                                      ? 'The event is full. You joined the waitlist.'
                                      : 'Your event spot is confirmed.';
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(content: Text(message)),
                                  );
                                }
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
    final bookingStatus = booking['status']?.toString() ?? '';
    final bookingLabel = switch (bookingStatus) {
      'waitlisted' => 'Waitlisted',
      'reserved' => 'Spot confirmed',
      'attended' => 'Attended',
      'no_show' => 'No-show',
      'cancelled' => 'Booking cancelled',
      'event_cancelled' => 'Event cancelled',
      'waitlist_expired' => 'Waitlist closed',
      _ => bookingStatus.replaceAll('_', ' '),
    };
    final bookingColor = switch (bookingStatus) {
      'reserved' || 'attended' => AppColors.success,
      'waitlisted' => AppColors.warning,
      'cancelled' || 'event_cancelled' || 'no_show' => AppColors.error,
      _ => AppColors.textMuted,
    };
    return PremiumCard(
      onTap: onTap,
      glowColor: booking.isEmpty ? AppColors.primary : AppColors.success,
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
              border: Border.all(color: AppColors.strokeStrong),
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
                      bookingLabel,
                      style: TextStyle(
                        color: bookingColor,
                        fontWeight: FontWeight.w700,
                      ),
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

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => EmptyState(
    title: 'Events could not load',
    message: message,
    icon: Icons.cloud_off_outlined,
    action: FilledButton(onPressed: retry, child: const Text('Try again')),
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
