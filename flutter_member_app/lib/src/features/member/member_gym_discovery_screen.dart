import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/models.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import 'member_repository.dart';
import 'member_trial_requests_screen.dart';

class MemberGymDiscoveryScreen extends StatefulWidget {
  const MemberGymDiscoveryScreen({
    super.key,
    required this.repository,
    required this.onRefreshParent,
    required this.currentUser,
    required this.onOpenTrialRequests,
  });

  final MemberRepository repository;
  final Future<void> Function() onRefreshParent;
  final MemberUser currentUser;
  final Future<void> Function({
    Map<String, dynamic>? initialGym,
    bool initialStatusTab,
  })
  onOpenTrialRequests;

  @override
  State<MemberGymDiscoveryScreen> createState() =>
      _MemberGymDiscoveryScreenState();
}

class _MemberGymDiscoveryScreenState extends State<MemberGymDiscoveryScreen> {
  final TextEditingController _searchController = TextEditingController();
  bool _loading = true;
  bool _savingGym = false;
  String? _error;
  bool _locationLoading = false;
  String? _locationError;
  double? _currentLatitude;
  double? _currentLongitude;
  List<Map<String, dynamic>> _gyms = const [];
  List<Map<String, dynamic>> _savedGyms = const [];
  _GymDiscoveryFilters _filters = const _GymDiscoveryFilters();
  double _nearbyDistanceKm = 25;

  static const List<double> _nearbyDistanceOptions = [5, 10, 25, 50, 100];

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
      final results = await Future.wait<Map<String, dynamic>>([
        widget.repository.fetchPublicGyms(filters: _discoveryQuery()),
        widget.repository.fetchSavedGyms(),
      ]);

      _gyms = (results[0]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _savedGyms = (results[1]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Map<String, dynamic> _discoveryQuery() {
    final query = Map<String, dynamic>.from(_filters.toQuery());
    if (_currentLatitude != null && _currentLongitude != null) {
      query['latitude'] = _currentLatitude;
      query['longitude'] = _currentLongitude;
      query['distance'] = _nearbyDistanceKm;
    }
    return query;
  }

  Future<void> _useCurrentLocation() async {
    if (_locationLoading) {
      return;
    }

    setState(() {
      _locationLoading = true;
      _locationError = null;
    });

    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!enabled) {
        throw Exception('Turn on location services to find nearby gyms.');
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied) {
        throw Exception('Location permission is needed to sort gyms nearby.');
      }
      if (permission == LocationPermission.deniedForever) {
        throw Exception('Enable location permission from app settings.');
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 10),
        ),
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _currentLatitude = position.latitude;
        _currentLongitude = position.longitude;
        _locationLoading = false;
      });
      await _load();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      final message = exception.toString().replaceFirst('Exception: ', '');
      setState(() {
        _locationError = message;
        _locationLoading = false;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _clearCurrentLocation() async {
    setState(() {
      _currentLatitude = null;
      _currentLongitude = null;
      _locationError = null;
    });
    await _load();
  }

  Future<void> _changeNearbyDistance(double distanceKm) async {
    if (_nearbyDistanceKm == distanceKm) {
      return;
    }

    setState(() => _nearbyDistanceKm = distanceKm);
    if (_currentLatitude != null && _currentLongitude != null) {
      await _load();
    }
  }

  Future<void> _applySearch() async {
    setState(
      () => _filters = _filters.copyWith(search: _searchController.text.trim()),
    );
    await _load();
  }

  Future<void> _openFilters() async {
    final cities =
        _gyms
            .map((gym) => gym['city']?.toString().trim() ?? '')
            .where((city) => city.isNotEmpty)
            .toSet()
            .toList()
          ..sort();
    final facilities =
        _gyms
            .expand(
              (gym) => (gym['facilities'] as List<dynamic>? ?? const []).map(
                (item) => Map<String, dynamic>.from(item as Map),
              ),
            )
            .map(
              (item) => _FacilityOption(
                slug: item['slug']?.toString() ?? '',
                name: item['name']?.toString() ?? 'Facility',
              ),
            )
            .where((item) => item.slug.isNotEmpty)
            .toSet()
            .toList()
          ..sort((left, right) => left.name.compareTo(right.name));

    final next = await showModalBottomSheet<_GymDiscoveryFilters>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _GymFiltersSheet(
        initialFilters: _filters,
        cities: cities,
        facilities: facilities,
      ),
    );

    if (next == null) {
      return;
    }

    setState(() {
      _filters = next;
      _searchController.text = next.search;
    });
    await _load();
  }

  Future<Map<String, dynamic>> _fetchGymDetail(Map<String, dynamic> gym) async {
    if (gym['branches'] is List && gym.containsKey('timings')) {
      return Map<String, dynamic>.from(gym);
    }

    final slug = gym['slug']?.toString();
    if (slug == null || slug.isEmpty) {
      throw Exception('Gym detail is unavailable.');
    }

    final detailFilters = <String, dynamic>{};
    if (_currentLatitude != null && _currentLongitude != null) {
      detailFilters['latitude'] = _currentLatitude;
      detailFilters['longitude'] = _currentLongitude;
    }

    final response = await widget.repository.fetchPublicGymDetail(
      slug,
      filters: detailFilters.isEmpty ? null : detailFilters,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> _openGymDetail(Map<String, dynamic> gym) async {
    try {
      final detail = await _fetchGymDetail(gym);
      if (!mounted) {
        return;
      }

      await Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (_) => MemberGymDetailScreen(
            repository: widget.repository,
            detail: detail,
            isSaved: _isSavedGym(detail),
            currentUser: widget.currentUser,
            onRefreshParent: () async {
              await _load();
              await widget.onRefreshParent();
            },
          ),
        ),
      );

      if (mounted) {
        await _load();
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _toggleSavedGym(Map<String, dynamic> gym) async {
    final gymId = (gym['id'] as num?)?.toInt();
    if (gymId == null || _savingGym) {
      return;
    }

    setState(() => _savingGym = true);
    final isSaved = _isSavedGym(gym);
    final messenger = ScaffoldMessenger.of(context);

    try {
      if (isSaved) {
        await widget.repository.removeSavedGym(gymId);
      } else {
        await widget.repository.saveGym(gymId);
      }

      if (!mounted) {
        return;
      }

      await _load();
      await widget.onRefreshParent();
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            isSaved
                ? 'Gym removed from saved list.'
                : 'Gym saved successfully.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingGym = false);
      }
    }
  }

  bool _isSavedGym(Map<String, dynamic> gym) {
    final gymId = (gym['id'] as num?)?.toInt();
    if (gymId == null) {
      return false;
    }

    return _savedGyms.any((item) => (item['id'] as num?)?.toInt() == gymId);
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Nearby Gyms',
      subtitle: 'Discover, compare and shortlist your next training space',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
        IconButton(
          onPressed: () => widget.onOpenTrialRequests(initialStatusTab: true),
          icon: const Icon(Icons.flag_outlined),
        ),
        IconButton(
          onPressed: _openFilters,
          icon: const Icon(Icons.tune_rounded),
        ),
      ],
      body: _loading
          ? const _DiscoverySkeleton()
          : _error != null
          ? ErrorStateView(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  _DiscoveryHeroPanel(
                    gymCount: _gyms.length,
                    savedCount: _savedGyms.length,
                    filterCount: _filters.hasActiveFilters
                        ? _filters.activeCount
                        : 0,
                    filters: _filters,
                    locationEnabled:
                        _currentLatitude != null && _currentLongitude != null,
                    locationLoading: _locationLoading,
                    locationError: _locationError,
                    nearbyDistanceKm: _nearbyDistanceKm,
                    nearbyDistanceOptions: _nearbyDistanceOptions,
                    searchController: _searchController,
                    onSearch: _applySearch,
                    onClearSearch: () async {
                      _searchController.clear();
                      await _applySearch();
                    },
                    onOpenFilters: _openFilters,
                    onUseLocation: _useCurrentLocation,
                    onClearLocation: _clearCurrentLocation,
                    onDistanceChanged: _changeNearbyDistance,
                  ),
                  if (_savedGyms.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.lg),
                    const _DiscoverySectionTitle(
                      title: 'Saved gyms',
                      action: 'Shortlist',
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 132,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _savedGyms.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 12),
                        itemBuilder: (context, index) {
                          final gym = _savedGyms[index];
                          return _SavedGymPill(
                            gym: gym,
                            onTap: () => _openGymDetail(gym),
                          );
                        },
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  _DiscoverySectionTitle(
                    title: _gyms.isEmpty
                        ? 'No gyms available'
                        : '${_gyms.length} gyms ready to explore',
                    action: 'Filters',
                    onTap: _openFilters,
                  ),
                  const SizedBox(height: 12),
                  if (_gyms.isEmpty)
                    const _DiscoveryEmptyPanel(
                      title: 'No gyms match this search',
                      message:
                          'Try widening the price range, clearing a facility filter, or searching a different city.',
                      icon: Icons.travel_explore_rounded,
                    )
                  else
                    ..._gyms.asMap().entries.map(
                      (entry) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: RevealOnBuild(
                          delay: Duration(milliseconds: 40 * entry.key),
                          child: _NearbyGymCard(
                            gym: entry.value,
                            isSaved: _isSavedGym(entry.value),
                            onTap: () => _openGymDetail(entry.value),
                            onToggleSaved: () => _toggleSavedGym(entry.value),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}

class MemberGymDetailScreen extends StatefulWidget {
  const MemberGymDetailScreen({
    super.key,
    required this.repository,
    required this.detail,
    required this.isSaved,
    required this.currentUser,
    required this.onRefreshParent,
  });

  final MemberRepository repository;
  final Map<String, dynamic> detail;
  final bool isSaved;
  final MemberUser currentUser;
  final Future<void> Function() onRefreshParent;

  @override
  State<MemberGymDetailScreen> createState() => _MemberGymDetailScreenState();
}

class _DiscoveryHeroPanel extends StatelessWidget {
  const _DiscoveryHeroPanel({
    required this.gymCount,
    required this.savedCount,
    required this.filterCount,
    required this.filters,
    required this.locationEnabled,
    required this.locationLoading,
    required this.locationError,
    required this.nearbyDistanceKm,
    required this.nearbyDistanceOptions,
    required this.searchController,
    required this.onSearch,
    required this.onClearSearch,
    required this.onOpenFilters,
    required this.onUseLocation,
    required this.onClearLocation,
    required this.onDistanceChanged,
  });

  final int gymCount;
  final int savedCount;
  final int filterCount;
  final _GymDiscoveryFilters filters;
  final bool locationEnabled;
  final bool locationLoading;
  final String? locationError;
  final double nearbyDistanceKm;
  final List<double> nearbyDistanceOptions;
  final TextEditingController searchController;
  final Future<void> Function() onSearch;
  final Future<void> Function() onClearSearch;
  final VoidCallback onOpenFilters;
  final VoidCallback onUseLocation;
  final VoidCallback onClearLocation;
  final ValueChanged<double> onDistanceChanged;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.96, end: 1),
      duration: const Duration(milliseconds: 420),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) =>
          Transform.scale(scale: value, child: child),
      child: Container(
        padding: const EdgeInsets.all(22),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(32),
          gradient: const LinearGradient(
            colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD), Color(0xFFC58BF2)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.24),
              blurRadius: 26,
              offset: const Offset(0, 16),
            ),
          ],
        ),
        child: Stack(
          children: [
            Positioned(
              right: -34,
              top: -44,
              child: _DiscoveryOrb(
                size: 132,
                color: Colors.white.withValues(alpha: 0.20),
              ),
            ),
            Positioned(
              right: 44,
              bottom: 58,
              child: _DiscoveryOrb(
                size: 46,
                color: Colors.white.withValues(alpha: 0.16),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Find your training floor',
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Compare gyms, trials, pricing, and facilities in one clean view.',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.88),
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    _DiscoveryHeaderBadge(
                      value: '$gymCount',
                      label: 'gyms',
                      icon: Icons.storefront_rounded,
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: _DiscoveryHeroStat(
                        value: '$savedCount',
                        label: 'Saved',
                        icon: Icons.bookmark_rounded,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DiscoveryHeroStat(
                        value: '$filterCount',
                        label: 'Filters',
                        icon: Icons.tune_rounded,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DiscoveryHeroStat(
                        value: filters.openNow ? 'Now' : 'All',
                        label: 'Open',
                        icon: Icons.schedule_rounded,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primary.withValues(alpha: 0.12),
                        blurRadius: 18,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: TextField(
                    controller: searchController,
                    onSubmitted: (_) => onSearch(),
                    decoration: InputDecoration(
                      hintText: 'Search gyms, city, or facility',
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (searchController.text.trim().isNotEmpty)
                            IconButton(
                              onPressed: onClearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                          IconButton(
                            onPressed: onSearch,
                            icon: const Icon(Icons.arrow_forward_rounded),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _DiscoveryFilterPill(
                      label: filters.hasActiveFilters
                          ? '$filterCount filters applied'
                          : 'All gyms',
                      icon: Icons.filter_alt_rounded,
                      onTap: onOpenFilters,
                    ),
                    if (filters.city.isNotEmpty)
                      _DiscoveryFilterPill(
                        label: filters.city,
                        icon: Icons.location_city_rounded,
                        onTap: onOpenFilters,
                      ),
                    if (filters.trialAvailable)
                      _DiscoveryFilterPill(
                        label: 'Trial available',
                        icon: Icons.flash_on_rounded,
                        onTap: onOpenFilters,
                      ),
                    if (filters.verifiedOnly)
                      _DiscoveryFilterPill(
                        label: 'Verified',
                        icon: Icons.verified_rounded,
                        onTap: onOpenFilters,
                      ),
                    if (filters.featuredOnly)
                      _DiscoveryFilterPill(
                        label: 'Featured',
                        icon: Icons.workspace_premium_rounded,
                        onTap: onOpenFilters,
                      ),
                    if (filters.openNow)
                      _DiscoveryFilterPill(
                        label: 'Open now',
                        icon: Icons.schedule_rounded,
                        onTap: onOpenFilters,
                      ),
                    _DiscoveryLocationPill(
                      enabled: locationEnabled,
                      loading: locationLoading,
                      error: locationError,
                      distanceKm: nearbyDistanceKm,
                      onUseLocation: onUseLocation,
                      onClearLocation: onClearLocation,
                    ),
                  ],
                ),
                if (locationEnabled) ...[
                  const SizedBox(height: 12),
                  _NearbyDistanceRail(
                    options: nearbyDistanceOptions,
                    selected: nearbyDistanceKm,
                    onChanged: onDistanceChanged,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _NearbyDistanceRail extends StatelessWidget {
  const _NearbyDistanceRail({
    required this.options,
    required this.selected,
    required this.onChanged,
  });

  final List<double> options;
  final double selected;
  final ValueChanged<double> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          Text(
            'Search radius',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.82),
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 8),
          ...options.map((distance) {
            final active = distance == selected;
            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: GestureDetector(
                onTap: () => onChanged(distance),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: active
                        ? Colors.white
                        : Colors.white.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.24),
                    ),
                  ),
                  child: Text(
                    '${distance.toStringAsFixed(0)} km',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: active ? AppColors.primary : Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}

class _DiscoveryLocationPill extends StatelessWidget {
  const _DiscoveryLocationPill({
    required this.enabled,
    required this.loading,
    required this.error,
    required this.distanceKm,
    required this.onUseLocation,
    required this.onClearLocation,
  });

  final bool enabled;
  final bool loading;
  final String? error;
  final double distanceKm;
  final VoidCallback onUseLocation;
  final VoidCallback onClearLocation;

  @override
  Widget build(BuildContext context) {
    final label = loading
        ? 'Locating...'
        : enabled
        ? 'Nearby ${distanceKm.toStringAsFixed(0)} km'
        : error != null
        ? 'Location unavailable'
        : 'Use current location';
    final icon = enabled
        ? Icons.my_location_rounded
        : error != null
        ? Icons.location_off_rounded
        : Icons.near_me_rounded;
    final tap = enabled ? onClearLocation : onUseLocation;

    return GestureDetector(
      onTap: loading ? null : tap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
        decoration: BoxDecoration(
          color: enabled
              ? Colors.white
              : Colors.white.withValues(alpha: error == null ? 0.20 : 0.16),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: enabled
                ? Colors.white
                : Colors.white.withValues(alpha: 0.22),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (loading)
              SizedBox(
                width: 14,
                height: 14,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  valueColor: AlwaysStoppedAnimation<Color>(
                    enabled ? AppColors.primary : Colors.white,
                  ),
                ),
              )
            else
              Icon(
                icon,
                color: enabled ? AppColors.primary : Colors.white,
                size: 15,
              ),
            const SizedBox(width: 6),
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: enabled ? AppColors.primary : Colors.white,
                fontWeight: FontWeight.w900,
              ),
            ),
            if (enabled) ...[
              const SizedBox(width: 6),
              Icon(
                Icons.close_rounded,
                color: AppColors.primary.withValues(alpha: 0.72),
                size: 14,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DiscoveryHeaderBadge extends StatelessWidget {
  const _DiscoveryHeaderBadge({
    required this.value,
    required this.label,
    required this.icon,
  });

  final String value;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 82,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.20),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white.withValues(alpha: 0.22)),
      ),
      child: Column(
        children: [
          Icon(icon, color: Colors.white, size: 20),
          const SizedBox(height: 6),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.78),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _DiscoveryHeroStat extends StatelessWidget {
  const _DiscoveryHeroStat({
    required this.value,
    required this.label,
    required this.icon,
  });

  final String value;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(icon, color: Colors.white, size: 17),
          const SizedBox(width: 7),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.78),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DiscoveryFilterPill extends StatelessWidget {
  const _DiscoveryFilterPill({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.20),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: Colors.white, size: 15),
            const SizedBox(width: 6),
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SavedGymPill extends StatelessWidget {
  const _SavedGymPill({required this.gym, required this.onTap});

  final Map<String, dynamic> gym;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final heroImage = _gymHeroImage(gym);
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 260,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(28),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.11),
              blurRadius: 20,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Row(
          children: [
            AppNetworkImage(
              imageUrl: heroImage,
              height: 88,
              width: 88,
              borderRadius: 22,
              placeholderIcon: Icons.storefront_rounded,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const StatusBadge(
                    label: 'Saved',
                    icon: Icons.bookmark_rounded,
                    color: Color(0xFF92A3FD),
                  ),
                  const Spacer(),
                  Text(
                    gym['name']?.toString() ?? 'Saved gym',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    [
                      gym['city']?.toString() ?? '',
                      gym['state']?.toString() ?? '',
                    ].where((item) => item.trim().isNotEmpty).join(', '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DiscoverySectionTitle extends StatelessWidget {
  const _DiscoverySectionTitle({
    required this.title,
    required this.action,
    this.onTap,
  });

  final String title;
  final String action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        if (onTap == null)
          Text(
            action,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.primary,
              fontWeight: FontWeight.w900,
            ),
          )
        else
          TextButton.icon(
            onPressed: onTap,
            icon: const Icon(Icons.filter_alt_outlined, size: 18),
            label: Text(action),
          ),
      ],
    );
  }
}

class _DiscoveryEmptyPanel extends StatelessWidget {
  const _DiscoveryEmptyPanel({
    required this.title,
    required this.message,
    required this.icon,
  });

  final String title;
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
              ),
              borderRadius: BorderRadius.circular(22),
            ),
            child: Icon(icon, color: Colors.white),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _DiscoveryOrb extends StatelessWidget {
  const _DiscoveryOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

class _MemberGymDetailScreenState extends State<MemberGymDetailScreen> {
  late final Map<String, dynamic> _detail = Map<String, dynamic>.from(
    widget.detail,
  );
  late bool _isSaved = widget.isSaved;
  bool _savingGym = false;

  Future<void> _toggleSaved() async {
    final gymId = (_detail['id'] as num?)?.toInt();
    if (gymId == null || _savingGym) {
      return;
    }

    setState(() => _savingGym = true);
    try {
      final messenger = ScaffoldMessenger.of(context);
      if (_isSaved) {
        await widget.repository.removeSavedGym(gymId);
      } else {
        await widget.repository.saveGym(gymId);
      }

      if (!mounted) {
        return;
      }

      setState(() => _isSaved = !_isSaved);
      await widget.onRefreshParent();
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            _isSaved
                ? 'Gym saved successfully.'
                : 'Gym removed from saved list.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingGym = false);
      }
    }
  }

  Future<void> _openTrialSheet() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => MemberTrialRequestsScreen(
          repository: widget.repository,
          currentUser: widget.currentUser,
          initialGym: _detail,
        ),
      ),
    );

    if (mounted) {
      await widget.onRefreshParent();
    }
  }

  Future<void> _openMap() async {
    final latitude = _asDoubleOrNull(_detail['latitude']);
    final longitude = _asDoubleOrNull(_detail['longitude']);
    final address = _fullAddress(_detail);

    final String query;
    if (latitude != null && longitude != null) {
      query = '$latitude,$longitude';
    } else if (address.isNotEmpty) {
      query = address;
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Map location is unavailable.')),
      );
      return;
    }

    final uri = Uri.https('www.google.com', '/maps/search/', {
      'api': '1',
      'query': query,
    });
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open maps on this device.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    final gallery = {
      if ((detail['cover_image_url']?.toString() ?? '').isNotEmpty)
        detail['cover_image_url'].toString(),
      ...((detail['photo_urls'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .where((item) => item.isNotEmpty)),
    }.toList();
    final facilities = (detail['facilities'] as List<dynamic>? ?? const [])
        .map(
          (item) => item is Map
              ? Map<String, dynamic>.from(item)
              : const <String, dynamic>{},
        )
        .toList();
    final trainers = (detail['trainers'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final branches = (detail['branches'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final plans = (detail['fees'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final isOpen = detail['is_open_now'] == true;
    final canShowPricing = detail['pricing_visible'] == true;
    final canShowContact = detail['contact_visible'] == true;
    final feeSummary = Map<String, dynamic>.from(
      detail['fee_summary'] as Map? ?? const {},
    );

    return AppGradientScaffold(
      title: detail['name']?.toString() ?? 'Gym Detail',
      actions: [
        IconButton(
          onPressed: _savingGym ? null : _toggleSaved,
          icon: Icon(
            _isSaved ? Icons.bookmark_rounded : Icons.bookmark_border_rounded,
          ),
        ),
      ],
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _GymDetailHero(
            detail: detail,
            imageUrl: gallery.isEmpty ? null : gallery.first,
            isOpen: isOpen,
            canShowPricing: canShowPricing,
            feeSummary: feeSummary,
          ),
          if (gallery.length > 1) ...[
            const SizedBox(height: AppSpacing.lg),
            _DetailSection(
              title: 'Gallery',
              subtitle: 'A quick visual sweep of the training space.',
              child: SizedBox(
                height: 108,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: gallery.length,
                  separatorBuilder: (_, __) =>
                      const SizedBox(width: AppSpacing.sm),
                  itemBuilder: (context, index) => AppNetworkImage(
                    imageUrl: gallery[index],
                    height: 108,
                    width: 150,
                    borderRadius: 20,
                    placeholderIcon: Icons.image_rounded,
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          _DetailSection(
            title: 'Facilities',
            subtitle: 'Amenities available on the public listing.',
            child: facilities.isEmpty
                ? const _DiscoveryEmptyPanel(
                    title: 'No facilities listed yet',
                    message:
                        'This gym has not published facility highlights on its public profile yet.',
                    icon: Icons.grid_view_rounded,
                  )
                : Wrap(
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.sm,
                    children: facilities
                        .map(
                          (facility) => StatusBadge(
                            label: facility['name']?.toString() ?? 'Facility',
                            color: AppColors.textSecondary,
                          ),
                        )
                        .toList(),
                  ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _DetailSection(
            title: 'Trainer section',
            subtitle: 'Visible trainer profiles and coaching highlights.',
            child: trainers.isEmpty
                ? const _DiscoveryEmptyPanel(
                    title: 'Trainer profiles coming soon',
                    message:
                        'This public listing does not have visible trainer profiles yet.',
                    icon: Icons.groups_rounded,
                  )
                : Column(
                    children: trainers
                        .map(
                          (trainer) => Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.sm,
                            ),
                            child: _TrainerCard(trainer: trainer),
                          ),
                        )
                        .toList(),
                  ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _DetailSection(
            title: 'Membership plans',
            subtitle: canShowPricing
                ? 'Pricing shown only because this gym made it public.'
                : 'Pricing is hidden by this gym right now.',
            child: !canShowPricing
                ? const _DiscoveryEmptyPanel(
                    title: 'Pricing hidden',
                    message:
                        'Use the trial CTA to connect with the gym and request the latest plan details.',
                    icon: Icons.visibility_off_rounded,
                  )
                : plans.isEmpty
                ? const _DiscoveryEmptyPanel(
                    title: 'No public plans yet',
                    message:
                        'The gym has not published active public membership plans yet.',
                    icon: Icons.sell_rounded,
                  )
                : Column(
                    children: plans
                        .map(
                          (plan) => Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.sm,
                            ),
                            child: _PlanCard(plan: plan),
                          ),
                        )
                        .toList(),
                  ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _DetailSection(
            title: 'Branches',
            subtitle: 'Branch locations currently visible from this listing.',
            child: branches.isEmpty
                ? const _DiscoveryEmptyPanel(
                    title: 'No branch data yet',
                    message:
                        'Branch-level listing details are not visible for this gym right now.',
                    icon: Icons.account_tree_rounded,
                  )
                : Column(
                    children: branches
                        .map(
                          (branch) => Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.sm,
                            ),
                            child: _BranchCard(branch: branch),
                          ),
                        )
                        .toList(),
                  ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _DetailSection(
            title: 'Location map',
            subtitle: 'Open the real gym location in maps before visiting.',
            child: _GymMapPanel(detail: detail, onOpenMap: _openMap),
          ),
          const SizedBox(height: AppSpacing.lg),
          _DetailSection(
            title: 'Contact and trial',
            subtitle: canShowContact
                ? 'The gym accepts public enquiries from this listing.'
                : 'Direct phone details are private, but you can still request a callback.',
            child: _ContactVisibilityPanel(
              canShowContact: canShowContact,
              trialAvailable: detail['trial_available'] == true,
              onRequestTrial: _openTrialSheet,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _savingGym ? null : _toggleSaved,
                  icon: Icon(
                    _isSaved
                        ? Icons.bookmark_remove_rounded
                        : Icons.bookmark_add_rounded,
                  ),
                  label: Text(_isSaved ? 'Saved' : 'Save Gym'),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: GradientButton(
                  onPressed: detail['trial_available'] == true
                      ? _openTrialSheet
                      : null,
                  label: detail['trial_available'] == true
                      ? 'Request Trial'
                      : 'Trial unavailable',
                  icon: Icons.flash_on_rounded,
                  expanded: true,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymMapPanel extends StatelessWidget {
  const _GymMapPanel({required this.detail, required this.onOpenMap});

  final Map<String, dynamic> detail;
  final VoidCallback onOpenMap;

  @override
  Widget build(BuildContext context) {
    final latitude = _asDoubleOrNull(detail['latitude']);
    final longitude = _asDoubleOrNull(detail['longitude']);
    final address = _fullAddress(detail);
    final hasLocation = latitude != null && longitude != null;

    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: const LinearGradient(
          colors: [Color(0xFFFFFFFF), Color(0xFFEAF5FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        children: [
          SizedBox(
            height: 170,
            child: Stack(
              children: [
                Positioned.fill(child: CustomPaint(painter: _SoftMapPainter())),
                Positioned.fill(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.white.withValues(alpha: 0.05),
                          Colors.white.withValues(alpha: 0.30),
                        ],
                      ),
                    ),
                  ),
                ),
                Center(
                  child: Container(
                    width: 70,
                    height: 70,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primary.withValues(alpha: 0.22),
                          blurRadius: 22,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.location_pin,
                      color: Color(0xFFFF6B6B),
                      size: 42,
                    ),
                  ),
                ),
                Positioned(
                  left: 14,
                  top: 14,
                  child: StatusBadge(
                    label: hasLocation ? 'Exact coordinates' : 'Address search',
                    color: hasLocation
                        ? AppColors.statusCompleted
                        : AppColors.warning,
                    icon: Icons.map_rounded,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  address.isEmpty ? 'Location not published' : address,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (hasLocation) ...[
                  const SizedBox(height: 4),
                  Text(
                    '${latitude.toStringAsFixed(5)}, ${longitude.toStringAsFixed(5)}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                GradientButton(
                  label: 'Open in Maps',
                  icon: Icons.map_rounded,
                  expanded: true,
                  onPressed: onOpenMap,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SoftMapPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final background = Paint()..color = const Color(0xFFEAF5FF);
    canvas.drawRect(Offset.zero & size, background);

    final roadPaint = Paint()
      ..color = Colors.white.withValues(alpha: 0.90)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 18
      ..strokeCap = StrokeCap.round;
    final accentRoadPaint = Paint()
      ..color = const Color(0xFF92A3FD).withValues(alpha: 0.24)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round;
    final parkPaint = Paint()..color = const Color(0xFFC7F3DF);

    canvas.drawCircle(
      Offset(size.width * 0.18, size.height * 0.24),
      44,
      parkPaint,
    );
    canvas.drawCircle(
      Offset(size.width * 0.82, size.height * 0.72),
      54,
      parkPaint,
    );

    final pathOne = Path()
      ..moveTo(-20, size.height * 0.70)
      ..quadraticBezierTo(
        size.width * 0.30,
        size.height * 0.42,
        size.width + 20,
        size.height * 0.54,
      );
    canvas.drawPath(pathOne, roadPaint);
    canvas.drawPath(pathOne, accentRoadPaint);

    final pathTwo = Path()
      ..moveTo(size.width * 0.18, -20)
      ..quadraticBezierTo(
        size.width * 0.48,
        size.height * 0.42,
        size.width * 0.34,
        size.height + 20,
      );
    canvas.drawPath(pathTwo, roadPaint);
    canvas.drawPath(pathTwo, accentRoadPaint);

    final pathThree = Path()
      ..moveTo(size.width * 0.72, -20)
      ..lineTo(size.width * 0.58, size.height + 20);
    canvas.drawPath(pathThree, roadPaint);
    canvas.drawPath(pathThree, accentRoadPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _ContactVisibilityPanel extends StatelessWidget {
  const _ContactVisibilityPanel({
    required this.canShowContact,
    required this.trialAvailable,
    required this.onRequestTrial,
  });

  final bool canShowContact;
  final bool trialAvailable;
  final VoidCallback onRequestTrial;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color:
                      (canShowContact ? AppColors.success : AppColors.primary)
                          .withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  canShowContact
                      ? Icons.mark_chat_read_rounded
                      : Icons.privacy_tip_rounded,
                  color: canShowContact ? AppColors.success : AppColors.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      canShowContact
                          ? 'Public enquiries enabled'
                          : 'Direct phone is private',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      canShowContact
                          ? 'Send your request and the gym can follow up.'
                          : 'This does not block you. Send a trial request and the gym receives your details.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          GradientButton(
            label: trialAvailable
                ? 'Request Trial / Callback'
                : 'Trial unavailable',
            icon: Icons.flash_on_rounded,
            expanded: true,
            onPressed: trialAvailable ? onRequestTrial : null,
          ),
        ],
      ),
    );
  }
}

class _NearbyGymCard extends StatelessWidget {
  const _NearbyGymCard({
    required this.gym,
    required this.isSaved,
    required this.onTap,
    required this.onToggleSaved,
  });

  final Map<String, dynamic> gym;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onToggleSaved;

  @override
  Widget build(BuildContext context) {
    final feeSummary = Map<String, dynamic>.from(
      gym['fee_summary'] as Map? ?? const {},
    );
    final facilities = (gym['facilities'] as List<dynamic>? ?? const [])
        .map(
          (item) => item is Map
              ? Map<String, dynamic>.from(item)
              : const <String, dynamic>{},
        )
        .take(4)
        .toList();
    final showPricing = gym['pricing_visible'] == true && feeSummary.isNotEmpty;
    final isOpen = gym['is_open_now'] == true;
    final heroImage = _gymHeroImage(gym);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(32),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.12),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            children: [
              AppNetworkImage(
                imageUrl: heroImage,
                height: 190,
                width: double.infinity,
                borderRadius: 32,
                placeholderIcon: Icons.storefront_rounded,
              ),
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(32),
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.transparent,
                        Colors.black.withValues(alpha: 0.42),
                      ],
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 12,
                left: 12,
                right: 12,
                child: Row(
                  children: [
                    StatusBadge(
                      label: isOpen ? 'Open now' : 'Closed',
                      color: isOpen ? AppColors.success : AppColors.warning,
                      icon: Icons.schedule_rounded,
                    ),
                    const Spacer(),
                    _DiscoverySaveButton(
                      isSaved: isSaved,
                      onPressed: onToggleSaved,
                    ),
                  ],
                ),
              ),
              Positioned(
                left: 16,
                right: 16,
                bottom: 16,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      gym['name']?.toString() ?? 'Gym',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      [
                        if ((gym['distance_km']?.toString() ?? '').isNotEmpty)
                          '${gym['distance_km']} km',
                        if ((gym['city']?.toString() ?? '').isNotEmpty)
                          gym['city'].toString(),
                      ].join(' • '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.86),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _DiscoveryCardPill(
                        icon: Icons.currency_rupee_rounded,
                        label: showPricing
                            ? 'From ${_money(feeSummary['min_price'])}'
                            : 'Pricing hidden',
                        color: const Color(0xFF92A3FD),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DiscoveryCardPill(
                        icon: Icons.flash_on_rounded,
                        label: gym['trial_available'] == true
                            ? 'Trial available'
                            : 'No trial',
                        color: gym['trial_available'] == true
                            ? const Color(0xFF40D9B8)
                            : AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
                if (facilities.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (gym['is_verified'] == true)
                        const StatusBadge(
                          label: 'Verified',
                          icon: Icons.verified_rounded,
                          color: Color(0xFF92A3FD),
                        ),
                      ...facilities.map(
                        (facility) => StatusBadge(
                          label: facility['name']?.toString() ?? 'Facility',
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 14),
                GradientButton(
                  onPressed: onTap,
                  label: 'View Details',
                  icon: Icons.arrow_forward_rounded,
                  expanded: true,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GymDetailHero extends StatelessWidget {
  const _GymDetailHero({
    required this.detail,
    required this.imageUrl,
    required this.isOpen,
    required this.canShowPricing,
    required this.feeSummary,
  });

  final Map<String, dynamic> detail;
  final String? imageUrl;
  final bool isOpen;
  final bool canShowPricing;
  final Map<String, dynamic> feeSummary;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(34),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.14),
            blurRadius: 26,
            offset: const Offset(0, 16),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            children: [
              AppNetworkImage(
                imageUrl: imageUrl,
                height: 250,
                width: double.infinity,
                borderRadius: 34,
                placeholderIcon: Icons.storefront_rounded,
              ),
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(34),
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.transparent,
                        Colors.black.withValues(alpha: 0.50),
                      ],
                    ),
                  ),
                ),
              ),
              Positioned(
                left: 16,
                right: 16,
                top: 16,
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (detail['is_verified'] == true)
                      const StatusBadge(
                        label: 'Verified',
                        icon: Icons.verified_rounded,
                        color: Color(0xFF92A3FD),
                      ),
                    if (detail['is_featured'] == true)
                      const StatusBadge(
                        label: 'Featured',
                        icon: Icons.workspace_premium_rounded,
                        color: Color(0xFFC58BF2),
                      ),
                    StatusBadge(
                      label: isOpen ? 'Open now' : 'Closed',
                      color: isOpen ? AppColors.success : AppColors.warning,
                      icon: Icons.schedule_rounded,
                    ),
                  ],
                ),
              ),
              Positioned(
                left: 18,
                right: 18,
                bottom: 18,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      detail['name']?.toString() ?? 'Gym',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      [
                        detail['address_line']?.toString() ?? '',
                        detail['city']?.toString() ?? '',
                        detail['state']?.toString() ?? '',
                      ].where((item) => item.trim().isNotEmpty).join(', '),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.88),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _DiscoveryCardPill(
                        icon: Icons.currency_rupee_rounded,
                        label: canShowPricing && feeSummary.isNotEmpty
                            ? 'Starts ${_money(feeSummary['min_price'])}'
                            : 'Pricing hidden',
                        color: const Color(0xFF92A3FD),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DiscoveryCardPill(
                        icon: Icons.schedule_rounded,
                        label: _timingSummary(detail['timings']),
                        color: const Color(0xFF40D9B8),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                _DiscoveryCardPill(
                  icon: Icons.event_busy_rounded,
                  label: _weeklyOffSummary(detail['weekly_off']),
                  color: const Color(0xFFC58BF2),
                ),
                if ((detail['description']?.toString() ?? '')
                    .trim()
                    .isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Text(
                    detail['description'].toString(),
                    maxLines: 4,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                      height: 1.45,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DiscoverySaveButton extends StatelessWidget {
  const _DiscoverySaveButton({required this.isSaved, required this.onPressed});

  final bool isSaved;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.92),
      shape: const CircleBorder(),
      child: IconButton(
        onPressed: onPressed,
        icon: Icon(
          isSaved ? Icons.bookmark_rounded : Icons.bookmark_border_rounded,
          color: AppColors.primary,
        ),
      ),
    );
  }
}

class _DiscoveryCardPill extends StatelessWidget {
  const _DiscoveryCardPill({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(icon, size: 17, color: color),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GymFiltersSheet extends StatefulWidget {
  const _GymFiltersSheet({
    required this.initialFilters,
    required this.cities,
    required this.facilities,
  });

  final _GymDiscoveryFilters initialFilters;
  final List<String> cities;
  final List<_FacilityOption> facilities;

  @override
  State<_GymFiltersSheet> createState() => _GymFiltersSheetState();
}

class _GymFiltersSheetState extends State<_GymFiltersSheet> {
  late final TextEditingController _minPriceController;
  late final TextEditingController _maxPriceController;
  late String _city;
  late bool _trialAvailable;
  late bool _verifiedOnly;
  late bool _featuredOnly;
  late bool _openNow;
  late Set<String> _facilities;

  @override
  void initState() {
    super.initState();
    _city = widget.initialFilters.city;
    _trialAvailable = widget.initialFilters.trialAvailable;
    _verifiedOnly = widget.initialFilters.verifiedOnly;
    _featuredOnly = widget.initialFilters.featuredOnly;
    _openNow = widget.initialFilters.openNow;
    _facilities = widget.initialFilters.facilities.toSet();
    _minPriceController = TextEditingController(
      text: widget.initialFilters.minPrice?.toString() ?? '',
    );
    _maxPriceController = TextEditingController(
      text: widget.initialFilters.maxPrice?.toString() ?? '',
    );
  }

  @override
  void dispose() {
    _minPriceController.dispose();
    _maxPriceController.dispose();
    super.dispose();
  }

  void _apply() {
    final minPrice = double.tryParse(_minPriceController.text.trim());
    final maxPrice = double.tryParse(_maxPriceController.text.trim());
    if (minPrice != null && maxPrice != null && minPrice > maxPrice) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Minimum price cannot be greater than maximum price.'),
        ),
      );
      return;
    }

    Navigator.of(context).pop(
      widget.initialFilters.copyWith(
        city: _city,
        facilities: _facilities.toList(),
        trialAvailable: _trialAvailable,
        verifiedOnly: _verifiedOnly,
        featuredOnly: _featuredOnly,
        openNow: _openNow,
        minPrice: minPrice,
        maxPrice: maxPrice,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFFF7F8F8),
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.only(
            left: AppSpacing.xl,
            right: AppSpacing.xl,
            top: AppSpacing.xl,
            bottom: MediaQuery.of(context).viewInsets.bottom + AppSpacing.xl,
          ),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(
                      color: AppColors.textSecondary.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Refine discovery',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  'Refine discovery by city, facilities, trial access, and visible pricing.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                DropdownButtonFormField<String>(
                  initialValue: _city.isEmpty ? null : _city,
                  decoration: const InputDecoration(
                    labelText: 'City',
                    prefixIcon: Icon(Icons.location_city_rounded),
                  ),
                  items: [
                    const DropdownMenuItem<String>(
                      value: '',
                      child: Text('All cities'),
                    ),
                    ...widget.cities.map(
                      (city) => DropdownMenuItem<String>(
                        value: city,
                        child: Text(city),
                      ),
                    ),
                  ],
                  onChanged: (value) => setState(() => _city = value ?? ''),
                ),
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _minPriceController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Min price',
                          prefixIcon: Icon(Icons.currency_rupee_rounded),
                        ),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: TextField(
                        controller: _maxPriceController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Max price',
                          prefixIcon: Icon(Icons.currency_rupee_rounded),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Facilities',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.sm,
                  children: widget.facilities
                      .map(
                        (facility) => FilterChip(
                          selected: _facilities.contains(facility.slug),
                          label: Text(facility.name),
                          onSelected: (selected) {
                            setState(() {
                              if (selected) {
                                _facilities.add(facility.slug);
                              } else {
                                _facilities.remove(facility.slug);
                              }
                            });
                          },
                        ),
                      )
                      .toList(),
                ),
                const SizedBox(height: AppSpacing.lg),
                _FilterSwitchTile(
                  value: _trialAvailable,
                  onChanged: (value) => setState(() => _trialAvailable = value),
                  title: 'Trial available',
                  icon: Icons.flash_on_rounded,
                ),
                _FilterSwitchTile(
                  value: _verifiedOnly,
                  onChanged: (value) => setState(() => _verifiedOnly = value),
                  title: 'Verified gyms only',
                  icon: Icons.verified_rounded,
                ),
                _FilterSwitchTile(
                  value: _featuredOnly,
                  onChanged: (value) => setState(() => _featuredOnly = value),
                  title: 'Featured gyms only',
                  icon: Icons.workspace_premium_rounded,
                ),
                _FilterSwitchTile(
                  value: _openNow,
                  onChanged: (value) => setState(() => _openNow = value),
                  title: 'Open now',
                  icon: Icons.schedule_rounded,
                ),
                const SizedBox(height: AppSpacing.lg),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          Navigator.of(context).pop(
                            widget.initialFilters.copyWith(
                              city: '',
                              facilities: const [],
                              trialAvailable: false,
                              verifiedOnly: false,
                              featuredOnly: false,
                              openNow: false,
                              minPrice: null,
                              maxPrice: null,
                            ),
                          );
                        },
                        child: const Text('Clear'),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: GradientButton(
                        label: 'Apply Filters',
                        icon: Icons.check_rounded,
                        expanded: true,
                        onPressed: _apply,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DetailSection extends StatelessWidget {
  const _DetailSection({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          child,
        ],
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        color: const Color(0xFFF7F8F8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan['name']?.toString() ?? 'Plan',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusBadge(
                label: _money(plan['plan_price']),
                color: AppColors.primaryBright,
                icon: Icons.currency_rupee_rounded,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            '${plan['duration_days'] ?? '--'} days • Joining ${_money(plan['joining_fee'])}',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          ),
          if ((plan['description']?.toString() ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(
              plan['description'].toString(),
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

class _BranchCard extends StatelessWidget {
  const _BranchCard({required this.branch});

  final Map<String, dynamic> branch;

  @override
  Widget build(BuildContext context) {
    final facilities = (branch['facilities'] as List<dynamic>? ?? const [])
        .map(
          (item) => item is Map
              ? Map<String, dynamic>.from(item)
              : const <String, dynamic>{},
        )
        .toList();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        color: const Color(0xFFF7F8F8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            branch['name']?.toString() ?? 'Branch',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            [
              branch['address_line']?.toString() ?? '',
              branch['city']?.toString() ?? '',
              branch['state']?.toString() ?? '',
            ].where((item) => item.trim().isNotEmpty).join(', '),
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              StatusBadge(
                label: _timingSummary(branch['timings']),
                color: AppColors.primaryBright,
                icon: Icons.schedule_rounded,
              ),
              if (facilities.isNotEmpty)
                ...facilities
                    .take(2)
                    .map(
                      (facility) => StatusBadge(
                        label: facility['name']?.toString() ?? 'Facility',
                        color: AppColors.textSecondary,
                      ),
                    ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TrainerCard extends StatelessWidget {
  const _TrainerCard({required this.trainer});

  final Map<String, dynamic> trainer;

  @override
  Widget build(BuildContext context) {
    final specializations =
        (trainer['specializations'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .take(3)
            .toList();
    final branch = trainer['assigned_branch'] is Map
        ? Map<String, dynamic>.from(trainer['assigned_branch'] as Map)
        : const <String, dynamic>{};

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        color: const Color(0xFFF7F8F8),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppNetworkImage(
            imageUrl: trainer['photo_url']?.toString(),
            height: 72,
            width: 72,
            borderRadius: 20,
            placeholderIcon: Icons.person_outline_rounded,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  trainer['name']?.toString() ?? 'Trainer',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                if (branch.isNotEmpty)
                  Text(
                    branch['name']?.toString() ?? '',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                if (specializations.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Wrap(
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.sm,
                    children: specializations
                        .map(
                          (item) => StatusBadge(
                            label: item,
                            color: AppColors.textSecondary,
                          ),
                        )
                        .toList(),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterSwitchTile extends StatelessWidget {
  const _FilterSwitchTile({
    required this.value,
    required this.onChanged,
    required this.title,
    required this.icon,
  });

  final bool value;
  final ValueChanged<bool> onChanged;
  final String title;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: SwitchListTile.adaptive(
        value: value,
        onChanged: onChanged,
        secondary: Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: AppColors.primary.withValues(alpha: 0.11),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Icon(icon, color: AppColors.primary, size: 19),
        ),
        title: Text(
          title,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class _DiscoverySkeleton extends StatelessWidget {
  const _DiscoverySkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const [
          SkeletonLoader(lines: 4),
          SizedBox(height: AppSpacing.lg),
          SkeletonDiscoveryCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonDiscoveryCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonDiscoveryCard(),
        ],
      ),
    );
  }
}

class _FacilityOption {
  const _FacilityOption({required this.slug, required this.name});

  final String slug;
  final String name;

  @override
  bool operator ==(Object other) {
    return other is _FacilityOption && other.slug == slug;
  }

  @override
  int get hashCode => slug.hashCode;
}

const Object _filterUnset = Object();

class _GymDiscoveryFilters {
  const _GymDiscoveryFilters({
    this.search = '',
    this.city = '',
    this.facilities = const [],
    this.trialAvailable = false,
    this.verifiedOnly = false,
    this.featuredOnly = false,
    this.openNow = false,
    this.minPrice,
    this.maxPrice,
  });

  final String search;
  final String city;
  final List<String> facilities;
  final bool trialAvailable;
  final bool verifiedOnly;
  final bool featuredOnly;
  final bool openNow;
  final double? minPrice;
  final double? maxPrice;

  bool get hasActiveFilters =>
      city.isNotEmpty ||
      facilities.isNotEmpty ||
      trialAvailable ||
      verifiedOnly ||
      featuredOnly ||
      openNow ||
      minPrice != null ||
      maxPrice != null;

  int get activeCount {
    var count = 0;
    if (city.isNotEmpty) {
      count++;
    }
    if (facilities.isNotEmpty) {
      count++;
    }
    if (trialAvailable) {
      count++;
    }
    if (verifiedOnly) {
      count++;
    }
    if (featuredOnly) {
      count++;
    }
    if (openNow) {
      count++;
    }
    if (minPrice != null || maxPrice != null) {
      count++;
    }
    return count;
  }

  _GymDiscoveryFilters copyWith({
    String? search,
    String? city,
    List<String>? facilities,
    bool? trialAvailable,
    bool? verifiedOnly,
    bool? featuredOnly,
    bool? openNow,
    Object? minPrice = _filterUnset,
    Object? maxPrice = _filterUnset,
  }) {
    return _GymDiscoveryFilters(
      search: search ?? this.search,
      city: city ?? this.city,
      facilities: facilities ?? this.facilities,
      trialAvailable: trialAvailable ?? this.trialAvailable,
      verifiedOnly: verifiedOnly ?? this.verifiedOnly,
      featuredOnly: featuredOnly ?? this.featuredOnly,
      openNow: openNow ?? this.openNow,
      minPrice: identical(minPrice, _filterUnset)
          ? this.minPrice
          : minPrice as double?,
      maxPrice: identical(maxPrice, _filterUnset)
          ? this.maxPrice
          : maxPrice as double?,
    );
  }

  Map<String, dynamic> toQuery() {
    return {
      if (search.trim().isNotEmpty) 'search': search.trim(),
      if (city.trim().isNotEmpty) 'city': city.trim(),
      if (facilities.isNotEmpty) 'facilities': facilities,
      if (trialAvailable) 'trial_available': true,
      if (verifiedOnly) 'verified_only': true,
      if (featuredOnly) 'featured_only': true,
      if (openNow) 'open_now': true,
      if (minPrice != null) 'min_price': minPrice,
      if (maxPrice != null) 'max_price': maxPrice,
    };
  }
}

String _money(Object? value) {
  if (value == null) {
    return '--';
  }
  final amount = value is num ? value.toDouble() : double.tryParse('$value');
  if (amount == null) {
    return '$value';
  }
  final format = NumberFormat.currency(
    locale: 'en_IN',
    symbol: 'Rs ',
    decimalDigits: amount % 1 == 0 ? 0 : 1,
  );
  return format.format(amount);
}

double? _asDoubleOrNull(Object? value) {
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse(value?.toString() ?? '');
}

String _fullAddress(Map<String, dynamic> detail) {
  return [
    detail['address_line']?.toString() ?? '',
    detail['city']?.toString() ?? '',
    detail['state']?.toString() ?? '',
    detail['country']?.toString() ?? '',
  ].where((item) => item.trim().isNotEmpty).join(', ');
}

String? _gymHeroImage(Map<String, dynamic> gym) {
  final cover = gym['cover_image_url']?.toString() ?? '';
  if (cover.isNotEmpty) {
    return cover;
  }
  final logo = gym['logo_url']?.toString() ?? '';
  return logo.isEmpty ? null : logo;
}

String _timingSummary(dynamic value) {
  if (value is! Map || value.isEmpty) {
    return 'Timings soon';
  }

  const preferredKeys = [
    'all_days',
    'monday_to_saturday',
    'weekdays',
    'monday',
  ];

  for (final key in preferredKeys) {
    final slot = value[key];
    if (slot is Map && slot['open'] != null && slot['close'] != null) {
      return '${slot['open']} - ${slot['close']}';
    }
  }

  return 'See branch timings';
}

String _weeklyOffSummary(dynamic value) {
  if (value is! List || value.isEmpty) {
    return 'No weekly off listed';
  }

  final labels = value
      .map((item) => item.toString())
      .where((item) => item.trim().isNotEmpty);
  return 'Weekly off: ${labels.join(', ')}';
}
