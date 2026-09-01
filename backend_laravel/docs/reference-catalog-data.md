# Reference catalog data

This document records the scope, provenance, and safe update rules for the platform reference seeders. The seeders are additive and idempotent: they update rows identified by their stable city identity, facility slug, specialization slug, or food name, without deleting operator-created rows.

## India city catalog

`CitySeeder` covers the Smart Cities Mission urban-centre catalog and additional high-priority markets already supported by Gym Atlas (including Delhi NCR, Ladakh, and major metros). It is a service-location catalog, not a claim to contain every statutory town or village in India.

Attribution: Ministry of Housing and Urban Affairs / Smart Cities Mission, Government of India, Smart Cities Mission city catalog, Smart Cities Mission Data Portal, accessed 31 August 2026, <https://smartcities.data.gov.in/cities>. Published under the [Government Open Data License - India](https://smartcities.data.gov.in/government-open-data-license-india).

For a future exhaustive town import, prefer the official Census Location Code Directory or a separately reviewed GeoNames import. Do not silently mix villages, neighbourhoods, and cities into the customer-facing city selector.

## Facility taxonomy

`CommonFacilitySeeder` is a Gym Atlas-owned generic taxonomy. It expands the original list into training spaces, member amenities, accessibility, services, and safety facilities. The labels and descriptions are original wording and do not reproduce a third-party catalog.

The coverage was reviewed against:

- ACSM's *Health/Fitness Facility Standards and Guidelines* categories for emergency planning, operating practices, facility design, equipment, and signage: <https://www.acsm.org/wp-content/uploads/2025/01/acsm-health-fitness-facility-standards-guidelines-download-pdf.pdf>
- Health & Fitness Association and Deloitte India's *India Fitness Market Report 2025* for the current Indian commercial-fitness market: <https://www.healthandfitness.org/india-fitness-market-report-2025/>

The legacy `crossfit` slug is retained to avoid breaking existing gym relationships, but its display label is the generic **Cross-training Zone**. This avoids presenting a third-party trademark as a generic facility type.

## Trainer specializations

`TrainerSpecializationSeeder` uses generic practice areas rather than certification product names. The taxonomy covers training outcomes, modalities, populations, coaching delivery, and behaviour support.

Coverage was reviewed against NASM's current specialization pathways, including corrective exercise, performance, older adults, nutrition, behaviour change, and women's fitness: <https://www.nasm.org/resource-center/blog/specializations/how-to-choose-the-right-nasm-specialization-for-your-fitness-career>.

Catalog entries do not certify a trainer. Verification of qualifications, insurance, medical clearance, and local scope-of-practice requirements remains a separate platform process. “Corrective exercise,” “post-rehabilitation fitness,” and nutrition coaching descriptions explicitly remain non-diagnostic and within trainer scope.

## Food catalog

`FoodCatalogSeeder` contains two data-quality classes:

1. **USDA survey reference rows.** Indian foods available in FoodData Central FNDDS are stored per 100 g and include the FDC ID in `notes`. FoodData Central is published under CC0 1.0/public domain, and USDA requests source attribution: <https://fdc.nal.usda.gov/api-guide/>.
2. **Gym Atlas recipe estimates.** Broader Indian meals are clearly labelled as recipe-derived estimates. They are useful for meal planning and search, but must not be presented as laboratory measurements. Oil, ingredients, portion size, region, and brand can materially change their nutrients.

FoodData Central downloadable releases are available at <https://fdc.nal.usda.gov/download-datasets/>. A future bulk import should preserve the FDC ID, release, nutrient basis, and source in dedicated database columns before replacing the curated starter rows.

### Why IFCT 2017 data is not embedded

ICMR-NIN's *Indian Food Composition Tables 2017* is the strongest India-specific analytical reference found: it reports 151 components for 528 foods. However, its copyright page permits personal reproduction with acknowledgement and says electronic storage or reproduction for creating a product requires prior written permission from the National Institute of Nutrition. Therefore, Gym Atlas must not copy the IFCT tables into the product without a written licence.

Reference only: T. Longvah, R. Ananthan, K. Bhaskarachary, and K. Venkaiah, *Indian Food Composition Tables 2017*, National Institute of Nutrition, Indian Council of Medical Research, <https://www.nin.res.in/ebooks/IFCT2017_16122024.pdf>.

No finite catalog can truthfully contain “all Indian foods”: regional dishes, recipes, brands, and serving sizes continually vary. The safe production path is a broad searchable starter catalog, clear data-quality labels, packaged-label verification, and later licensed IFCT or validated recipe imports with provenance.

## Running the seeders

From `backend_laravel`:

```bash
php artisan db:seed --class=CitySeeder --force
php artisan db:seed --class=CommonFacilitySeeder --force
php artisan db:seed --class=TrainerSpecializationSeeder --force
php artisan db:seed --class=FoodCatalogSeeder --force
```

Run a database backup first in production. These commands do not delete custom rows. They do update matching seeded rows so corrected descriptions, servings, and nutrient values reach existing installations.
