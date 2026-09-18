class DemoLogoTapTracker {
  DemoLogoTapTracker({this.window = const Duration(milliseconds: 1200)});

  final Duration window;
  final List<DateTime> _taps = <DateTime>[];

  bool register([DateTime? at]) {
    final timestamp = at ?? DateTime.now();
    _taps.removeWhere((tap) => timestamp.difference(tap) > window);
    _taps.add(timestamp);

    if (_taps.length < 3) {
      return false;
    }

    clear();
    return true;
  }

  void clear() => _taps.clear();
}
