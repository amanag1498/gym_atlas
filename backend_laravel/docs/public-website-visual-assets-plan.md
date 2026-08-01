# Atlas public website visual-assets plan

Status: implementation specification  
Audit date: 2026-08-01  
Scope: Laravel public website only

## Purpose

Use the existing Play Store and App Store material to make the Atlas public website easier to understand and visually consistent with the product. Real application screenshots will be the primary proof of functionality. Promotional store artwork and future generated imagery will support the story, but must never imply that a conceptual interface or unverified feature is already available.

This document is a companion to the public-website implementation plan. It defines which source assets to use, where each visual belongs, what must be adapted for the web, and how the resulting media should be shipped.

## Non-negotiable rules

1. Keep `play_store_assets/` and `app_store_assets/` as source archives. Do not serve those large originals directly from a public page.
2. Put approved web derivatives under `backend_laravel/public/images/public-site/` with descriptive, stable filenames.
3. Use real UI screenshots for feature claims. Label store-style composites as promotional illustrations when their data or layout does not exactly exist in the current app.
4. Do not generate fake Member, Trainer, Gym Admin, or Platform Admin interfaces.
5. Verify every screenshot against the current application before publishing it. Recapture any screen that is stale, contains test data that should not be public, or shows a feature that changed.
6. Do not place important explanatory text inside an image. Headings, feature descriptions, buttons, and links must remain semantic HTML.
7. Every decorative image must use empty alternative text. Every informative product image must have concise, purpose-specific alternative text.
8. The website must remain understandable if images fail to load or are hidden.

## Audited source inventory

### Brand assets

| Source | Size | Decision | Notes |
|---|---:|---|---|
| `play_store_assets/branding/atlas-master-icon.png` | 1254 x 1254 | Reuse and adapt | Highest-resolution Atlas mark in this asset set. Use as the source for web brand-mark and favicon derivatives. It includes a dark square background, so it is not a substitute for a transparent horizontal wordmark. |
| `play_store_assets/branding/atlas-play-store-icon.png` | 512 x 512 | Reuse selectively | Store-ready icon. Use only where an app icon is expected, such as app download cards; do not use it as the navigation logo. |

### Member assets

| Group | Files | Size | Decision | Notes |
|---|---|---:|---|---|
| Promotional screenshots | `play_store_assets/member/screenshots/01-fitness-connected.png` through `04-coaching-close.png` | 1080 x 1920 each | Adapt | Strong premium compositions and feature-led headlines. Several panels are conceptual marketing UI, so they must not be described as literal screenshots. |
| Real UI screenshots | `play_store_assets/member/screenshots-real-ui/01-dashboard.png` through `04-workout-history.png` | 1080 x 1920 each | Reuse after verification | Best Android-format proof for dashboard, activity, workouts, and history. Crop only device-safe blank margins; do not crop away navigation or state needed to understand the screen. |
| Feature graphic | `play_store_assets/member/feature-graphic-1024x500.png` | 1024 x 500 | Reuse as supporting art | Dark Atlas/network artwork works well as a wide section background or Member App card. It does not explain a feature by itself. |
| Feature graphic source | `play_store_assets/member/feature-graphic-source.png` | 1796 x 876 | Adapt from source | Preferred input when creating larger web derivatives. |
| App Store screenshots | `app_store_assets/member/screenshots-6.9/*.png` | 1290 x 2796 each | Preferred high-resolution real UI | These combine real UI with simple App Store framing and short text. Use for full phone compositions when that framing is useful. |
| App Store 6.5-inch screenshots | `app_store_assets/member/screenshots-6.5/*.png` | 1284 x 2778 each | Fallback only | Retain for device-specific store delivery. Do not create duplicate website sections when the 6.9-inch set communicates the same content. |

### Trainer assets

| Group | Files | Size | Decision | Notes |
|---|---|---:|---|---|
| Promotional screenshots | `play_store_assets/trainer/screenshots/01-coach-with-clarity.png` through `04-stay-connected.png` | 1080 x 1920 each | Adapt | Useful for premium feature introductions. Treat the dashboard fragments, progress values, messages, alerts, and safety controls as promotional illustration unless verified against the current Trainer App. |
| Real UI screenshots | `play_store_assets/trainer/screenshots-real-ui/01-dashboard.png` through `04-notifications.png` | 1080 x 1920 each | Reuse after verification | Direct proof for coaching overview, assigned clients, workout builder, and notifications. |
| Feature graphic | `play_store_assets/trainer/feature-graphic-1024x500.png` | 1024 x 500 | Reuse as supporting art | Dark network/people artwork suits the Trainer App overview or ecosystem connection section. |
| Feature graphic source | `play_store_assets/trainer/feature-graphic-source.png` | 1795 x 876 | Adapt from source | Preferred input for wide web derivatives. |
| App Store screenshots | `app_store_assets/trainer/screenshots-6.9/*.png` | 1290 x 2796 each | Preferred high-resolution real UI | Use when the section benefits from a complete phone-shaped composition and the current content has been verified. |
| App Store 6.5-inch screenshots | `app_store_assets/trainer/screenshots-6.5/*.png` | 1284 x 2778 each | Fallback only | Preserve for store delivery; omit from the website when visually redundant. |

## Reuse, adapt, and avoid decisions

### Reuse directly after optimization

- The master icon for favicon, app card, and compact brand-mark derivatives.
- Member real UI: dashboard, activity, workouts, and workout history.
- Trainer real UI: dashboard, assigned clients, workout builder, and notifications.
- The two wide feature graphics as background/supporting illustrations.
- App Store 6.9-inch screenshots where their dark framing and caption suit the section.

### Adapt for the website

- Convert tall Play Store promotional posters into web feature cards; do not place all eight full-height posters in one carousel.
- Create layered Member/Trainer phone montages for the homepage and Product overview, using real UI screens inside consistent device frames.
- Crop store graphics to responsive compositions without cutting off the Atlas mark or meaningful interface regions.
- Replace text baked into store images with HTML when creating new web compositions.
- Create desktop browser-frame screenshots for Gym Admin and Platform Admin because those roles are not represented by the current mobile asset folders.
- Produce light and dark variants only when contrast can be maintained without recoloring real UI.

### Avoid or hold back

- Do not use a promotional poster as evidence of a working feature when its panels do not match the app.
- Do not show QR codes from promotional artwork as a real download or check-in action unless the encoded target and workflow are verified.
- Do not use both the 6.5-inch and 6.9-inch versions of the same screen on the website.
- Do not ship multi-megabyte PNG originals, CSS background images without responsive alternatives, or remote image URLs.
- Do not place screenshots containing private member information, real phone numbers, emails, gym financial data, access tokens, or identifiable conversations.
- Do not stretch the square Atlas icon into a horizontal logo. Create or source a proper wordmark separately if navigation requires one.

## Exact website placement map

| Page / section | Primary visual | Source or production requirement | Desktop treatment | Mobile treatment |
|---|---|---|---|---|
| Global navigation | Compact Atlas mark plus HTML wordmark | Derivative of `atlas-master-icon.png`; wordmark rendered as text until an approved vector exists | 32-40 px mark | 28-32 px mark |
| Home hero | Member dashboard and Trainer dashboard phone montage | Verified real UI screenshots; use the 6.9-inch or real-UI set, not conceptual panels | Two overlapping devices beside the main value proposition | One dominant device with the second partially visible; no horizontal overflow |
| Home: ecosystem overview | Member, Trainer, Gym Admin, Platform Admin connection visual | Existing Member/Trainer UI plus new real admin captures and a restrained generated background | Four-role connected composition | Stacked four-step visual sequence |
| Home: Member outcome | Member activity or workout screen | Member real UI `02-activity.png` or `03-workouts.png` | Screenshot beside feature copy | Screenshot below summary with expandable details |
| Home: Trainer outcome | Assigned clients or workout builder | Trainer real UI `02-clients.png` or `03-workout-builder.png` | Screenshot beside feature copy | Screenshot below summary |
| Home: final CTA | Atlas network artwork | Member or Trainer feature graphic, chosen to avoid duplication above | Wide background with HTML CTA overlay | Simplified center crop; CTA remains HTML |
| Product overview hero | Connected Atlas ecosystem illustration | New website composition using both feature-graphic visual languages | Wide 16:9 composition | Purpose-built 4:5 crop, not an automatic center crop |
| Product overview workflow | Real screens connected by steps | Member dashboard, Trainer clients, new Gym Admin dashboard, new Platform Admin dashboard | Alternating four-step timeline | Vertically stacked numbered steps |
| Member App hero | Member App Store dashboard composition | `app_store_assets/member/screenshots-6.9/01-dashboard.png` after verification | Phone frame plus short feature list | Centered phone below headline |
| Member App feature: activity | Real activity screen | Member real UI `02-activity.png` | 5:7 media card | Full-width card |
| Member App feature: plans | Real workouts screen | Member real UI `03-workouts.png` | 5:7 media card | Full-width card |
| Member App feature: history | Real history screen | Member real UI `04-workout-history.png` | 5:7 media card | Full-width card |
| Member App visual interlude | Fitness-connected or train-with-plan poster | Member promotional `01` or `02`, explicitly treated as promotional illustration | Cropped 4:5 feature panel | Cropped 4:5 or omitted on very small screens if repetitive |
| Trainer App hero | Trainer App Store dashboard composition | `app_store_assets/trainer/screenshots-6.9/01-dashboard.png` after verification | Phone frame plus role summary | Centered phone below headline |
| Trainer feature: clients | Real client roster | Trainer real UI `02-clients.png` | 5:7 media card | Full-width card |
| Trainer feature: programs | Real workout builder | Trainer real UI `03-workout-builder.png` | 5:7 media card | Full-width card |
| Trainer feature: alerts | Real notifications | Trainer real UI `04-notifications.png` | 5:7 media card | Full-width card |
| Trainer visual interlude | Coach-with-clarity or build-better-programs poster | Trainer promotional `01` or `02`, labeled as promotional illustration if needed | Cropped 4:5 feature panel | Cropped 4:5 or omitted if repetitive |
| Gym Management hero | Gym dashboard in browser frame | New sanitized capture from the Laravel Gym Admin panel | 16:10 browser frame | Scroll-safe 4:3 detail crop plus a `View dashboard features` control |
| Gym Management feature sections | Members, staff/trainers, plans, attendance, billing/dues, reports, listing controls | New sanitized real captures | One screenshot per feature group; use annotated hotspots sparingly | One screen crop per group; annotation text outside image |
| Platform Administration hero | Platform dashboard in browser frame | New sanitized capture from the Laravel Platform Admin panel | 16:10 browser frame | 4:3 crop focused on navigation and key summary |
| Platform Administration features | Gym approval, users, catalogues, subscriptions, reports, announcements, auditing/settings | New sanitized real captures | Tabbed or alternating media sections | Accordion with one image loaded for the open item |
| How Atlas Works | Discovery-to-retention sequence | Real public gym page, Member screen, Trainer screen, Gym Admin capture | Horizontal step sequence | Vertical step sequence |
| Find Gyms | Real public listing UI | Current Laravel public gym listing; capture only after redesign | Wide browser frame only if explanatory content needs it | Do not add a redundant screenshot above the live listing |
| Gym detail explanation | Real public profile, plans, facilities, trainer and trial flow | Current Laravel gym detail page after redesign | Annotated browser-frame excerpt | Stacked live page sections; avoid screenshot duplication |
| App download CTA | Member and Trainer store icons/phones | Atlas app icon plus verified store URLs and selected real UI | Two app cards | Stacked app cards |
| Social sharing | Atlas logo, Member and Trainer devices, short HTML-equivalent title in image | New 1200 x 630 export | Open Graph image | Same asset; keep central safe area |

## Admin screenshot capture checklist

Before capturing Gym Admin or Platform Admin screens:

1. Use a seeded demonstration account and synthetic data.
2. Set the viewport to 1440 x 1024 at device scale factor 2 where possible.
3. Capture complete screens without browser extensions, personal bookmarks, system notifications, or developer overlays.
4. Use realistic but non-identifying names, membership plans, amounts, dates, and charts.
5. Check for email addresses, phone numbers, account IDs, API keys, payment references, addresses, chat text, and uploaded documents.
6. Capture both the information-rich desktop view and a real mobile layout where the panel supports mobile.
7. Record the route, role, date, commit, viewport, and feature state in an adjacent asset manifest.
8. Re-capture after major UI or data-contract changes instead of editing the screenshot to hide a stale interface.

## Web derivative specification

All output filenames should be lowercase kebab-case. Originals remain untouched.

### Directory proposal

```text
backend_laravel/public/images/public-site/
  brand/
  home/
  member/
  trainer/
  gym-admin/
  platform-admin/
  ecosystem/
  social/
```

### Required exports

| Asset type | Required pixel sizes | Preferred formats | Quality / handling |
|---|---|---|---|
| Navigation brand mark | 32, 64, 128 square | PNG; WebP optional | Keep PNG fallback because this source has hard-edged brand geometry. |
| Favicons / touch icons | 16, 32, 48, 180, 192, 512 square | ICO/PNG | Derive from the master icon; verify small-size legibility. |
| Phone UI screenshot | 360 and 720 px wide | AVIF, WebP; PNG fallback only when text clarity requires it | Preserve aspect ratio. Test labels at final rendered size before accepting lossy output. |
| Promotional poster card | 360 and 540 px wide | AVIF and WebP | Use a 9:16 source or a deliberate 4:5 crop. Do not squeeze. |
| App Store framed phone | 360 and 645 px wide | AVIF and WebP | Use the 6.9-inch source. Keep enough resolution for a 2x mobile display. |
| Wide feature artwork | 640, 1024, and 1600 px wide | AVIF and WebP | Generate the 1600-wide derivative from the feature-graphic source, not the 1024 export. |
| Admin browser screenshot | 720, 1280, and 1920 px wide | AVIF and WebP; PNG fallback for fine tables | Capture at 2x. Check table text and graph labels after conversion. |
| Admin mobile screenshot | 390 and 780 px wide | AVIF and WebP | Must be a real responsive capture, not a desktop crop masquerading as mobile. |
| Homepage hero montage | 900 x 1100 mobile; 1600 x 1000 desktop | AVIF and WebP | Create two art-directed compositions and select with `<picture>`. |
| Ecosystem illustration | 900 x 1125 mobile; 1600 x 900 desktop | AVIF and WebP | Keep all meaningful connections inside the safe area. |
| Open Graph image | 1200 x 630 | JPEG or WebP plus PNG master | Keep logo and title within the central 1000 x 500 safe area. |

### HTML delivery pattern

- Use `<picture>` for art-directed hero and ecosystem images.
- Use width-based `srcset` and an accurate `sizes` attribute for screenshots and cards.
- Set intrinsic `width` and `height` on every image to prevent layout shift.
- Use `loading="eager"` and `fetchpriority="high"` only for the single above-the-fold hero image.
- Use `loading="lazy"` and `decoding="async"` for below-the-fold media.
- Do not lazy-load the logo or the largest-contentful-paint image.
- Do not use a CSS background for an informative screenshot. Backgrounds are reserved for decorative art.
- Keep a local fallback format. The public site must not depend on Play Store, App Store, Unsplash, or other remote image hosts at runtime.

### Initial file-size budgets

| Delivered asset | Target transfer size |
|---|---:|
| Navigation logo/icon | <= 25 KB |
| Mobile screenshot candidate | <= 120 KB |
| Desktop screenshot candidate | <= 220 KB |
| Mobile hero composition | <= 180 KB |
| Desktop hero composition | <= 300 KB |
| Decorative section background | <= 180 KB |
| Open Graph image | <= 300 KB |

These are delivery budgets, not source-file limits. If an interface becomes unreadable at the target budget, prefer a smaller rendered dimension or PNG fallback for that specific image rather than aggressively blurring text.

## Accessibility guidance

### Alternative text rules

- Describe the feature demonstrated, not every visible pixel. Example: `Atlas Member workout screen showing assigned Strength Foundation and Mobility Reset plans.`
- Do not start alternative text with `Image of` or repeat the adjacent heading.
- For screenshots with a detailed explanation next to them, keep alt text concise and provide the full explanation in HTML.
- Use `alt=""` for gradients, light trails, abstract network backgrounds, and repeated phone screens that add no information beyond a preceding informative image.
- If a screenshot is the only explanation of a workflow, add an HTML caption or transcript of the important state and actions.
- Do not put the same alt text on a light and dark duplicate displayed for responsive purposes; only the chosen `<picture>` needs one `alt` on its `<img>`.

### Visual accessibility rules

- Maintain at least WCAG AA contrast for all HTML text over decorative backgrounds. Add a solid or gradient scrim when required.
- Do not rely on blue, cyan, or purple color alone to distinguish user roles or states.
- Never make a screenshot the target of the primary call to action; use a separate labeled button or link.
- Keep captions at a minimum readable size and outside screenshot pixels.
- Respect `prefers-reduced-motion`; device montages may have subtle entry motion, but must not autoplay parallax or continuous floating animation for reduced-motion users.
- Ensure zooming to 200% does not crop essential screenshot explanations or lock a device frame to a fixed height.

## Performance and maintenance guidance

- Build an asset manifest containing source path, derivative paths, feature/page placement, capture date, verification date, app version, and approval status.
- Include derivatives in visual-regression review so a replacement cannot silently introduce private data or stale functionality.
- Prefer a maximum of one large product visual above the fold and one screenshot per feature group.
- Load tab/accordion screenshots on demand when they are not initially visible.
- Set long-lived immutable cache headers on fingerprinted derivatives. If filenames are not fingerprinted, include a version suffix when content changes.
- Run image-dimension, broken-reference, and oversized-file checks in the website quality gate.
- Review all screenshots at 360, 390, 768, 1024, and 1440 px page widths.
- Re-audit product screenshots for each public-site release that changes app navigation, theme, or feature availability.

## Future image-generation brief

Generated imagery is supporting material only. It may provide premium backgrounds, human context, and transitions around verified product screenshots.

### Visual language

- Premium fitness technology with a disciplined Atlas blue, deep navy, cool white, and restrained cyan accent palette.
- Clean studio lighting, subtle glass and dimensional layers, fine network lines, and purposeful negative space.
- Modern Indian gym context where people appear; inclusive ages, genders, body types, and skin tones without tokenistic group staging.
- Energetic but credible, with calm editorial composition rather than exaggerated bodybuilding imagery.
- Match the geometric direction and glow of the existing feature graphics without copying their exact layouts.

### Image set to generate

1. **Home hero background:** abstract connected fitness ecosystem, wide 16:10 with clear negative space for HTML copy on the left and device montage on the right; matching 4:5 mobile variant.
2. **Member lifestyle scene:** a member following a structured workout in a polished contemporary gym, phone visible but screen blank/indistinct so real UI can be composited later.
3. **Trainer lifestyle scene:** a trainer reviewing a member's plan and progress in a collaborative, professional setting; no readable invented data.
4. **Gym operations background:** subtle branch, people, attendance, and analytics motifs arranged as an abstract system, designed to sit behind a real Gym Admin screenshot.
5. **Platform network background:** multiple gyms connected to a central Atlas system, suitable for the Platform Administration hero without resembling a fake dashboard.
6. **Section transitions:** two or three low-detail gradient/network textures that can be cropped broadly and remain decorative.

### Generation constraints

- No fake app or dashboard UI, legible generated text, QR codes, prices, medical metrics, certification badges, or app-store badges.
- No third-party logos, gym trademarks, branded clothing, copyrighted character styles, or recognizable public figures.
- No visual claims of body transformation, guaranteed outcomes, diagnosis, treatment, or medical monitoring.
- Keep limbs, hands, equipment interaction, reflections, and phone geometry plausible.
- Generate clean base images without the Atlas logo; add the approved mark during deterministic asset composition.
- Request enough negative space for responsive crops and produce desktop/mobile compositions separately rather than relying on one crop.
- Review every generated image for bias, anatomical artifacts, unsafe exercise form, illegible background text, and resemblance to a real person.

### Example production prompt direction

> Premium editorial fitness-technology scene in deep navy, Atlas blue, cool white and subtle cyan. Contemporary Indian gym, believable equipment and safe training posture, crisp studio lighting, restrained glass layers and fine connection lines, generous clean negative space for web copy, no logos, no readable text, no app interface, no medical claims, realistic photography, wide website composition.

The final production prompt must name the intended page, crop, negative-space side, and whether a real screenshot will be composited into the scene.

## Step-by-step visual implementation

### Step 1: Verify and classify

- Review each real UI screenshot against the current Member and Trainer builds.
- Mark each promotional poster panel as `verified feature`, `conceptual presentation`, or `do not publish`.
- Select one canonical screenshot per feature; default to the 6.9-inch set for high-resolution framed presentations and the real-UI set for clean crops.
- Record the decisions in the asset manifest.

**Exit condition:** every existing source image has an owner, classification, last-verified date, and intended placement or explicit rejection.

### Step 2: Capture missing admin proof

- Capture Gym Admin and Platform Admin using sanitized demo data.
- Capture the public listing and gym-detail workflows only after those pages have the new design system.
- Produce desktop and genuine mobile captures where appropriate.

**Exit condition:** all website feature claims have matching real product proof or are intentionally text-only.

### Step 3: Produce website compositions

- Build homepage device montage, role-specific hero visuals, and the ecosystem sequence.
- Adapt selected promotional posters into 4:5 web cards.
- Keep feature descriptions and CTAs in HTML.
- Create the social-sharing master image.

**Exit condition:** desktop and mobile art direction exists for every high-priority page without duplicating the same visual repeatedly.

### Step 4: Generate supporting imagery

- Generate only the approved backgrounds/lifestyle scenes from the brief above.
- Review them for brand fit, safe exercise representation, artifacts, and prohibited claims.
- Composite the approved Atlas logo or real product screens deterministically after generation.

**Exit condition:** every generated asset is clearly decorative/supporting and cannot be mistaken for a real interface.

### Step 5: Export and integrate

- Export AVIF and WebP derivatives at the specified dimensions.
- Add PNG/JPEG fallbacks only where required.
- Implement `<picture>`, `srcset`, intrinsic dimensions, loading priority, captions, and alt text.
- Reference only local public-site derivative paths from Blade components.

**Exit condition:** no source-sized store PNG or remote placeholder is delivered by a public page.

### Step 6: Validate

- Review every page at the five target viewport widths.
- Check image sharpness, text readability, crop safety, color contrast, keyboard/zoom behavior, layout shift, and network payload.
- Run broken-image and oversized-file checks.
- Confirm that conceptual promotional art is never described as a real screenshot.
- Obtain final product-owner approval for screenshots and claims.

**Exit condition:** every visual has passed privacy, accuracy, accessibility, responsive, and performance checks.

## Definition of done

- Existing Play Store and App Store assets have been deliberately reused, adapted, or rejected rather than copied wholesale.
- Member, Trainer, Gym Admin, and Platform Admin each have verified visual proof.
- Homepage and Product overview clearly show how the four roles connect.
- Desktop and mobile variants are art-directed, readable, and free from horizontal overflow.
- No private data, invented product UI, unverified QR code, or misleading feature claim is published.
- All delivered images are local, responsive, optimized, accessible, and recorded in an asset manifest.
- The website remains fully understandable without images.
