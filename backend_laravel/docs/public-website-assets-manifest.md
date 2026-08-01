# Public Website Asset Manifest

Generated on: 2026-08-01

This manifest records the first optimized website derivatives created from the existing Gym Atlas Play Store and App Store source artwork. Source files remain unchanged.

## Brand and social assets

| Public file | Source | Dimensions | Use | Status |
|---|---|---:|---|---|
| `public/images/public-site/brand/atlas-mark-64.png` | `../play_store_assets/branding/atlas-master-icon.png` | 64 x 64 | Header, favicon, structured data | Approved derivative |
| `public/images/public-site/brand/apple-touch-icon.png` | `../play_store_assets/branding/atlas-master-icon.png` | 180 x 180 | Apple touch icon | Approved derivative |
| `public/images/public-site/brand/atlas-mark-512.png` | `../play_store_assets/branding/atlas-master-icon.png` | 512 x 512 | High-resolution icon/PWA source | Approved derivative |
| `public/images/public-site/social/atlas-platform-social.jpg` | `../play_store_assets/member/feature-graphic-source.png` | 1200 x 630 | Default Open Graph/Twitter image | Approved initial derivative |

## Member App assets

| Public file | Source | Dimensions | Use | Status |
|---|---|---:|---|---|
| `public/images/product/member/dashboard-720.webp` | `../play_store_assets/member/screenshots-real-ui/01-dashboard.png` | 720 x 1280 | Homepage, Product, Member App | Verified real UI |
| `public/images/product/member/activity-720.webp` | `../play_store_assets/member/screenshots-real-ui/02-activity.png` | 720 x 1280 | Member App feature proof | Verified real UI |
| `public/images/product/member/workouts-720.webp` | `../play_store_assets/member/screenshots-real-ui/03-workouts.png` | 720 x 1280 | Homepage, Product, Member App, workflow | Verified real UI |
| `public/images/product/member/workout-history-720.webp` | `../play_store_assets/member/screenshots-real-ui/04-workout-history.png` | 720 x 1280 | Member App feature proof | Verified real UI |
| `public/images/product/member/fitness-connected-540.webp` | `../play_store_assets/member/screenshots/01-fitness-connected.png` | 540 x 960 | Promotional illustration | Conceptual; label as promotional |
| `public/images/product/member/train-with-plan-540.webp` | `../play_store_assets/member/screenshots/02-train-with-plan.png` | 540 x 960 | Promotional illustration | Conceptual; label as promotional |
| `public/images/product/member/feature-network-1024.webp` | `../play_store_assets/member/feature-graphic-source.png` | 1024 x 500 | Decorative network artwork | Approved supporting art |
| `public/images/product/member/app-store-dashboard-645.webp` | `../app_store_assets/member/screenshots-6.9/01-dashboard.png` | 645 x 1398 | Optional App Store composition | Hold until store-link verification |

## Trainer App assets

| Public file | Source | Dimensions | Use | Status |
|---|---|---:|---|---|
| `public/images/product/trainer/dashboard-720.webp` | `../play_store_assets/trainer/screenshots-real-ui/01-dashboard.png` | 720 x 1280 | Homepage, Product, Trainer App | Verified real UI |
| `public/images/product/trainer/clients-720.webp` | `../play_store_assets/trainer/screenshots-real-ui/02-clients.png` | 720 x 1280 | Trainer App and workflow | Verified real UI |
| `public/images/product/trainer/workout-builder-720.webp` | `../play_store_assets/trainer/screenshots-real-ui/03-workout-builder.png` | 720 x 1280 | Homepage, Product, Trainer App | Verified real UI |
| `public/images/product/trainer/notifications-720.webp` | `../play_store_assets/trainer/screenshots-real-ui/04-notifications.png` | 720 x 1280 | Trainer App feature proof | Verified real UI |
| `public/images/product/trainer/coach-with-clarity-540.webp` | `../play_store_assets/trainer/screenshots/01-coach-with-clarity.png` | 540 x 960 | Promotional illustration | Conceptual; label as promotional |
| `public/images/product/trainer/know-every-client-540.webp` | `../play_store_assets/trainer/screenshots/03-know-every-client.png` | 540 x 960 | Promotional illustration | Conceptual; label as promotional |
| `public/images/product/trainer/feature-network-1024.webp` | `../play_store_assets/trainer/feature-graphic-source.png` | 1024 x 500 | Decorative network artwork | Approved supporting art |
| `public/images/product/trainer/app-store-dashboard-645.webp` | `../app_store_assets/trainer/screenshots-6.9/01-dashboard.png` | 645 x 1398 | Optional App Store composition | Hold until store-link verification |

## Privacy and claim boundaries

- Real UI assets may contain seeded demonstration names and metrics. They must be re-reviewed before production deployment.
- Promotional store compositions are illustrations and must not be described as literal current screenshots.
- App Store compositions remain unused until live store URLs and current listing state are verified.
- Gym Admin and Platform Admin screenshots remain pending because no browser capture surface was available during this implementation run.
- Admin screenshots must use synthetic accounts and pass the privacy checklist in `public-website-visual-assets-plan.md`.

## Export commands

The current derivatives were produced with macOS `sips`, `cwebp`, and ImageMagick. WebP screenshot derivatives use a maximum width of 720 pixels and quality 84. Promotional derivatives use a maximum width of 540 pixels and quality 82. The social image is a centered 1200 x 630 JPEG crop.

## Remaining asset gates

- Capture real Gym Admin desktop and mobile states.
- Capture real Platform Admin desktop and mobile states.
- Create art-directed homepage desktop/mobile montage after visual browser review.
- Verify and add live Play Store/App Store links.
- Re-review seeded names, dates, financial values, and notification content before production.
