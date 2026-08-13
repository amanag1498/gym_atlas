import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
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
    return AppGradientScaffold(
      title: 'Events',
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.md,
                AppSpacing.lg,
                0,
              ),
              child: _EventsTopBar(
                onBack: () => Navigator.of(context).maybePop(),
                onRefresh: _load,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.md,
                AppSpacing.lg,
                0,
              ),
              child: _EventsSummaryPanel(
                upcomingCount: _events.length,
                bookingCount: _bookedEvents.where((event) {
                  final status = _map(event['booking'])['status'];
                  return status == 'reserved' || status == 'waitlisted';
                }).length,
                nextEvent: _events.firstOrNull,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.md,
                AppSpacing.lg,
                AppSpacing.md,
              ),
              child: _EventsTabSlider(
                selected: _tab,
                onChanged: (value) => setState(() => _tab = value),
              ),
            ),
            Expanded(
              child: _loading
                  ? const LoadingState(label: 'Loading upcoming events...')
                  : _error != null
                  ? ErrorStateView(message: _error!, onRetry: _load)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: visible.isEmpty
                          ? ListView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              children: [
                                const SizedBox(height: 24),
                                _EventsEmptyPanel(
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
                                96,
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
      backgroundColor: Colors.transparent,
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
          top: false,
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.9,
            ),
            decoration: const BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
            ),
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(
                AppSpacing.lg,
                12,
                AppSpacing.lg,
                AppSpacing.lg + MediaQuery.viewInsetsOf(sheetContext).bottom,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Center(
                    child: Container(
                      width: 46,
                      height: 5,
                      decoration: BoxDecoration(
                        color: AppColors.strokeStrong,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
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
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _EventInfoChip(
                        icon: Icons.schedule_rounded,
                        label:
                            '${_date(event['starts_at'])} – ${_time(event['ends_at'])}',
                      ),
                      _EventInfoChip(
                        icon: Icons.payments_outlined,
                        label: pricing,
                      ),
                      _EventInfoChip(
                        icon: Icons.group_outlined,
                        label: event['capacity'] == null
                            ? 'Open capacity'
                            : '${event['available_spots'] ?? 0} spots available',
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  PremiumCard(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: AppColors.accentPurple.withValues(
                              alpha: 0.12,
                            ),
                            borderRadius: BorderRadius.circular(15),
                          ),
                          child: const Icon(
                            Icons.location_on_outlined,
                            color: AppColors.accentPurple,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                event['location_name']?.toString() ??
                                    'Location to be announced',
                                style: Theme.of(context).textTheme.titleSmall
                                    ?.copyWith(
                                      color: AppColors.textPrimary,
                                      fontWeight: FontWeight.w900,
                                    ),
                              ),
                              if ((event['address']?.toString() ?? '')
                                  .isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  event['address'].toString(),
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        color: AppColors.textSecondary,
                                      ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  if ((paymentNote?.toString() ?? '').isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: _EventNotice(
                        icon: Icons.info_outline_rounded,
                        text: paymentNote.toString(),
                      ),
                    ),
                  if ((event['description']?.toString() ?? '').isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'About this event',
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: 7),
                          Text(
                            event['description'].toString(),
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: AppColors.textSecondary,
                                  height: 1.45,
                                ),
                          ),
                        ],
                      ),
                    ),
                  if (event['latitude'] != null && event['longitude'] != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: OutlinedButton.icon(
                        onPressed: () => launchUrl(
                          Uri.parse(
                            'https://www.google.com/maps/search/?api=1&query=${event['latitude']},${event['longitude']}',
                          ),
                          mode: LaunchMode.externalApplication,
                        ),
                        icon: const Icon(Icons.directions_outlined),
                        label: const Text('Open directions'),
                      ),
                    ),
                  const SizedBox(height: 18),
                  GradientButton(
                    expanded: true,
                    variant: booked
                        ? GradientButtonVariant.danger
                        : GradientButtonVariant.primary,
                    icon: booked
                        ? Icons.event_busy_outlined
                        : Icons.confirmation_number_outlined,
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
                    label: booked
                        ? (canCancel ? 'Cancel booking' : 'Cancellation closed')
                        : (canBook ? 'Reserve my spot' : 'Booking closed'),
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

class _EventsTopBar extends StatelessWidget {
  const _EventsTopBar({required this.onBack, required this.onRefresh});

  final VoidCallback onBack;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        MemberHeaderActionButton(icon: Icons.arrow_back_rounded, onTap: onBack),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Upcoming Events',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                'Classes, workshops, and community sessions.',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        MemberHeaderActionButton(icon: Icons.refresh_rounded, onTap: onRefresh),
      ],
    );
  }
}

class _EventsSummaryPanel extends StatelessWidget {
  const _EventsSummaryPanel({
    required this.upcomingCount,
    required this.bookingCount,
    required this.nextEvent,
  });

  final int upcomingCount;
  final int bookingCount;
  final Map<String, dynamic>? nextEvent;

  @override
  Widget build(BuildContext context) {
    final event = nextEvent;
    final nextTitle = event?['title']?.toString() ?? 'Discover what is next';
    final nextSchedule = event == null
        ? 'New gym and global events will appear here.'
        : '${_date(event['starts_at'])} · ${event['location_name'] ?? 'Location TBA'}';

    return PremiumCard(
      padding: EdgeInsets.zero,
      glowColor: AppColors.accentPurple,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.primary.withValues(alpha: 0.12),
              AppColors.accentPurple.withValues(alpha: 0.08),
              AppColors.surface,
            ],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Icon(
                    Icons.event_available_rounded,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'NEXT ON YOUR CALENDAR',
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: AppColors.primary,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 0.8,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        nextTitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: AppColors.textPrimary,
                              fontWeight: FontWeight.w900,
                            ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              nextSchedule,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _EventInfoChip(
                  icon: Icons.calendar_month_rounded,
                  label: '$upcomingCount upcoming',
                ),
                _EventInfoChip(
                  icon: Icons.confirmation_number_rounded,
                  label: '$bookingCount booked',
                ),
                const _EventInfoChip(
                  icon: Icons.notifications_active_outlined,
                  label: 'Reminders included',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EventsTabSlider extends StatelessWidget {
  const _EventsTabSlider({required this.selected, required this.onChanged});

  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    const items = [
      (label: 'All Upcoming', icon: Icons.calendar_month_rounded),
      (label: 'My Bookings', icon: Icons.confirmation_number_rounded),
    ];
    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: AppColors.surfaceOverlay,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          for (var index = 0; index < items.length; index++)
            Expanded(
              child: InkWell(
                onTap: () => onChanged(index),
                borderRadius: BorderRadius.circular(999),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  curve: Curves.easeOutCubic,
                  margin: const EdgeInsets.symmetric(horizontal: 2),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 11,
                  ),
                  decoration: BoxDecoration(
                    color: selected == index
                        ? AppColors.primary
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        items[index].icon,
                        size: 17,
                        color: selected == index
                            ? Colors.white
                            : AppColors.textSecondary,
                      ),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          items[index].label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelMedium
                              ?.copyWith(
                                color: selected == index
                                    ? Colors.white
                                    : AppColors.textSecondary,
                                fontWeight: selected == index
                                    ? FontWeight.w800
                                    : FontWeight.w700,
                              ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _EventsEmptyPanel extends StatelessWidget {
  const _EventsEmptyPanel({
    required this.title,
    required this.message,
    required this.icon,
  });

  final String title;
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: PremiumCard(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Icon(icon, color: AppColors.primary, size: 30),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EventInfoChip extends StatelessWidget {
  const _EventInfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.84),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: AppColors.primary),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _EventNotice extends StatelessWidget {
  const _EventNotice({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppColors.warning, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w700,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
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
    final category = event['category']?.toString().trim();
    final useBookingSnapshot = [
      'reserved',
      'waitlisted',
      'attended',
    ].contains(bookingStatus);
    final payAtVenue = useBookingSnapshot
        ? booking['price_amount_snapshot'] != null
        : event['pricing_type'] == 'pay_at_venue';
    final pricing = payAtVenue
        ? '${_money(useBookingSnapshot ? booking['price_amount_snapshot'] : event['price_amount'], useBookingSnapshot ? booking['currency_snapshot'] : event['currency'])} at venue'
        : 'Free';
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
      glowColor: booking.isEmpty ? AppColors.primary : bookingColor,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
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
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    Text(
                      _month(event['starts_at']),
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      _day(event['starts_at']),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            color: AppColors.textPrimary,
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
                    Wrap(
                      spacing: 7,
                      runSpacing: 6,
                      children: [
                        if (category != null && category.isNotEmpty)
                          _EventStatusBadge(
                            label: category,
                            color: AppColors.accentPurple,
                          ),
                        _EventStatusBadge(
                          label: event['scope'] == 'global'
                              ? 'Atlas event'
                              : 'Gym event',
                          color: AppColors.primary,
                        ),
                      ],
                    ),
                    const SizedBox(height: 9),
                    Text(
                      event['title']?.toString() ?? 'Event',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${_time(event['starts_at'])} · ${event['location_name'] ?? 'Location TBA'}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const Padding(
                padding: EdgeInsets.only(top: 4),
                child: Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _EventInfoChip(icon: Icons.payments_outlined, label: pricing),
              _EventInfoChip(
                icon: Icons.group_outlined,
                label: event['capacity'] == null
                    ? 'Open capacity'
                    : '${event['available_spots'] ?? 0} spots left',
              ),
              if (booking.isNotEmpty)
                _EventStatusBadge(label: bookingLabel, color: bookingColor),
            ],
          ),
        ],
      ),
    );
  }
}

class _EventStatusBadge extends StatelessWidget {
  const _EventStatusBadge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: color,
          fontWeight: FontWeight.w900,
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
