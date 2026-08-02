import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/pagination.dart';

void main() {
  test('reads the Laravel meta.pagination contract', () {
    final page = ApiPagination.fromResponse(const {
      'data': [],
      'meta': {
        'pagination': {'current_page': 3, 'last_page': 3, 'total': 41},
      },
    });

    expect(page.currentPage, 3);
    expect(page.hasMore, isFalse);
    expect(page.total, 41);
  });

  test('deduplicates appended records by id', () {
    final merged = mergeApiPageItems(
      const [
        {'id': 8, 'status': 'old'},
      ],
      const [
        {'id': 8, 'status': 'new'},
        {'id': 9, 'status': 'new'},
      ],
    );

    expect(merged, [
      {'id': 8, 'status': 'new'},
      {'id': 9, 'status': 'new'},
    ]);
  });
}
