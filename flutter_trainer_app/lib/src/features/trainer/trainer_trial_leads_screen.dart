import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_gradients.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/empty_state.dart';
import '../../../core/widgets/premium_app_bar.dart';
import '../../../core/widgets/premium_card.dart';
import '../../../core/widgets/status_badge.dart';
import '../../core/pagination.dart';
import 'trainer_repository.dart';

class TrainerTrialLeadsScreen extends StatefulWidget {
  const TrainerTrialLeadsScreen({
    super.key,
    required this.repository,
    this.initialTrialRequestId,
  });

  final TrainerRepository repository;
  final int? initialTrialRequestId;

  @override
  State<TrainerTrialLeadsScreen> createState() =>
      _TrainerTrialLeadsScreenState();
}

class _TrainerTrialLeadsScreenState extends State<TrainerTrialLeadsScreen> {
  final TextEditingController _searchController = TextEditingController();
  List<Map<String, dynamic>> _leads = const [];
  ApiPagination _pagination = const ApiPagination.singlePage();
  String _status = '';
  bool _loading = true;
  bool _loadingMore = false;
  bool _openedInitialLead = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchTrialRequests(
        status: _status,
        search: _searchController.text,
      );
      if (!mounted) return;
      setState(() {
        _leads = apiPageItems(response);
        _pagination = ApiPagination.fromResponse(response);
        _loading = false;
      });
      await _openInitialLeadOnce();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error.toString();
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_pagination.hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final response = await widget.repository.fetchTrialRequests(
        page: _pagination.nextPage,
        status: _status,
        search: _searchController.text,
      );
      if (!mounted) return;
      setState(() {
        _leads = mergeApiPageItems(_leads, apiPageItems(response));
        _pagination = ApiPagination.fromResponse(response);
      });
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openInitialLeadOnce() async {
    final id = widget.initialTrialRequestId;
    if (_openedInitialLead || id == null) return;
    _openedInitialLead = true;
    Map<String, dynamic>? lead;
    for (final item in _leads) {
      if (_int(item['id']) == id) {
        lead = item;
        break;
      }
    }
    if (lead == null) {
      try {
        final response = await widget.repository.fetchTrialRequest(id);
        lead = _map(response['data']);
      } catch (_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('This trial lead is no longer assigned to you.'),
            ),
          );
        }
        return;
      }
    }
    if (!mounted || lead.isEmpty) return;
    final selectedLead = lead;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _openLead(selectedLead);
    });
  }

  Future<void> _setStatus(String value) async {
    if (_status == value) return;
    setState(() => _status = value);
    await _load();
  }

  Future<void> _openLead(Map<String, dynamic> lead) async {
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _TrialLeadDetailSheet(
        lead: lead,
        onUpdate: (payload) async {
          final id = _int(lead['id']);
          if (id == null) return;
          await widget.repository.updateTrialRequest(id, payload);
        },
      ),
    );
    if (changed == true && mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PremiumAppBar(
        title: 'Trial leads',
        subtitle: 'Your assigned visits and follow-ups',
        actions: [
          IconButton(
            tooltip: 'Refresh trial leads',
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
              child: Column(
                children: [
                  TextField(
                    controller: _searchController,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (_) => _load(),
                    decoration: InputDecoration(
                      hintText: 'Search name, phone or email',
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: IconButton(
                        tooltip: 'Search',
                        onPressed: _load,
                        icon: const Icon(Icons.arrow_forward_rounded),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _StatusFilter(
                            label: 'All',
                            value: '',
                            selected: _status,
                            onSelected: _setStatus,
                          ),
                          _StatusFilter(
                            label: 'New',
                            value: 'pending',
                            selected: _status,
                            onSelected: _setStatus,
                          ),
                          _StatusFilter(
                            label: 'Accepted',
                            value: 'accepted',
                            selected: _status,
                            onSelected: _setStatus,
                          ),
                          _StatusFilter(
                            label: 'Visited',
                            value: 'completed',
                            selected: _status,
                            onSelected: _setStatus,
                          ),
                          _StatusFilter(
                            label: 'Converted',
                            value: 'converted',
                            selected: _status,
                            onSelected: _setStatus,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(child: _body()),
          ],
        ),
      ),
    );
  }

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return ListView(
        children: [
          const SizedBox(height: 96),
          EmptyState(
            title: 'Trial leads could not load',
            message: _error!,
            icon: Icons.cloud_off_outlined,
            action: FilledButton(
              onPressed: _load,
              child: const Text('Try again'),
            ),
          ),
        ],
      );
    }
    if (_leads.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          children: const [
            SizedBox(height: 96),
            EmptyState(
              title: 'No assigned trial leads',
              message:
                  'A lead appears here as soon as your gym assigns you as its follow-up trainer.',
              icon: Icons.person_search_outlined,
            ),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(AppSpacing.lg, 0, AppSpacing.lg, 96),
        itemCount: _leads.length + (_pagination.hasMore ? 1 : 0),
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (_, index) {
          if (index == _leads.length) {
            return Center(
              child: OutlinedButton.icon(
                onPressed: _loadingMore ? null : _loadMore,
                icon: _loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: Text(_loadingMore ? 'Loading...' : 'Load more'),
              ),
            );
          }
          return _TrialLeadCard(
            lead: _leads[index],
            onTap: () => _openLead(_leads[index]),
          );
        },
      ),
    );
  }
}

class _StatusFilter extends StatelessWidget {
  const _StatusFilter({
    required this.label,
    required this.value,
    required this.selected,
    required this.onSelected,
  });
  final String label;
  final String value;
  final String selected;
  final Future<void> Function(String) onSelected;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected == value,
        onSelected: (_) => onSelected(value),
      ),
    );
  }
}

class _TrialLeadCard extends StatelessWidget {
  const _TrialLeadCard({required this.lead, required this.onTap});
  final Map<String, dynamic> lead;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final gym = _map(lead['gym']);
    final branch = _map(lead['branch']);
    final name = _leadName(lead);
    return PremiumCard(
      onTap: onTap,
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(17),
                ),
                child: Center(
                  child: Text(
                    name.substring(0, 1).toUpperCase(),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [gym['name'], branch['name']]
                          .where(
                            (item) =>
                                item?.toString().trim().isNotEmpty == true,
                          )
                          .join(' · '),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: _title(lead['status']?.toString() ?? 'pending'),
                color: AppColors.statusColor(
                  lead['status']?.toString() ?? 'pending',
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InfoPill(
                icon: Icons.calendar_today_outlined,
                label: _preferredSlot(lead),
              ),
              if (lead['phone']?.toString().trim().isNotEmpty == true)
                _InfoPill(
                  icon: Icons.phone_outlined,
                  label: lead['phone'].toString(),
                ),
            ],
          ),
          if (lead['notes']?.toString().trim().isNotEmpty == true) ...[
            const SizedBox(height: 12),
            Text(
              lead['notes'].toString(),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ],
      ),
    );
  }
}

class _TrialLeadDetailSheet extends StatefulWidget {
  const _TrialLeadDetailSheet({required this.lead, required this.onUpdate});
  final Map<String, dynamic> lead;
  final Future<void> Function(Map<String, dynamic>) onUpdate;

  @override
  State<_TrialLeadDetailSheet> createState() => _TrialLeadDetailSheetState();
}

class _TrialLeadDetailSheetState extends State<_TrialLeadDetailSheet> {
  late final TextEditingController _notesController;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _notesController = TextEditingController(
      text: widget.lead['notes']?.toString() ?? '',
    );
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _save({String? status}) async {
    setState(() => _saving = true);
    try {
      await widget.onUpdate({
        'notes': _notesController.text.trim(),
        if (status != null) 'status': status,
      });
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _launch(String scheme, String value) async {
    final uri = Uri(scheme: scheme, path: value);
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No compatible app is available.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final lead = widget.lead;
    final status = lead['status']?.toString() ?? 'pending';
    final phone = lead['phone']?.toString().trim() ?? '';
    final email = lead['email']?.toString().trim() ?? '';
    final gym = _map(lead['gym']);
    final branch = _map(lead['branch']);
    return Material(
      color: Colors.transparent,
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.92,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(
            22,
            12,
            22,
            MediaQuery.viewInsetsOf(context).bottom + 24,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 52,
                  height: 5,
                  decoration: BoxDecoration(
                    color: AppColors.strokeStrong,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      _leadName(lead),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                  ),
                  StatusBadge(
                    label: _title(status),
                    color: AppColors.statusColor(status),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                [gym['name'], branch['name'], _preferredSlot(lead)]
                    .where((item) => item?.toString().trim().isNotEmpty == true)
                    .join(' · '),
                style: const TextStyle(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  if (phone.isNotEmpty)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _launch('tel', phone),
                        icon: const Icon(Icons.call_outlined),
                        label: const Text('Call'),
                      ),
                    ),
                  if (phone.isNotEmpty && email.isNotEmpty)
                    const SizedBox(width: 10),
                  if (email.isNotEmpty)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _launch('mailto', email),
                        icon: const Icon(Icons.email_outlined),
                        label: const Text('Email'),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 18),
              TextField(
                controller: _notesController,
                minLines: 4,
                maxLines: 7,
                decoration: const InputDecoration(
                  labelText: 'Follow-up notes',
                  alignLabelWithHint: true,
                  hintText:
                      'Record contact outcome, preferences or visit context',
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _saving ? null : () => _save(),
                icon: const Icon(Icons.save_outlined),
                label: Text(_saving ? 'Saving...' : 'Save notes'),
              ),
              if (status == 'pending') ...[
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _saving
                            ? null
                            : () => _save(status: 'rejected'),
                        child: const Text('Not proceeding'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: FilledButton(
                        onPressed: _saving
                            ? null
                            : () => _save(status: 'accepted'),
                        child: const Text('Accept trial'),
                      ),
                    ),
                  ],
                ),
              ] else if (status == 'accepted') ...[
                const SizedBox(height: 10),
                FilledButton.icon(
                  onPressed: _saving ? null : () => _save(status: 'completed'),
                  icon: const Icon(Icons.check_circle_outline),
                  label: const Text('Mark visit completed'),
                ),
              ],
              const SizedBox(height: 8),
              Text(
                'Trainer assignment is controlled by the gym. Reassigned leads automatically leave this list.',
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textMuted),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoPill extends StatelessWidget {
  const _InfoPill({required this.icon, required this.label});
  final IconData icon;
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
    decoration: BoxDecoration(
      color: AppColors.surfaceSoft,
      borderRadius: BorderRadius.circular(99),
      border: Border.all(color: AppColors.stroke),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: AppColors.primary),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: AppColors.textSecondary,
          ),
        ),
      ],
    ),
  );
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};
int? _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '');
String _leadName(Map<String, dynamic> lead) {
  final member = _map(lead['member']);
  final name = member['name']?.toString().trim().isNotEmpty == true
      ? member['name'].toString()
      : lead['name']?.toString().trim();
  return name == null || name.isEmpty ? 'Trial lead' : name;
}

String _title(String value) => value
    .split('_')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');
String _preferredSlot(Map<String, dynamic> lead) {
  final rawDate = lead['preferred_date']?.toString();
  final rawTime = lead['preferred_time']?.toString().trim();
  String date = 'Flexible date';
  final parsed = rawDate == null ? null : DateTime.tryParse(rawDate);
  if (parsed != null) date = DateFormat('d MMM yyyy').format(parsed.toLocal());
  return rawTime == null || rawTime.isEmpty ? date : '$date · $rawTime';
}
