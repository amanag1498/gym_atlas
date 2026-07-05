import 'package:dio/dio.dart';
import 'package:firebase_auth/firebase_auth.dart' as firebase;
import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../../core/api_client.dart';
import '../../core/fcm_token_service.dart';
import '../../core/models.dart';
import '../../core/secure_storage_service.dart';
import 'auth_service.dart';

class MemberSessionController extends ChangeNotifier {
  MemberSessionController({
    required SecureStorageService storage,
    required MemberApiClient apiClient,
    required AuthService authService,
    MemberFcmTokenService? fcmTokenService,
    GoogleSignIn? googleSignIn,
  }) : _storage = storage,
       _apiClient = apiClient,
       _authService = authService,
       _fcmTokenService = fcmTokenService,
       _googleSignIn =
           googleSignIn ?? GoogleSignIn(scopes: const ['email', 'profile']) {
    _apiClient.updateUnauthorizedHandler(_handleUnauthorized);
  }

  final SecureStorageService _storage;
  final MemberApiClient _apiClient;
  final AuthService _authService;
  final MemberFcmTokenService? _fcmTokenService;
  final GoogleSignIn _googleSignIn;

  MemberUser? user;
  String? token;
  bool busy = false;
  bool initializing = true;
  String? error;

  bool get isAuthenticated => user != null && token != null;
  MemberApiClient get client => _apiClient;

  Future<void> bootstrap() async {
    initializing = true;
    notifyListeners();

    final storedToken = await _storage.readToken();
    final storedUser = await _storage.readUser();

    if (storedToken == null || storedToken.isEmpty) {
      await _clearLocalState(notify: true);
      initializing = false;
      notifyListeners();
      return;
    }

    token = storedToken;
    user = storedUser;
    _apiClient.setBearerToken(storedToken);

    try {
      var me = await _authService.fetchMe();
      me = await _ensureMemberRole(me);
      _ensureEligibleMember(me);
      user = me;
      await _storage.saveSession(token: storedToken, user: me);
      await _registerFcmToken();
    } on DioException catch (exception) {
      if (exception.response?.statusCode == 401) {
        await _clearLocalState(notify: false);
      } else {
        error = _mapAuthError(exception);
      }
    } catch (exception) {
      await _clearLocalState(notify: false);
      error = exception.toString().replaceFirst('Exception: ', '');
    }

    initializing = false;
    notifyListeners();
  }

  Future<void> login() async {
    busy = true;
    error = null;
    notifyListeners();

    try {
      final account = await _googleSignIn.signIn();
      if (account == null) {
        throw Exception('Google sign-in cancelled.');
      }

      final authentication = await account.authentication;
      final credential = firebase.GoogleAuthProvider.credential(
        idToken: authentication.idToken,
        accessToken: authentication.accessToken,
      );
      final firebaseUserCredential = await firebase.FirebaseAuth.instance
          .signInWithCredential(credential);
      final idToken = await firebaseUserCredential.user?.getIdToken(true);
      if (idToken == null || idToken.isEmpty) {
        throw Exception('Firebase ID token missing.');
      }

      final session = await _authService.signInWithFirebase(
        idToken: idToken,
        appType: 'member',
      );

      if (session.token.isEmpty) {
        throw Exception('Authentication token missing from server response.');
      }

      _apiClient.setBearerToken(session.token);

      var me = await _authService.fetchMe();
      me = await _ensureMemberRole(me);
      _ensureEligibleMember(me);

      token = session.token;
      user = me;
      await _storage.saveSession(token: session.token, user: me);
      await _registerFcmToken();
    } on DioException catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = _mapAuthError(exception);
    } catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = exception.toString().replaceFirst('Exception: ', '');
    }

    busy = false;
    notifyListeners();
  }

  Future<void> logout({bool remote = true}) async {
    error = null;

    final existingToken = token;
    if (remote && existingToken != null && existingToken.isNotEmpty) {
      try {
        await _fcmTokenService?.unregisterCurrentToken();
        await _authService.logout();
      } catch (_) {
        // Local logout should still complete even if the remote call fails.
      }
    }

    await _googleSafeSignOut();
    await _clearLocalState(notify: false);
    notifyListeners();
  }

  Future<void> updateCurrentUser(MemberUser updatedUser) async {
    user = updatedUser;
    final currentToken = token;
    if (currentToken != null && currentToken.isNotEmpty) {
      await _storage.saveSession(token: currentToken, user: updatedUser);
    }
    notifyListeners();
  }

  Future<void> _handleUnauthorized() async {
    await logout(remote: false);
  }

  Future<MemberUser> _ensureMemberRole(MemberUser currentUser) async {
    if (currentUser.activeRole == 'member') {
      return currentUser;
    }

    if (currentUser.roles.contains('member')) {
      final updated = await _authService.switchToMemberRole();
      return updated;
    }

    return currentUser;
  }

  Future<void> _registerFcmToken() async {
    if (token == null || token!.isEmpty) {
      return;
    }
    await _fcmTokenService?.registerToken(appRole: 'member');
  }

  void _ensureEligibleMember(MemberUser currentUser) {
    if (!currentUser.isActive) {
      throw Exception('Your account is inactive. Please contact support.');
    }

    if (currentUser.activeRole != 'member' &&
        !currentUser.roles.contains('member')) {
      throw Exception('This account is not allowed in the Member App.');
    }
  }

  Future<void> _clearLocalState({required bool notify}) async {
    await _storage.clear();
    _apiClient.clearBearerToken();
    token = null;
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
    final response = exception.response;
    final data = response?.data;

    if (response?.statusCode == 401) {
      return 'Your session is invalid or expired. Please sign in again.';
    }

    if (data is Map<String, dynamic>) {
      final message = data['message']?.toString();
      if (message != null && message.isNotEmpty) {
        return message;
      }
    } else if (data is Map) {
      final message = data['message']?.toString();
      if (message != null && message.isNotEmpty) {
        return message;
      }
    }

    switch (exception.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'Network error. Please check your connection and try again.';
      default:
        return 'Sign-in failed. Please try again.';
    }
  }
}
