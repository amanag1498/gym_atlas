import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';

class FilterChipBar<T> extends StatelessWidget {
  const FilterChipBar({
    super.key,
    required this.options,
    required this.selectedValue,
    required this.labelBuilder,
    required this.onSelected,
    this.allowClear = false,
    this.clearLabel = 'All',
  });

  final List<T> options;
  final T? selectedValue;
  final String Function(T option) labelBuilder;
  final ValueChanged<T?> onSelected;
  final bool allowClear;
  final String clearLabel;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        children: [
          if (allowClear)
            Padding(
              padding: const EdgeInsets.only(right: AppSpacing.sm),
              child: FilterChip(
                selected: selectedValue == null,
                label: Text(clearLabel),
                onSelected: (_) => onSelected(null),
              ),
            ),
          ...options.map(
            (option) => Padding(
              padding: const EdgeInsets.only(right: AppSpacing.sm),
              child: FilterChip(
                selected: selectedValue == option,
                label: Text(labelBuilder(option)),
                onSelected: (_) => onSelected(option),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
