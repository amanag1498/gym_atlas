class DietPlanFormCodec {
  const DietPlanFormCodec._();

  static String itemsToLines(List<dynamic>? items) {
    return (items ?? const [])
        .map((raw) {
          final item = Map<String, dynamic>.from(raw as Map);
          return [
            item['name'] ?? '',
            item['quantity'] ?? '',
            item['calories'] ?? '',
            item['protein_g'] ?? '',
            item['carbs_g'] ?? '',
            item['fats_g'] ?? '',
            item['notes'] ?? '',
          ].join(' | ');
        })
        .join('\n');
  }

  static List<Map<String, dynamic>> linesToItems(String value) {
    return value
        .split('\n')
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .map((line) {
          final fields = line.split('|').map((field) => field.trim()).toList();
          String? text(int index) =>
              index < fields.length && fields[index].isNotEmpty
              ? fields[index]
              : null;
          num? number(int index) => num.tryParse(text(index) ?? '');
          return <String, dynamic>{
            'name': text(0) ?? 'Food item',
            'quantity': text(1),
            'calories': number(2)?.round(),
            'protein_g': number(3),
            'carbs_g': number(4),
            'fats_g': number(5),
            'notes': text(6),
          };
        })
        .toList();
  }
}
