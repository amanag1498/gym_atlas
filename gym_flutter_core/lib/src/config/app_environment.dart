class AppEnvironment {
  const AppEnvironment({
    required this.appName,
    required this.apiBaseUrl,
    required this.socketBaseUrl,
    required this.googleWebClientId,
  });

  final String appName;
  final String apiBaseUrl;
  final String socketBaseUrl;
  final String googleWebClientId;

  factory AppEnvironment.fromDefines({required String appName}) {
    return AppEnvironment(
      appName: appName,
      apiBaseUrl: const String.fromEnvironment(
        'API_BASE_URL',
        defaultValue: 'http://127.0.0.1:8000/api',
      ),
      socketBaseUrl: const String.fromEnvironment(
        'SOCKET_BASE_URL',
        defaultValue: 'http://127.0.0.1:3000',
      ),
      googleWebClientId: const String.fromEnvironment(
        'GOOGLE_WEB_CLIENT_ID',
        defaultValue: '',
      ),
    );
  }
}
