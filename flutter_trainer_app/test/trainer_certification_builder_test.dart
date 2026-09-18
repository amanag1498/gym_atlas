import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_certification_builder.dart';

void main() {
  testWidgets('renders the shared signup certification editor', (tester) async {
    final nameController = TextEditingController();
    final issuerController = TextEditingController();
    final yearController = TextEditingController();
    addTearDown(nameController.dispose);
    addTearDown(issuerController.dispose);
    addTearDown(yearController.dispose);
    var addCalls = 0;
    var removedIndex = -1;

    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(splashFactory: NoSplash.splashFactory),
        home: Scaffold(
          body: SingleChildScrollView(
            child: TrainerCertificationBuilder(
              certifications: const [
                {'name': 'ACE CPT', 'issuer': 'ACE', 'issued_year': 2025},
              ],
              nameController: nameController,
              issuerController: issuerController,
              yearController: yearController,
              pendingProof: null,
              uploading: false,
              onUpload: () {},
              onAdd: () => addCalls++,
              onRemove: (index) => removedIndex = index,
            ),
          ),
        ),
      ),
    );

    expect(find.text('Certification name'), findsOneWidget);
    expect(find.text('Issuer'), findsOneWidget);
    expect(find.text('Year'), findsOneWidget);
    expect(find.text('Attach proof file'), findsOneWidget);
    expect(find.text('ACE CPT'), findsOneWidget);

    await tester.tap(find.text('Add certification'));
    await tester.tap(find.byTooltip('Remove certification'));

    expect(addCalls, 1);
    expect(removedIndex, 0);
  });
}
