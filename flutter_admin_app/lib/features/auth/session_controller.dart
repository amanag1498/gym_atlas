import 'package:dio/dio.dart';
import 'package:firebase_auth/firebase_auth.dart' as firebase;
import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../../core/user_facing_error.dart';
import '../../core/models/session_models.dart';
import '../../core/network/api_client.dart';
import '../../core/notifications/admin_fcm_token_service.dart';
import '../../core/storage/token_storage.dart';
import 'auth_repository.dart';

class SessionController extends ChangeNotifier {
  SessionController({
    TokenStorage? tokenStorage,
    ApiClient? apiClient,
    AdminFcmTokenService? fcmTokenService,
    GoogleSignIn? googleSignIn,
  }) : _tokenStorage = tokenStorage ?? const TokenStorage(),
       _apiClient =
           apiClient ?? ApiClient(token: null, onUnauthorized: () async {}),
       _googleSignIn =
           googleSignIn ?? GoogleSignIn(scopes: const ['email', 'profile']) {
    _apiClient.updateUnauthorizedHandler(_handleUnauthorized);
    _fcmTokenService = fcmTokenService ?? AdminFcmTokenService(_apiClient);
  }

  final TokenStorage _tokenStorage;
  final ApiClient _apiClient;
  final GoogleSignIn _googleSignIn;
  late final AdminFcmTokenService _fcmTokenService;

  AppUser? user;
  String? _token;
  bool bootstrapping = false;
  bool loggingIn = false;
  String? error;

  static const List<String> _allowedRoles = [
    'gym_owner',
    'branch_manager',
    'gym_staff',
  ];

  bool get isAuthenticated => _token != null && user != null;
  bool get hasMultipleRoles => (user?.adminRoles.length ?? 0) > 1;
  String? get token => _token;
  ApiClient get authenticatedClient => _apiClient;

  Future<void> bootstrap() async {
    bootstrapping = true;
    notifyListeners();

    final storedToken = await _tokenStorage.readToken();
    final storedUser = await _tokenStorage.readUser();

    if (storedToken == null || storedToken.isEmpty) {
      await _clearLocalState(notify: false);
      bootstrapping = false;
      notifyListeners();
      return;
    }

    _token = storedToken;
    user = storedUser;
    _apiClient.setBearerToken(storedToken);

    try {
      final repository = AuthRepository(_apiClient);
      var me = await repository.fetchMe();
      me = await _ensureAdminRole(repository, me);
      _ensureEligibleAdmin(me);
      user = me;
      await _tokenStorage.writeSession(token: storedToken, user: me);
      await _fcmTokenService.registerToken();
    } on DioException catch (exception) {
      if (exception.response?.statusCode == 401) {
        await _clearLocalState(notify: false);
      } else {
        error = _mapAuthError(exception);
      }
    } catch (exception) {
      await _clearLocalState(notify: false);
      error = userFacingError(exception).replaceFirst('Exception: ', '');
    }

    bootstrapping = false;
    notifyListeners();
  }

  Future<void> login() async {
    loggingIn = true;
    error = null;
    notifyListeners();

    try {
      final account = await _googleSignIn.signIn();
      if (account == null) {
        throw Exception('Google sign-in was cancelled.');
      }

      final authentication = await account.authentication;
      final credential = firebase.GoogleAuthProvider.credential(
        idToken: authentication.idToken,
        accessToken: authentication.accessToken,
      );
      final firebaseUserCredential = await firebase.FirebaseAuth.instance
          .signInWithCredential(credential);
      await _completeFirebaseLogin(firebaseUserCredential);
    } on DioException catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = _mapAuthError(exception);
    } catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = userFacingError(exception).replaceFirst('Exception: ', '');
    }

    loggingIn = false;
    notifyListeners();
  }

  Future<void> loginWithApple() async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.iOS) {
      error = 'Sign in with Apple is available on iPhone and iPad.';
      notifyListeners();
      return;
    }

    loggingIn = true;
    error = null;
    notifyListeners();

    try {
      final provider = firebase.AppleAuthProvider()
        ..addScope('email')
        ..addScope('name');
      final credential = await firebase.FirebaseAuth.instance
          .signInWithProvider(provider);
      await _completeFirebaseLogin(credential);
    } on DioException catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = _mapAuthError(exception);
    } on firebase.FirebaseAuthException catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = _mapAppleAuthError(exception);
    } catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = userFacingError(exception).replaceFirst('Exception: ', '');
    }

    loggingIn = false;
    notifyListeners();
  }

  Future<void> _completeFirebaseLogin(
    firebase.UserCredential firebaseUserCredential,
  ) async {
    final idToken = await firebaseUserCredential.user?.getIdToken(true);
    if (idToken == null || idToken.isEmpty) {
      throw Exception('Firebase ID token was not returned.');
    }

    final repository = AuthRepository(_apiClient);
    final session = await repository.signInWithFirebase(idToken: idToken);
    if (session.token.isEmpty) {
      throw Exception('Authentication token missing from server response.');
    }
    if (session.user.adminRoles.isEmpty) {
      _apiClient.setBearerToken(session.token);
      await repository.logout();
      throw Exception(
        'This app is only for gym owners, branch managers, and gym staff.',
      );
    }

    _apiClient.setBearerToken(session.token);
    var me = await repository.fetchMe();
    me = await _ensureAdminRole(repository, me);
    _ensureEligibleAdmin(me);
    _token = session.token;
    user = me;
    await _tokenStorage.writeSession(token: session.token, user: me);
    await _fcmTokenService.registerToken();
  }

  Future<bool> fetchDemoLoginEnabled() async {
    try {
      final response = await _apiClient.get(
        '/public/app-config',
        queryParameters: {
          'app_type': 'admin',
          'platform': defaultTargetPlatform == TargetPlatform.iOS
              ? 'ios'
              : 'android',
        },
      );
      return (response['data'] as Map?)?['demo_admin_login_enabled'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<void> loginWithDemo({
    required String email,
    required String accessCode,
  }) async {
    loggingIn = true;
    error = null;
    notifyListeners();
    try {
      final repository = AuthRepository(_apiClient);
      final session = await repository.signInWithDemo(
        email: email,
        accessCode: accessCode,
      );
      if (session.token.isEmpty ||
          session.user.activeRole != 'gym_owner' ||
          session.user.hasRole('platform_admin')) {
        throw Exception('Admin reviewer account is unavailable.');
      }
      _token = session.token;
      _apiClient.setBearerToken(session.token);
      user = session.user;
      await _tokenStorage.writeSession(
        token: session.token,
        user: session.user,
      );
      await _fcmTokenService.registerToken();
    } on DioException catch (exception) {
      await _clearLocalState(notify: false);
      error = _mapAuthError(exception);
    } catch (exception) {
      await _clearLocalState(notify: false);
      error = userFacingError(exception).replaceFirst('Exception: ', '');
    }
    loggingIn = false;
    notifyListeners();
  }

  Future<void> switchRole(String role) async {
    if (_token == null) {
      return;
    }
    error = null;
    notifyListeners();

    try {
      if (!_allowedRoles.contains(role)) {
        throw Exception('This role is not supported in the Admin App.');
      }
      final repository = AuthRepository(_apiClient);
      var me = await repository.switchRole(role);
      me = await _ensureAdminRole(repository, me);
      _ensureEligibleAdmin(me);
      user = me;
      await _tokenStorage.writeSession(token: _token!, user: me);
      await _fcmTokenService.registerToken();
    } on DioException catch (exception) {
      error = _mapAuthError(exception);
    } catch (exception) {
      error = userFacingError(exception).replaceFirst('Exception: ', '');
    }

    notifyListeners();
  }

  Future<void> logout({bool remote = true}) async {
    error = null;

    if (remote && _token != null && _token!.isNotEmpty) {
      try {
        await _fcmTokenService.unregisterCurrentToken();
        await AuthRepository(_apiClient).logout();
      } catch (_) {
        // Preserve logout flow on API failure.
      }
    }

    await _googleSafeSignOut();
    await _clearLocalState(notify: false);
    notifyListeners();
  }

  Future<void> _handleUnauthorized() async {
    await logout(remote: false);
  }

  Future<AppUser> _ensureAdminRole(
    AuthRepository repository,
    AppUser currentUser,
  ) async {
    if (_allowedRoles.contains(currentUser.activeRole)) {
      return currentUser;
    }

    final fallbackRole = currentUser.adminRoles.firstOrNull;
    if (fallbackRole != null) {
      return repository.switchRole(fallbackRole);
    }

    return currentUser;
  }

  void _ensureEligibleAdmin(AppUser currentUser) {
    if (!currentUser.isActive) {
      throw Exception(
        'Your admin account is inactive. Please contact support.',
      );
    }

    if (!_allowedRoles.contains(currentUser.activeRole) &&
        currentUser.adminRoles.isEmpty) {
      throw Exception('This Google account is not allowed in the Admin App.');
    }
  }

  Future<void> _clearLocalState({required bool notify}) async {
    await _tokenStorage.clear();
    _apiClient.clearBearerToken();
    _token = null;
    user = null;
    if (notify) {
      notifyListeners();
    }
  }

  Future<void> _googleSafeSignOut() async {
    try {
      await _googleSignIn.signOut();
    } catch (_) {
      // Ignore Google SDK sign-out failures during local cleanup.
    }
    try {
      await firebase.FirebaseAuth.instance.signOut();
    } catch (_) {
      // Ignore Firebase sign-out failures during local cleanup.
    }
  }

  String _mapAuthError(DioException exception) {
    return userFacingError(exception);
  }

  String _mapAppleAuthError(firebase.FirebaseAuthException exception) {
    switch (exception.code) {
      case 'canceled':
      case 'web-context-canceled':
      case 'web-context-cancelled':
        return 'Apple sign-in cancelled.';
      case 'operation-not-allowed':
        return 'Sign in with Apple is not enabled yet.';
      case 'account-exists-with-different-credential':
        return 'An account already exists for this email. Sign in with Google first.';
      case 'network-request-failed':
        return 'Network error. Please check your connection and try again.';
      default:
        return 'Apple sign-in failed. Please try again.';
    }
  }
}

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
