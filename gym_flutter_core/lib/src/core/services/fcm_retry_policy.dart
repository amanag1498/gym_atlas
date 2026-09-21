class FcmRetryPolicy {
  FcmRetryPolicy({this.maxAttempts = 6});

  final int maxAttempts;
  int _attempts = 0;

  int get attempts => _attempts;
  bool get exhausted => _attempts >= maxAttempts;

  Duration? nextDelay() {
    if (exhausted) return null;
    final delay = Duration(seconds: 1 << _attempts);
    _attempts++;
    return delay;
  }

  void reset() {
    _attempts = 0;
  }
}
