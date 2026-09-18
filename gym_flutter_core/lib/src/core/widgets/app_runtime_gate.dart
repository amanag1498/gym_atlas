import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'atlas_brand_lockup.dart';
import 'branded_startup_loader.dart';

class AppRuntimeController extends ChangeNotifier with WidgetsBindingObserver {
  AppRuntimeController({required this.dio, required this.appType});

  final Dio dio;
  final String appType;

  AppRuntimeConfig? config;
  bool loading = true;
  String appVersion = '1.0.0';
  int buildNumber = 1;
  Future<void>? _refreshInFlight;

  static String get clientPlatform {
    if (kIsWeb) return 'web';
    return defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
  }

  Future<void> initialize() async {
    WidgetsBinding.instance.addObserver(this);
    try {
      final package = await PackageInfo.fromPlatform();
      appVersion = package.version.trim().isEmpty
          ? appVersion
          : package.version.trim();
      buildNumber = int.tryParse(package.buildNumber.trim()) ?? buildNumber;
    } catch (_) {
      // Compile-time defaults keep development and widget tests operational.
    }

    dio.options.headers.addAll(<String, Object>{
      'X-Atlas-App': appType,
      'X-Client-Platform': clientPlatform,
      'X-App-Version': appVersion,
      'X-App-Version-Code': buildNumber.toString(),
    });
    dio.interceptors.add(
      InterceptorsWrapper(
        onError: (error, handler) {
          if (error.response?.statusCode == 426 ||
              error.response?.statusCode == 503) {
            unawaited(refresh());
          }
          handler.next(error);
        },
      ),
    );
    await refresh();
  }

  Future<void> refresh() {
    final active = _refreshInFlight;
    if (active != null) return active;
    final request = _performRefresh();
    _refreshInFlight = request;
    return request.whenComplete(() {
      if (identical(_refreshInFlight, request)) _refreshInFlight = null;
    });
  }

  Future<void> _performRefresh() async {
    loading = true;
    notifyListeners();
    try {
      final response = await dio.get<dynamic>(
        '/public/app-config',
        queryParameters: <String, Object>{
          'app_type': appType,
          'platform': clientPlatform,
          'build_number': buildNumber,
          'version': appVersion,
        },
      );
      final body = Map<String, dynamic>.from(response.data as Map);
      config = AppRuntimeConfig.fromJson(
        Map<String, dynamic>.from(body['data'] as Map? ?? const {}),
      );
    } catch (_) {
      // Fail open on connectivity errors. The API still enforces active gates.
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(refresh());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
}

class AppRuntimeConfig {
  const AppRuntimeConfig({
    required this.maintenanceEnabled,
    required this.maintenanceTitle,
    required this.maintenanceMessage,
    required this.updateRequired,
    required this.updateTitle,
    required this.updateMessage,
    required this.minimumVersion,
    required this.minimumBuild,
    required this.storeUrl,
  });

  final bool maintenanceEnabled;
  final String maintenanceTitle;
  final String maintenanceMessage;
  final bool updateRequired;
  final String updateTitle;
  final String updateMessage;
  final String minimumVersion;
  final int minimumBuild;
  final String? storeUrl;

  factory AppRuntimeConfig.fromJson(Map<String, dynamic> json) {
    return AppRuntimeConfig(
      maintenanceEnabled: _asBool(json['maintenance_mode_enabled']),
      maintenanceTitle: _asText(
        json['maintenance_title'],
        'A quick tune-up is underway',
      ),
      maintenanceMessage: _asText(
        json['maintenance_message'],
        'Atlas is temporarily unavailable. Please try again shortly.',
      ),
      updateRequired: _asBool(json['update_required']),
      updateTitle: _asText(json['update_title'], 'Update Atlas to continue'),
      updateMessage: _asText(
        json['update_message'],
        'A newer version is required to continue.',
      ),
      minimumVersion: _asText(json['minimum_version'], 'latest'),
      minimumBuild:
          int.tryParse(json['minimum_build_number']?.toString() ?? '') ?? 1,
      storeUrl: _nullableText(json['store_url']),
    );
  }

  static bool _asBool(dynamic value) =>
      value == true || value == 1 || value?.toString().toLowerCase() == 'true';

  static String _asText(dynamic value, String fallback) =>
      _nullableText(value) ?? fallback;

  static String? _nullableText(dynamic value) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? null : text;
  }
}

class AppRuntimeGate extends StatelessWidget {
  const AppRuntimeGate({
    super.key,
    required this.controller,
    required this.audience,
    required this.child,
  });

  final AppRuntimeController controller;
  final String audience;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final config = controller.config;
        if (controller.loading && config == null) {
          return const BrandedStartupLoader();
        }
        if (config?.updateRequired == true) {
          return _BlockingScreen(
            audience: audience,
            icon: Icons.system_update_alt_rounded,
            eyebrow: 'UPDATE REQUIRED',
            title: config!.updateTitle,
            message: config.updateMessage,
            detail:
                'Installed ${controller.appVersion} (${controller.buildNumber})  ·  Required ${config.minimumVersion} (${config.minimumBuild})',
            actionLabel: config.storeUrl == null ? 'Check again' : 'Update now',
            onAction: config.storeUrl == null
                ? controller.refresh
                : () => _openStore(config.storeUrl!),
            secondaryLabel: config.storeUrl == null ? null : 'I have updated',
            onSecondary: config.storeUrl == null ? null : controller.refresh,
            busy: controller.loading,
          );
        }
        if (config?.maintenanceEnabled == true) {
          return _BlockingScreen(
            audience: audience,
            icon: Icons.construction_rounded,
            eyebrow: 'MAINTENANCE',
            title: config!.maintenanceTitle,
            message: config.maintenanceMessage,
            detail: 'Your account and progress are safe.',
            actionLabel: 'Check again',
            onAction: controller.refresh,
            busy: controller.loading,
          );
        }
        return child;
      },
    );
  }

  Future<void> _openStore(String value) async {
    final uri = Uri.tryParse(value);
    if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

class _BlockingScreen extends StatelessWidget {
  const _BlockingScreen({
    required this.audience,
    required this.icon,
    required this.eyebrow,
    required this.title,
    required this.message,
    required this.detail,
    required this.actionLabel,
    required this.onAction,
    required this.busy,
    this.secondaryLabel,
    this.onSecondary,
  });

  final String audience;
  final IconData icon;
  final String eyebrow;
  final String title;
  final String message;
  final String detail;
  final String actionLabel;
  final Future<void> Function() onAction;
  final bool busy;
  final String? secondaryLabel;
  final Future<void> Function()? onSecondary;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF4F7FF), Colors.white],
          ),
        ),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) => SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  minHeight: constraints.maxHeight - 56,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 440),
                    child: Semantics(
                      container: true,
                      liveRegion: true,
                      label: '$title. $message',
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          Align(
                            alignment: Alignment.centerLeft,
                            child: AtlasBrandLockup(
                              audience: audience,
                              markSize: 54,
                            ),
                          ),
                          const SizedBox(height: 48),
                          Container(
                            width: 64,
                            height: 64,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: const Color(
                                0xFF465FFF,
                              ).withValues(alpha: 0.10),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Icon(
                              icon,
                              size: 30,
                              color: const Color(0xFF3641F5),
                            ),
                          ),
                          const SizedBox(height: 24),
                          Text(
                            eyebrow,
                            style: Theme.of(context).textTheme.labelMedium
                                ?.copyWith(
                                  color: const Color(0xFF465FFF),
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1.4,
                                ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            title,
                            style: Theme.of(context).textTheme.headlineMedium
                                ?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  height: 1.12,
                                ),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            message,
                            style: Theme.of(context).textTheme.bodyLarge
                                ?.copyWith(
                                  color: const Color(0xFF475467),
                                  height: 1.55,
                                ),
                          ),
                          const SizedBox(height: 20),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 13,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF2F4F7),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Text(
                              detail,
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: const Color(0xFF667085),
                                    height: 1.4,
                                  ),
                            ),
                          ),
                          const SizedBox(height: 28),
                          SizedBox(
                            height: 52,
                            child: FilledButton(
                              onPressed: busy
                                  ? null
                                  : () => unawaited(onAction()),
                              child: busy
                                  ? const SizedBox.square(
                                      dimension: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : Text(actionLabel),
                            ),
                          ),
                          if (secondaryLabel != null) ...<Widget>[
                            const SizedBox(height: 8),
                            SizedBox(
                              height: 48,
                              child: TextButton(
                                onPressed: busy || onSecondary == null
                                    ? null
                                    : () => unawaited(onSecondary!()),
                                child: Text(secondaryLabel!),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
