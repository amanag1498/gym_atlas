import 'package:flutter/foundation.dart';

import '../../config/app_environment.dart';
import '../models/app_models.dart';
import '../network/api_exception.dart';
import '../repositories/auth_repository.dart';
import '../services/google_auth_service.dart';
import '../services/realtime_service.dart';

class AuthController extends ChangeNotifier {
  AuthController({
    required this.environment,
    required this.authRepository,
    required this.googleAuthService,
    required this.realtimeService,
    required this.allowedRoles,
  });

  final AppEnvironment environment;
  final AuthRepository authRepository;
  final GoogleAuthService googleAuthService;
  final RealtimeService realtimeService;
  final Set<String> allowedRoles;

  SessionUser? _user;
  bool _initializing = true;
  bool _loggingIn = false;
  String? _error;

  SessionUser? get user => _user;
  bool get initializing => _initializing;
  bool get loggingIn => _loggingIn;
  String? get error => _error;
  bool get isAuthenticated => _user != null;

  bool get needsAllowedRoleSelection {
    final user = _user;
    if (user == null) return false;
    if (allowedRoles.contains(user.activeRole)) return false;
    return user.roles.any(allowedRoles.contains);
  }

  List<String> get availableAllowedRoles {
    final roles = _user?.roles ?? const <String>[];
    return roles.where(allowedRoles.contains).toList(growable: false);
  }

  Future<void> bootstrap() async {
    try {
      final token = await authRepository.readStoredToken();
      if (token == null || token.isEmpty) {
        _user = null;
        return;
      }
      final user = await authRepository.me();
      _setUser(user, reconnectSocket: true);
    } on ApiException catch (error) {
      _error = error.message;
      _user = null;
      await authRepository.logout();
    } finally {
      _initializing = false;
      notifyListeners();
    }
  }

  Future<void> login() async {
    _loggingIn = true;
    _error = null;
    notifyListeners();

    try {
      final idToken = await googleAuthService.fetchIdToken();
      final session = await authRepository.loginWithGoogle(
        idToken: idToken,
        deviceName: environment.appName,
      );
      _setUser(session.user, reconnectSocket: true);
    } catch (error) {
      _error = error is ApiException ? error.message : error.toString();
    } finally {
      _loggingIn = false;
      _initializing = false;
      notifyListeners();
    }
  }

  Future<void> selectRole(String role) async {
    _error = null;
    notifyListeners();
    try {
      final user = await authRepository.switchActiveRole(role);
      _setUser(user, reconnectSocket: true);
      notifyListeners();
    } on ApiException catch (error) {
      _error = error.message;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    await googleAuthService.signOut();
    await authRepository.logout();
    realtimeService.disconnect();
    _user = null;
    _error = null;
    notifyListeners();
  }

  void handleUnauthorized() {
    _user = null;
    _error = 'Your session expired. Please sign in again.';
    notifyListeners();
  }

  void _setUser(SessionUser user, {required bool reconnectSocket}) {
    _user = user;
    if (reconnectSocket && (user.activeRole == 'member' || user.activeRole == 'trainer')) {
      final tokenFuture = authRepository.readStoredToken();
      tokenFuture.then((token) {
        if (token != null && token.isNotEmpty) {
          realtimeService.connect(token: token, currentUserId: user.id);
        }
      });
    }
  }
}

class AsyncValueController<T> extends ChangeNotifier {
  AsyncValueController(this._loader);

  final Future<T> Function() _loader;

  bool loading = false;
  String? error;
  T? value;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      value = await _loader();
    } catch (exception) {
      error = exception is ApiException ? exception.message : exception.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}

class PaginatedListController<T> extends ChangeNotifier {
  PaginatedListController(this._loader);

  final Future<PaginatedResponse<T>> Function(int page) _loader;

  bool loading = false;
  bool refreshing = false;
  String? error;
  List<T> items = <T>[];
  PaginationMeta? pagination;

  Future<void> loadInitial() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _loader(1);
      items = result.items;
      pagination = result.pagination;
    } catch (exception) {
      error = exception is ApiException ? exception.message : exception.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() async {
    refreshing = true;
    notifyListeners();
    try {
      final result = await _loader(1);
      items = result.items;
      pagination = result.pagination;
      error = null;
    } catch (exception) {
      error = exception is ApiException ? exception.message : exception.toString();
    } finally {
      refreshing = false;
      notifyListeners();
    }
  }

  Future<void> loadMore() async {
    if (loading || !(pagination?.hasMore ?? false)) return;
    loading = true;
    notifyListeners();
    try {
      final nextPage = (pagination?.currentPage ?? 1) + 1;
      final result = await _loader(nextPage);
      items = <T>[...items, ...result.items];
      pagination = result.pagination;
    } catch (exception) {
      error = exception is ApiException ? exception.message : exception.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
