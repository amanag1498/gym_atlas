# Atlas Google Play release assets

This folder contains the checked-in, non-secret assets and listing copy for the
Atlas Member and Atlas Trainer releases.

## Ready assets

- `branding/atlas-master-icon.png`: generated high-resolution brand source.
- `branding/atlas-play-store-icon.png`: 512 x 512 Play Store icon.
- `member/feature-graphic-1024x500.png`: member feature graphic.
- `trainer/feature-graphic-1024x500.png`: trainer feature graphic.
- `member/listing.md` and `trainer/listing.md`: store names, descriptions,
  keywords, URLs, and reviewer notes.
- `member/play-console-fields.md` and `trainer/play-console-fields.md`: exact
  ready-to-paste Play Console values.
- `member/screenshots/` and `trainer/screenshots/`: four generated promotional
  functionality visuals per app at 1080 x 1920.
- `member/screenshots-real-ui/` and `trainer/screenshots-real-ui/`: archived
  production-widget captures, retained as a fallback and not selected for the
  requested listing.
- `app-content-answers.md`: target audience, app access, rating, health and
  declaration answers.
- `final-upload-runbook.md`: ordered submission, testing and rollout steps.
- `build-artifacts.md`: final AAB locations, checksums and upload-certificate
  fingerprints.
- `release-checklist.md`: final Play Console and build checklist.
- `data-safety.md`: declaration worksheet based on the current app behavior.

The launcher icon has also been installed into every Android density bucket in
both Flutter apps, including adaptive icon resources.

## Image-generation provenance

The master icon and both feature graphics were created with the built-in image
generation tool. The final selected prompt direction was a premium geometric
Atlas `A` with an upward path, using midnight navy, electric indigo, and cyan.
The source logo was then resized locally for deterministic Play and Android
dimensions.

## Screenshot provenance

The selected eight phone visuals were generated with the built-in image
generation workflow from the Atlas logo and verified app functionality. They
use illustrative app-style cards without device frames. The earlier real
production-widget captures are archived separately rather than deleted.
