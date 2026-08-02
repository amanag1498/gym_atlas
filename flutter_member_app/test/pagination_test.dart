import 'package:flutter_member_app/src/core/pagination.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('reads the Laravel meta.pagination contract', () {
    final page = ApiPagination.fromResponse(const {
      'data': [],
      'meta': {
        'pagination': {'current_page': 2, 'last_page': 4, 'total': 67},
      },
    });

    expect(page.currentPage, 2);
    expect(page.nextPage, 3);
    expect(page.hasMore, isTrue);
    expect(page.total, 67);
  });

  test('merges pages and replaces duplicate ids', () {
    final merged = mergeApiPageItems(
      const [
        {'id': 1, 'name': 'old'},
        {'id': 2, 'name': 'two'},
      ],
      const [
        {'id': 1, 'name': 'new'},
        {'id': 3, 'name': 'three'},
      ],
    );

    expect(merged.map((item) => item['id']), [1, 2, 3]);
    expect(merged.first['name'], 'new');
  });
}
