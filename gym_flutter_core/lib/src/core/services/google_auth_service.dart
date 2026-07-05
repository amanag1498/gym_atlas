import 'package:google_sign_in/google_sign_in.dart';

class GoogleAuthService {
  GoogleAuthService({required String clientId})
      : _googleSignIn = GoogleSignIn(
          serverClientId: clientId.isEmpty ? null : clientId,
          scopes: const ['email', 'profile'],
        );

  final GoogleSignIn _googleSignIn;

  Future<String> fetchIdToken() async {
    final user = await _googleSignIn.signIn();
    final authentication = await user?.authentication;
    final token = authentication?.idToken;

    if (token == null || token.isEmpty) {
      throw Exception('Google Sign-In failed to return an ID token.');
    }

    return token;
  }

  Future<void> signOut() => _googleSignIn.signOut();
}
