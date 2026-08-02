class ApiPagination {
  const ApiPagination({
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  const ApiPagination.singlePage({this.total = 0})
    : currentPage = 1,
      lastPage = 1;

  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
  int get nextPage => currentPage + 1;

  factory ApiPagination.fromResponse(Map<String, dynamic> response) {
    final meta = response['meta'];
    final pagination = meta is Map ? meta['pagination'] : null;
    if (pagination is! Map) {
      final data = response['data'];
      return ApiPagination.singlePage(total: data is List ? data.length : 0);
    }

    int number(Object? value, int fallback) => value is num
        ? value.toInt()
        : int.tryParse(value?.toString() ?? '') ?? fallback;

    final currentPage = number(pagination['current_page'], 1).clamp(1, 1 << 31);
    final lastPage = number(
      pagination['last_page'],
      currentPage,
    ).clamp(currentPage, 1 << 31);
    return ApiPagination(
      currentPage: currentPage,
      lastPage: lastPage,
      total: number(pagination['total'], 0).clamp(0, 1 << 31),
    );
  }
}

List<Map<String, dynamic>> apiPageItems(Map<String, dynamic> response) =>
    (response['data'] as List<dynamic>? ?? const <dynamic>[])
        .whereType<Map>()
        .map(Map<String, dynamic>.from)
        .toList();

List<Map<String, dynamic>> mergeApiPageItems(
  Iterable<Map<String, dynamic>> existing,
  Iterable<Map<String, dynamic>> incoming,
) {
  final merged = <Map<String, dynamic>>[];
  final keyedIndexes = <String, int>{};

  for (final item in <Map<String, dynamic>>[...existing, ...incoming]) {
    final id = item['id'];
    if (id == null) {
      merged.add(item);
      continue;
    }
    final key = '${id.runtimeType}:$id';
    final index = keyedIndexes[key];
    if (index == null) {
      keyedIndexes[key] = merged.length;
      merged.add(item);
    } else {
      merged[index] = item;
    }
  }
  return merged;
}
