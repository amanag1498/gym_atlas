class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.errors,
  });

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => 'ApiException($statusCode): $message';
}
