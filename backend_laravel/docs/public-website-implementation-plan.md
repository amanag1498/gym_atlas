
# Gym Atlas Laravel Public Website Implementation Plan

## Objective

Rebuild the Laravel public website as a consistent, premium, mobile-first product website that:

- Uses the same visual language as the Laravel TailAdmin backend.
- Explains the real Member App, Trainer App, and Gym Admin functionality without exposing platform-owner administration.
- Preserves existing gym discovery, public gym profile, trial request, contact, and authentication behavior.
- Uses real product screenshots and existing store artwork to make features understandable.
- Clearly separates currently available functionality from planned functionality.
- Meets production accessibility, responsive, SEO, and performance standards.

## Implementation status — 1 August 2026

The in-repository implementation for phases 1–9 and the follow-up premium composition pass is complete except for the explicitly listed external release gates. The Laravel public site now has a shared premium shell, mobile navigation, detailed audience and product pages, rebuilt discovery/supporting/legal pages, real optimized Member and Trainer visuals, SEO/schema/sitemap/robots support, and regression coverage for its routes, assets, metadata, and forms.

The premium composition pass replaced the repeated basic page template with route-specific storytelling across all 17 public routes. Product pages now use ecosystem mosaics, app journals, coaching workspaces, screenshot stages, and workflow timelines; operator pages use management consoles and operational sequences; support and legal pages use dedicated knowledge-hub, support-desk, editorial-document, and deletion-journey layouts. Three optimized editorial assets were generated for the ecosystem, trainer/member coaching, and gym-operations stories, while all product UI evidence continues to use real application screenshots.

The unattended hardening pass converted the FAQ’s decorative search treatment into working search and topic filters, removed placeholder Unsplash dependencies from discovery cards, replaced legacy gym-profile fallback artwork with the local editorial library, corrected discovery result grammar, completed image dimensions and below-fold loading behavior, improved mobile touch targets, strengthened selected-state semantics, and expanded asset regression coverage across every static public route.

The access-model pass documents three supported entry paths consistently: people may use the Member App independently, trainers may create an independent account but must be verified before adding members or managing member plans, and gyms may onboard the complete operating workspace. It also adds WhatsApp as an enquiry channel and replaces the oversized repeated end-of-page CTA with a compact contact strip.

Completed validation:

- Blade compilation passes.
- The focused public website, supporting-page, and discovery suites pass: 44 tests and 609 assertions.
- The Vite production build passes with the installed Node 22.23.2 runtime.
- Public JavaScript syntax and `git diff --check` pass.
- Mobile browser QA passes for every public route with no horizontal overflow or broken images. Representative desktop and mobile visual review, mobile navigation, filter-drawer focus behavior, and browser-console checks also pass.
- An 85-case responsive browser matrix (17 routes at 360, 390, 768, 1024, and 1440 pixels) passes its heading, landmark, metadata, accessible-name, duplicate-ID, image, and horizontal-overflow checks.
- Keyboard verification passes for the mobile navigation and gym-filter dialogs, including focus entry, Escape dismissal, and return focus. The FAQ search, category filters, result count, and empty state also pass functional browser verification.
- An independent route, asset, form-contract, accessibility-markup, SEO, and marketing-claim review was completed; actionable findings were corrected.

Broader repository status: the complete Laravel suite currently reports 222 passing tests, 18 failures, and 3 errors across pre-existing attendance, gym-profile, reporting, billing, and platform-administration behavior. None of the failures are in the public website suites, but those unrelated failures must be resolved separately before treating the whole backend test suite as green.

Release gates that require an external environment or product-owner decision remain deliberately open:

- Run Lighthouse against a production-like deployment. The local environment does not include Lighthouse or a compatible standalone Chromium binary; the complete five-width browser matrix, representative screenshots, metadata checks, image-loading audit, and interaction checks have been completed instead.
- Capture sanitized Gym Admin and Platform Admin screenshots from a controlled demo account. The website currently uses truthful workflow explanations instead of invented admin screens.
- Confirm current App Store and Play Store listing URLs before enabling download buttons.
- Obtain legal/operations approval for policy wording, account-removal handling, commercial terms, and effective dates.
- Recheck demo names and metrics visible inside the supplied Member and Trainer screenshots before production publication.

The detailed checklists below remain the reproducible implementation and release procedure. Open external gates are intentionally not represented as completed work.

## Scope

Public Laravel routes and views in `backend_laravel`:

- `/`
- `/gyms`
- `/gyms/{slug}`
- `/for-gyms`
- `/for-trainers`
- `/pricing`
- `/about`
- `/contact`
- `/privacy-policy`
- `/terms`
- `/account-deletion`
- New product and audience pages described below

The authenticated Laravel panels and Flutter apps are sources of design and product truth. Their behavior is not being redesigned as part of this website project.

## Non-negotiable rules

1. Do not claim a feature is available until its current route, controller, screen, or tested workflow is verified.
2. Label future functionality as `Planned`; do not mix it into current feature lists.
3. Preserve every existing public form action, query parameter, validation response, and redirect.
4. Use real UI screenshots for product proof. Promotional compositions must not be represented as literal app screens.
5. Build mobile layouts deliberately at 360, 390, 768, 1024, and 1440 pixel widths.
6. Keep content detailed but scannable through tabs, grouped feature sections, annotated screenshots, accordions, and workflow diagrams.
7. Reuse the TailAdmin Outfit typography, brand colors, Tabler icon language, button geometry, slate hierarchy, and restrained elevation.

## Target information architecture

### Primary navigation

- Product
  - Product Overview
  - Member App
  - Trainer App
  - Gym Management
  - Platform Administration
  - How Atlas Works
- Find Gyms
- Pricing
- Resources
- Company
- Gym Login
- Get Started

Privacy, Terms, and Account Deletion remain in the footer and help/legal navigation.

### New public pages

- `/product`
- `/member-app`
- `/trainer-app`
- `/gym-management`
- `/platform-administration`
- `/how-it-works`
- `/faq`

Routes may be adjusted during implementation if existing route naming conventions require it.

## Agent workstreams

### Product truth agent

- Inventory working Member, Trainer, Gym Admin, Platform Admin, public discovery, realtime, and notification functionality.
- Produce `docs/public-website-feature-matrix.md`.
- Mark each capability `Current`, `Limited`, or `Planned/Unsupported`.

### Visual asset agent

- Inventory Play Store and App Store artwork.
- Separate real screenshots from promotional compositions.
- Map assets to website sections and specify responsive derivatives.
- Produce `docs/public-website-visual-assets-plan.md`.

### UI audit agent

- Record page-specific consistency, responsive, accessibility, SEO, and performance gaps.
- Produce `docs/public-website-ui-audit.md`.

### Primary implementation agent

- Own shared tokens, components, layout, routes, page implementation, validation, and integration.
- Reconcile all agent outputs before claims or visuals are published.

## Phase 1: Baseline and product truth

- [x] Confirm public Laravel routes and Blade view inventory.
- [x] Identify the Laravel TailAdmin design source.
- [x] Identify Member and Trainer store artwork and real screenshots.
- [x] Finish the verified product feature matrix.
- [ ] Record baseline screenshots for every public page at all target widths.
- [ ] Record current Lighthouse and accessibility results.
- [ ] Confirm current pricing and App Store/Play Store URLs with the product owner.
- [x] Identify which screenshots contain personal/demo data that must be replaced.

Deliverable: approved product/content matrix and visual baseline.

## Phase 2: Public design-system foundation

- [x] Create a dedicated public-site CSS entry point.
- [x] Create a dedicated public-site JavaScript entry point.
- [x] Stop authenticated-panel theme/sidebar initialization on public pages.
- [x] Define public tokens for color, typography, spacing, radius, elevation, borders, motion, and breakpoints.
- [x] Add shared container, section, hero, button, card, badge, form, media-frame, app-shot, browser-shot, CTA, and empty-state primitives.
- [x] Add visible keyboard focus and reduced-motion behavior.
- [x] Replace mixed icon packs with Tabler icons.
- [x] Progressively remove Yogalax CSS/JavaScript dependencies.
- [x] Move inline layout and page CSS into maintainable stylesheets.

Deliverable: shared public shell and component showcase with no route behavior changes.

## Phase 3: Navigation, footer, and global metadata

- [x] Rebuild desktop navigation with a clear Product menu.
- [x] Rebuild mobile navigation as an accessible drawer/dialog.
- [x] Add audience-specific primary and secondary CTAs.
- [x] Rebuild footer with Product, Audiences, Company, Help, Legal, and download groups.
- [x] Use the optimized Atlas master icon for header, favicon, touch icon, and social identity.
- [x] Add canonical, Open Graph, Twitter, and default social-image metadata.
- [x] Add Organization and SoftwareApplication structured data.
- [x] Add skip navigation and landmark structure.

Deliverable: consistent, accessible chrome on every public route.

## Phase 4: Homepage and product story

- [x] Replace the stock-photo hero with an Atlas product ecosystem hero.
- [x] Create a desktop montage using real Member and Trainer UI screenshots.
- [x] Provide a simplified mobile hero composition.
- [x] Explain the four product surfaces: Member, Trainer, Gym Admin, Platform Admin.
- [x] Add the discovery-to-retention workflow.
- [x] Add feature tabs backed by real screenshots.
- [x] Retain featured-gym data and link it to discovery.
- [x] Add trust/proof metrics only when their meaning is verified.
- [x] Add appropriate Find Gym, Register Gym, and Explore Product CTAs.

Deliverable: homepage that explains the complete platform within one scroll narrative.

## Phase 5: Detailed product and audience pages

### Member App

- [x] Gym discovery, favourites, trial requests, and invitations.
- [x] Membership, attendance, trainer, and notifications.
- [x] Assigned and self-managed workouts, workout books, sessions, logbook, and history.
- [x] Diet plans, templates, meal logging, progress, steps, measurements, weight, and photos.
- [x] Messaging and safety controls where verified.
- [ ] Real Member App screenshot sequence and download CTA.

### Trainer App

- [x] Daily coaching overview, assigned members, and member detail.
- [x] Tasks, follow-ups, notes, attendance, and progress review.
- [x] Workout templates, plans, exercises, assignment, and builder workflow.
- [x] Diet-plan and template workflow.
- [x] Profile, certifications, announcements, notifications, trials, invitations, and messaging where verified.
- [ ] Real Trainer App screenshot sequence and access CTA.

### Gym Management

- [x] Members, trainers, staff, branches, roles, and invitations.
- [x] Plans, memberships, renewals, freeze/reactivate/extend/cancel, custom fees, and audit history.
- [x] Payments, receipts, dues, attendance, biometric/manual check-in, and reporting.
- [x] Trials, reminders, announcements, notifications, public listing, settings, and audit logs.
- [ ] Real Laravel dashboard screenshots in browser frames.

### Platform Administration

- [x] Gyms, owners, users, listings, featured/promoted placement, and approvals.
- [x] Platform plans, gym billing, subscription ledgers, and reports.
- [x] Facilities, exercises, workout books, diet templates, banners, goals, specializations, and cities.
- [x] Enquiries, announcements, notifications, settings, audit logs, and exports.
- [ ] Real Laravel admin screenshots with sensitive operational details removed.

Deliverable: complete, evidence-backed product documentation in marketing form.

## Phase 6: Discovery and gym profiles

- [x] Rebuild filter controls using shared inputs and accessible checkboxes.
- [x] Preserve search, city, price, facility, trial, verification, promotion, women-friendly, open-now, personal-training, and location filters.
- [x] Add mobile filter drawer and active-filter summary.
- [x] Rebuild gym cards with responsive images and consistent states.
- [x] Rebuild gym detail hero, quick facts, facilities, plans, branches, gallery, trainers, map, and trial/contact flow.
- [x] Add LocalBusiness/HealthClub and breadcrumb structured data.
- [x] Keep validation errors and submitted-state behavior intact.

Deliverable: premium and usable discovery flow on desktop and mobile.

## Phase 7: Supporting, pricing, and legal pages

- [x] Replace speculative pricing copy with confirmed current commercial information.
- [x] Separate current pricing from upcoming packages.
- [x] Rebuild About around mission, ecosystem, trust, and operating model.
- [x] Rebuild Contact with persistent labels and audience-aware routing.
- [x] Add detailed FAQ with FAQ structured data.
- [x] Restyle Privacy, Terms, and Account Deletion consistently.
- [x] Explain individual Member App access across pricing and product journeys.
- [x] Explain verified independent Trainer App access without implying a trainer marketplace.
- [x] Add the public WhatsApp enquiry path (`+91 74510 08842`).
- [x] Replace the oversized repeated answer CTA with a compact responsive contact treatment.
- [ ] Obtain legal review for policy wording, retention claims, and effective dates.

Deliverable: consistent supporting pages with trustworthy content.

## Phase 8: Visual production

- [ ] Verify every existing Play/App Store screenshot against the current UI.
- [ ] Recapture outdated Member and Trainer screens.
- [ ] Capture Gym Admin and Platform Admin screens at a controlled demo state.
- [x] Create website-specific wide hero, split-feature, device-montage, and browser-frame compositions.
- [x] Use Member and Trainer store feature graphics as brand-language references.
- [ ] Generate additional abstract Atlas ecosystem artwork only where it improves explanation.
- [x] Do not generate fake product screens.
- [x] Export AVIF/WebP/PNG fallbacks at responsive widths.
- [x] Add meaningful alternative text, intrinsic dimensions, and lazy loading.

Deliverable: optimized web media library with traceable source files.

## Phase 9: Accessibility, SEO, performance, and validation

- [x] Test keyboard navigation and focus order.
- [x] Test screen-reader names, landmarks, errors, and status messages.
- [ ] Meet WCAG AA contrast and 44px touch-target guidance.
- [x] Verify reduced-motion support.
- [x] Run responsive screenshot review at 360, 390, 768, 1024, and 1440 widths.
- [x] Run focused Laravel public-page, discovery, contact, and trial-request tests.
- [x] Run the Vite production build.
- [x] Validate structured data, metadata, sitemap, and robots behavior.
- [ ] Target Lighthouse 90+ for Accessibility, SEO, and Best Practices; measure mobile performance and document justified exceptions.
- [x] Verify no horizontal overflow, broken routes, missing images, or unverified feature claims.

Deliverable: production-readiness report and final before/after screenshot set.

## Implementation order

1. Product truth and baseline.
2. Public CSS/JavaScript isolation.
3. Shared design system.
4. Navigation/footer/metadata.
5. Homepage.
6. Product and audience pages.
7. Discovery and gym profiles.
8. Supporting and legal pages.
9. Visual production and optimization.
10. Full QA and release handoff.

## Definition of done

The project is complete only when every public route uses the shared design system, every supported product surface is explained with verified content and appropriate visuals, every existing public workflow still functions, all target mobile widths pass manual review, and the validation checklist is recorded with reproducible commands and results.
