# Catalog seeder sources and safety

These seeders contain deterministic, reviewed reference content. They never fetch remote data at runtime.

## Exercise and workout catalogs

- US HHS Physical Activity Guidelines: https://odphp.health.gov/our-work/nutrition-physical-activity/physical-activity-guidelines
- ACSM 2026 resistance-training guideline summary: https://acsm.org/resistance-training-guidelines-update-2026/

The plans apply the general principles of regular whole-body resistance training, scalable equipment choices, and goal-specific volume. Exercise instructions are original concise safety cues, not copied exercise-library text. Plans are general fitness references and are not rehabilitation or medical prescriptions.

## Food and diet catalogs

- USDA FoodData Central: https://fdc.nal.usda.gov/
- ICMR-NIN Dietary Guidelines for Indians 2024: https://nin.res.in/dietaryguidelines/pdfjs/locale/DGI_2024.pdf

Food values are approximate reference servings normalized for product use. Variety, brand, cooking method, and portion measurement can change nutrition substantially. Diet templates demonstrate balanced meal patterns and must be personalized for energy needs, allergies, restrictions, pregnancy, medical conditions, and clinician advice.

## Rerun behavior

- Exercises and foods use stable names and do not create duplicates on a second run.
- Existing food records and user-created plans are not overwritten.
- Workout books and diet templates are created only when their catalog name is absent.
- Catalog templates retain food snapshots so later catalog edits do not rewrite assigned diets.
