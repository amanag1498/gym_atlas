class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.errors,
    this.isConnectionError = false,
  });

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;
  final bool isConnectionError;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => 'ApiException($statusCode): $message';
}
