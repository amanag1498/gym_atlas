class MemberConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://gymatlas.in/api',
  );

  static const socketBaseUrl = String.fromEnvironment(
    'SOCKET_BASE_URL',
    defaultValue: 'https://socket.gymatlas.in',
  );

  static const appBuildNumber = int.fromEnvironment(
    'APP_BUILD_NUMBER',
    defaultValue: 16,
  );

  static const appVersion = String.fromEnvironment(
    'APP_VERSION',
    defaultValue: '1.0.6',
  );
}
