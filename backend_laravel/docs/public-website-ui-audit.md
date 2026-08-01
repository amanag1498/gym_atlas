# Atlas Laravel Public Website UI Audit

Status: source audit complete; implementation not started in this document  
Scope: `backend_laravel` public Laravel routes, Blade views, shared public components, `resources/css/app.css`, `resources/js/app.js`, and the existing Play Store/App Store visual assets  
Excluded: authenticated admin/gym panel redesign, mobile-app implementation, and functional changes to public forms or gym discovery  

## 1. Objective

Rebuild the Laravel public website as one premium, mobile-first Atlas experience that:

- uses the same visual language as the authenticated Laravel backend;
- explains the real Member App, Trainer App, Gym Admin, and Platform Admin capabilities in enough detail for a new visitor to understand the complete ecosystem;
- uses real product screenshots and the existing store-marketing assets as product proof;
- preserves existing Laravel routes, discovery filters, listing rules, trial requests, contact routing, privacy, terms, and account-deletion behavior;
- meets measurable accessibility, SEO, responsive, performance, and content-accuracy gates.

This is a source-level audit. A browser screenshot pass at the target viewport matrix is still required before implementation baselines are signed off.

## 2. Current public route inventory

| Route | View | Current purpose |
| --- | --- | --- |
| `/` | `public/home.blade.php` | High-level Atlas introduction and featured gyms |
| `/gyms` | `public/gyms/index.blade.php` | Public gym search and filtering |
| `/gyms/{slug}` | `public/gyms/show.blade.php` | Public gym profile and trial request |
| `/for-gyms` | `public/pages/for-gyms.blade.php` | Gym operator marketing and onboarding inquiry |
| `/for-trainers` | `public/pages/for-trainers.blade.php` | Trainer marketing and access inquiry |
| `/pricing` | `public/pages/pricing.blade.php` | Launch pricing and future product lanes |
| `/about` | `public/pages/about.blade.php` | Atlas mission and operating model |
| `/contact` | `public/pages/contact.blade.php` | User, gym, trainer, and support inquiries |
| `/privacy-policy` | `public/pages/privacy-policy.blade.php` | Public privacy overview |
| `/terms` | `public/pages/terms.blade.php` | Public terms overview |
| `/account-deletion` | `public/pages/account-deletion.blade.php` | Member/Trainer deletion request |

There is no dedicated public Member App page, product overview, Platform Admin page, full Gym Admin product tour, ecosystem workflow page, help/FAQ hub, or app-download page.

## 3. Executive findings

### 3.1 The site has multiple competing design systems

The shared public layout loads twelve Yogalax CSS files, Google Fonts, Tailwind/Vite CSS, an embedded override block of more than 500 lines, fifteen legacy JavaScript files, and the shared `app.js`. The gym index and gym profile then define hundreds of lines of page-local CSS. Styling is therefore split between:

1. Yogalax/Bootstrap template rules;
2. Tailwind classes generated from `resources/css/app.css`;
3. public-layout CSS variables and overrides;
4. inline style attributes in almost every page;
5. separate `atlas-*` systems in gym index and gym detail.

This causes inconsistent blue values, typography weights, radii, shadows, spacing, button treatments, form styling, and responsive behavior. It also makes small global changes risky.

Several generic classes used across marketing and legal pages (`atlas-card`, `atlas-lead`, `atlas-hero-copy`, `atlas-dark-panel`, `atlas-alert-success`, `atlas-alert-danger`, `atlas-link`, `atlas-ledger`, and `atlas-float`) have no shared definition in the Laravel public source. `atlas-card` is defined only inside the gym index/detail page-local styles, so it does not style the other pages. This is a visible correctness issue, not only maintainability debt.

### 3.2 The backend theme is not the actual public source of truth

The authenticated panel defines Outfit typography, brand colors (`#465fff`/`#3641f5`), slate surfaces, restrained radii, compact controls, semantic statuses, and reusable panel components in `resources/css/app.css`. The public layout instead centers on `#2563eb`, pill-shaped controls, large soft gradients, and legacy template components. The result is recognizably blue but not component-level parity with the backend.

### 3.3 Product explanation is incomplete and mostly textual

The current site describes discovery, operations, and coaching at a high level, but does not demonstrate the complete working product. Missing public explanations include:

- Member App: onboarding/profile, gym discovery, favourites, trials, memberships, assigned trainer, workout plans/history, diet plans, attendance, activity/progress, notifications, and chat;
- Trainer App: dashboard, assigned clients, workout/program creation, diet planning, progress context, follow-ups, notifications, and member communication;
- Gym Admin: branches, trainers, staff, members, imports, membership plans, custom fees, payments/dues, attendance, trials/leads, public listing controls, reminders, reports, and role/permission behavior;
- Platform Admin: gym approval/verification/listing controls, gym owners, users, catalogues, subscriptions/invoices/ledger, enquiries, reports/exports, announcements/notifications, settings, and audit logs;
- the cross-role journey connecting discovery, onboarding, gym operations, trainer work, and member continuity.

There are no real UI screenshots on any current public page. The website also does not use the existing Play Store/App Store assets.

### 3.4 Mobile support is partial rather than designed

The shared layout has one navigation breakpoint at 992px. Gym index and detail add local rules, while most other pages rely on Bootstrap wrapping and large inline fixed/minimum heights. Common risks are:

- `88vh`, `100vh`, and 26–42rem heroes on short phones or landscape screens;
- hero headings as large as `clamp(3.5rem, 8vw, 6.8rem)` with tight line height;
- a mobile menu containing nine page links plus two actions;
- repeated equal-height multi-column cards with inconsistent bottom spacing;
- two-column forms that collapse without deliberate mobile label/help/error treatment;
- negative-overlap discovery panels and dense filter controls;
- side-by-side action groups and summary rows that can become cramped;
- no site-wide 360px overflow or touch-target contract.

### 3.5 Accessibility fundamentals are missing

- No skip link or explicit main-content target.
- No global `:focus-visible` standard for links, buttons, navigation, cards, or custom controls.
- No `prefers-reduced-motion` handling despite hover transforms, transitions, typewriter behavior, AOS, Waypoints, Scrollax, carousel, and reveal classes.
- Most forms use placeholders as labels. The account-deletion form has neither IDs nor labels.
- Error summaries are not live regions, do not receive focus, and are not connected to individual fields with `aria-describedby`/`aria-invalid`.
- Gym index quick filters hide native checkboxes with `d-none`; state is primarily visual and the controls need keyboard/screen-reader verification.
- Background-image gallery links have no accessible name.
- Footer social/action icon links have no accessible label.
- Decorative SVG/marks do not consistently declare presentation semantics.
- Breadcrumbs are visually styled paragraphs, not a labelled breadcrumb navigation.
- Hover movement and color changes are used without an equivalent site-wide keyboard state.

### 3.6 SEO and structured discovery are minimal

The shared head exposes only a title and description. It lacks:

- canonical URL;
- Open Graph and Twitter metadata;
- default and per-page social image;
- robots directives where appropriate;
- Organization/WebSite/SoftwareApplication JSON-LD;
- LocalBusiness or HealthClub JSON-LD for gym profiles;
- FAQ and BreadcrumbList structured data;
- sitemap route/file and clear robots-to-sitemap linkage;
- app-store deep links or verified application metadata;
- per-gym share title/image controls.

The title format also repeats the application name if the configured name and page title overlap, and dynamic descriptions are not normalized to useful search lengths.

### 3.7 Performance is burdened by global legacy assets

Every public page loads CSS and JavaScript for animation, carousel, popup, parallax, datepicker, timepicker, number animation, jQuery migration, Bootstrap, and icon fonts whether used or not. The layout then forcibly makes animation classes visible because the legacy reveal path is unreliable. Other costs include:

- Google Fonts loaded both by a `<link>` and `@import` in `app.css`;
- remote Unsplash images as runtime dependencies;
- large CSS background images without `srcset`, intrinsic dimensions, browser lazy-loading, or responsive formats;
- no preloading strategy for the actual LCP image;
- large inline CSS blocks that cannot be cached independently;
- approximately 970-line gym index and 916-line gym detail templates;
- multiple icon-font families instead of one optimized icon strategy;
- panel JavaScript initializing theme/sidebar code on public pages even though those elements are absent;
- public navigation behavior coupled to the authenticated-panel entry point.

## 4. Shared shell audit

### Navigation

Current issues:

- Nine first-level links compete with two actions; Privacy and Terms occupy primary navigation space.
- The brand is text plus a decorative dot rather than the available Atlas master mark.
- There is no Product hierarchy or route to understand Member, Trainer, Gym Admin, and Platform Admin together.
- Desktop actions appear before the collapsible link region in DOM order, producing a potentially confusing keyboard sequence.
- Mobile navigation opens/closes visually but has no focus trap, outside-click behavior, focus return, or explicit close action.
- The public `app.js` logic does not close the menu with Escape.
- Active state is based on exact route name and does not establish a clear current-page announcement.
- Store/download actions are absent.

Required direction:

- Product mega-menu or accessible disclosure with Member App, Trainer App, Gym Management, Platform Administration, and How Atlas Works.
- Primary links: Product, Find Gyms, Pricing, Resources, Company.
- Actions: Gym Login and Get Started/List Your Gym; app downloads belong in a clear product CTA rather than crowding the header.
- Move Privacy, Terms, and Account Deletion to Help/Legal in the footer.
- Use the Atlas master icon with an accessible text wordmark.

### Footer

Current issues:

- Generic four-column template structure does not explain or link to the full product.
- Three circular icon links have no accessible names and are not genuine social profiles.
- Inline styles and legacy icon fonts continue the theme split.
- No app-download badges, member/trainer product links, Help/FAQ, account deletion, or explicit platform-admin explanation.
- No address/company/trust information or structured organization details.

### Layout and CSS architecture

Current issues:

- A 584-line Blade layout owns presentation CSS and all global scripts.
- Shared public components exist (`card`, `cta-section`, `empty-state`, `status-badge`) but the main pages mostly duplicate markup and inline styles instead of using them.
- Public and authenticated styles share one Vite entry, allowing global dark-mode body rules and panel behavior to leak into public rendering.
- The layout contains a comment documenting a broken legacy animation path and fixes it with broad `!important` overrides.
- `overflow-x: hidden` can conceal rather than prevent mobile overflow.

## 5. Page-by-page audit

### 5.1 Home (`/`)

Strengths to preserve:

- Clear three-audience premise.
- Live platform statistics and featured-gym data are already supplied by the route.
- Existing links point into real discovery, gym, and trainer paths.

Issues:

- The hero is a generic Unsplash background plus a fabricated “command view”; it does not prove the real applications or admin panels.
- `100vh` hero/row heights and the 3.5–6.8rem title are high risk on small/short screens.
- Page uses extensive inline style values and repeated card markup.
- Audience coverage excludes a dedicated Platform Admin story and gives no meaningful Member App detail.
- Metrics have no data freshness or context; zero/low counts may weaken trust in early environments.
- Featured gym cards use CSS background images with no alternative text, responsive sizing, or loading control.
- The “Before Atlas,” audience cards, workflow, and metrics create many text cards but no product UI demonstration.
- There is no app-download CTA, ecosystem visual, testimonial/proof, FAQ, or role comparison.

Required outcome:

- Hero must pair a concise value proposition with real Member/Trainer phone screens and a real gym-admin browser frame.
- Explain the connected ecosystem in one visual workflow.
- Provide role-based entrances for Member, Trainer, Gym Operator, and Platform Admin.
- Keep live stats only where values are meaningful; supply neutral empty/early-network presentation.
- Add product proof, feature narratives, download paths, featured gyms, and FAQ.

### 5.2 Find Gyms (`/gyms`)

Strengths to preserve:

- Real search, city, pricing, facilities, distance/location, verification, promotion, trial, women-friendly, training, and open-now filters.
- Active-filter count/chips, reset, result count, cards, pagination, and empty state.

Issues:

- Approximately 970 lines with a large embedded design system and inline script.
- Negative 8.5rem panel overlap and 38rem hero height create responsive and zoom risks.
- Exact latitude/longitude fields are exposed as a normal advanced filter instead of a clear geolocation workflow.
- Hidden checkbox inputs with styled sibling pills require accessible checked/focus states and larger semantic grouping.
- Filter disclosure uses custom JavaScript but lacks an explicit accessible expanded-state audit.
- Cards use CSS backgrounds, hiding meaningful gym imagery from assistive technology and preventing responsive image optimization.
- Phone/Instagram values appear as visual pills rather than clearly actionable contact controls.
- Filter density is high on mobile; applying changes submits the whole page and can return users to the top without preserving focus/context.
- The page introduces local versions of `atlas-card`, button, input, pill, label, badge, and panel components that differ from gym detail and the backend.

Required outcome:

- Preserve every query parameter and server-side result behavior.
- Create an accessible mobile filter dialog/drawer and a persistent desktop filter region.
- Use native labelled controls, fieldsets/legends, visible checked/focus states, and a clear apply/reset summary.
- Replace CSS-background card images with optimized responsive `<img>`/`<picture>` where the image conveys gym identity.
- Return focus to results and announce result-count changes after filtering.
- Keep useful active chips and empty states within the unified public design system.

### 5.3 Gym profile (`/gyms/{slug}`)

Strengths to preserve:

- Real listing badges, opening hours, prices, facilities, address/maps, branches, gallery, trainers, contacts, and trial request.
- Visibility flags are respected by server-rendered content.

Issues:

- Approximately 916 lines and a separate embedded component system.
- 42rem hero with a 3.2–6.4rem title is oversized on mobile and browser zoom.
- Hero and gallery images are backgrounds; gallery anchors have no accessible names.
- Contact number is used in `tel:` whenever filled, while display logic elsewhere refers to contact visibility; this requires contract verification so hidden data is never linked publicly.
- Trial form labels are not visible; placeholders carry the field meaning.
- Error summary is not connected to fields and success/error feedback is not announced.
- Operating-hours rows, plans, branches, trainers, and contact data become a long single-page stack without a compact mobile information hierarchy.
- External Maps/Instagram behavior and accessible new-window descriptions are inconsistent.
- No gym-specific canonical, share card, or structured HealthClub/LocalBusiness data.

Required outcome:

- Preserve all server visibility and trial-request rules.
- Use a compact responsive hero with a real responsive cover image and clear summary/actions.
- Add section navigation or accessible accordions where mobile length warrants it.
- Give every gallery item a meaningful label/caption and keyboard-operable lightbox or plain image link.
- Add full labelled form semantics, per-field errors, status announcements, and mobile-friendly controls.
- Generate safe structured data only from intentionally public fields.

### 5.4 For Gyms (`/for-gyms`)

Strengths to preserve:

- Connects public discovery to operational workflows.
- Routes a real gym inquiry and provides the existing gym login.

Issues:

- Mostly repeated text cards; no authenticated Gym Admin screenshots.
- Claims cover only a subset of the available gym-admin functionality and do not show how a workflow operates.
- No distinction between available, limited/beta, and planned capabilities.
- `88vh` hero and large type need short-screen treatment.
- Form uses placeholders instead of labels and the status blocks use low-contrast Tailwind colors suited to a dark surface even though the section is light.
- Existing real backend screens and permissions are not shown.
- CTA text says “Register” while the form creates an inquiry, which can imply immediate account creation.

Required outcome:

- Build an evidence-backed Gym Management product page with real browser-framed screens and workflow-level explanations.
- Cover branches, staff, trainers, members/import, plans, fees, payments/dues, attendance, leads/trials, listing controls, reminders, reports, and permissions.
- Label feature status and make the CTA accurately describe an onboarding inquiry.

### 5.5 For Trainers (`/for-trainers`)

Strengths to preserve:

- Explains gym-linked trainer access and routes a real trainer inquiry.
- Introduces daily members, planning, diet, follow-ups, and notifications.

Issues:

- No Trainer App marketing or real-UI screenshots despite complete assets already existing.
- Feature tiles repeat generic copy and do not explain tasks, inputs, outcomes, or gym/member connections.
- Current and future functionality are mixed; “Future online coaching” can be interpreted as shipped.
- No store-download route, compatibility/eligibility explanation, or exact onboarding flow.
- Form labels, errors, and mobile form behavior have the same issues as For Gyms.

Required outcome:

- Use the Trainer Play Store promotional set for section intros and real UI set/App Store captures as proof.
- Explain dashboard, clients, workout builder/programming, diets, progress context, follow-ups, notifications, and communication step by step.
- Separate current, beta, and planned functions visibly.

### 5.6 Pricing (`/pricing`)

Issues:

- “Free onboarding initially,” “Free through gym initially,” and future premium lanes are strategy copy rather than a durable commercial contract.
- It does not state inclusions, exclusions, eligibility, taxes, support, limits, renewal behavior, or how to start.
- Pricing visual inherits legacy `block-7` template styles rather than backend parity.
- The page can become factually stale if production subscription plans change.
- There is no FAQ or contact path tied to pricing questions.

Required outcome:

- Source published pricing from a verified business/config contract or explicitly label the page as launch availability.
- Clearly separate current plans from roadmap ideas.
- List audience, included features, limits, and CTA for each published tier.
- Add pricing FAQ and structured data only when values are confirmed.

### 5.7 About (`/about`)

Issues:

- Explains the problem but provides little trust evidence, company identity, product history, operating principles, security posture, or support information.
- Relies on generic fitness imagery and repeated cards.
- “One platform, three connected audiences” conflicts with the need to explain Platform Admin as a fourth operational role.
- Generic `atlas-card`, `atlas-dark-panel`, `atlas-lead`, and `atlas-float` classes are not defined globally.

Required outcome:

- Explain mission, ecosystem roles, product principles, trust/safety, data boundaries, and the team/company identity that is approved for publication.
- Use a role/ecosystem graphic and real product media rather than another generic gym hero.

### 5.8 Contact (`/contact`)

Strengths to preserve:

- Real server-side inquiry routing and configurable support contact details.

Issues:

- Inputs and select have no persistent labels.
- Required/optional state, expected response window, and privacy usage are not explained.
- Generic alert/link/card classes are not defined globally.
- Error summary does not focus or link to fields; field-level messages are absent.
- No spam prevention, submission pending state, or double-submit affordance is visible in the frontend.
- “Usually routed by inquiry type” is vague operational language.

Required outcome:

- Use persistent labels, required markers, autocomplete attributes, per-field help/errors, live status, and a privacy notice.
- Retain all current inquiry types and redirect allow-list behavior.
- Provide clear support expectations only when operationally verified.

### 5.9 Privacy Policy (`/privacy-policy`)

Issues:

- A dense marketing-styled card is difficult to scan as a legal document.
- No last-updated/effective date, table of contents, controller/contact identity, retention schedule detail, rights/request process by jurisdiction, or versioning.
- The page links to a configured external policy when present, creating two possible sources of truth.
- Headings do not break the policy into semantic sections.
- Generic shared classes used here are undefined.

Required outcome:

- Legal owner must establish the canonical policy source.
- Render semantic sections with contents navigation, effective date, contact/request routes, and readable line length.
- Keep account deletion prominent and accessible.

### 5.10 Terms (`/terms`)

Issues:

- Dense paragraphs mix public website terms and authenticated app/chat rules.
- Missing effective date, definitions, eligibility, service scope, account obligations, billing terms where relevant, liability/disclaimer, termination, governing law, disputes, changes, and contact structure.
- Configured external terms can create a second source of truth.
- No semantic contents navigation or section headings.

Required outcome:

- Legal owner must establish the canonical terms source and approved scope.
- Use semantic, linkable sections with an effective date and readable line length.
- Separate website discovery/trial terms from app/account/chat terms where legally appropriate.

### 5.11 Account deletion (`/account-deletion`)

Strengths to preserve:

- Public deletion entry point exists and supports member/trainer context.
- Explains verification and limited lawful retention at a high level.

Issues:

- Every visible field lacks a label and ID.
- The form is a generic support contact submission with a prefilled message, not an explicit deletion-request model/workflow in the frontend.
- No required-field explanation, identity-verification timeline, deletion timeline, status tracking, cancellation path, or app-specific instructions.
- Errors are not associated with fields.
- No protection against a user accidentally changing/removing the legal request text without understanding the impact.
- Generic shared style classes are undefined.

Required outcome:

- Preserve the approved backend request path until a dedicated workflow is authorized.
- Clearly identify Member vs Trainer, label every field, explain verification and expected next steps, and make the destructive nature explicit without coercive styling.
- Add per-field errors and accessible success/error status.

## 6. Visual asset audit and website use plan

Existing approved source material:

- Brand marks: `play_store_assets/branding/atlas-master-icon.png`, `atlas-play-store-icon.png`.
- Member promotional screens: `play_store_assets/member/screenshots/*.png`.
- Member real UI: `play_store_assets/member/screenshots-real-ui/*.png`.
- Member feature graphic: `play_store_assets/member/feature-graphic-1024x500.png`.
- Trainer promotional screens: `play_store_assets/trainer/screenshots/*.png`.
- Trainer real UI: `play_store_assets/trainer/screenshots-real-ui/*.png`.
- Trainer feature graphic: `play_store_assets/trainer/feature-graphic-1024x500.png`.
- Higher-resolution real UI captures: `app_store_assets/member/screenshots-6.5`, `screenshots-6.9`, and corresponding Trainer directories.

Rules for website use:

1. Audit each image against the current app before publishing it as a real capability.
2. Treat promotional compositions as marketing illustration when they contain conceptual copy or data; do not present them as literal app UI.
3. Use real UI captures to prove functionality.
4. Capture current Gym Admin and Platform Admin screens from seeded/sanitized accounts; remove names, emails, phone numbers, financial identifiers, tokens, and real member data.
5. Create website-specific compositions rather than inserting 1080x1920 store assets at full size:
   - home hero Member/Trainer device montage;
   - Member feature bands;
   - Trainer feature bands;
   - Gym Admin browser-frame walkthroughs;
   - Platform Admin browser-frame walkthroughs;
   - connected ecosystem diagram;
   - responsive mobile crops;
   - social sharing image.
6. Export local AVIF/WebP plus PNG fallback where necessary, with explicit dimensions and responsive `srcset`/`sizes`.
7. Use AI-generated imagery only for supporting lifestyle scenes, textures, abstract backgrounds, and editorial transitions. Never generate a fake product interface and label it as real.
8. Every informative image requires useful alternative text; decorative compositions use empty alt text and presentation semantics.
9. Do not copy Play Store text into the website without validating that the feature is current and correctly scoped.

## 7. Proposed information architecture

| Navigation group | Pages/content |
| --- | --- |
| Product | Product Overview, Member App, Trainer App, Gym Management, Platform Administration, How Atlas Works |
| Discover | Find Gyms, Gym Profile |
| Pricing | Current verified availability and pricing FAQ |
| Resources | Feature guides, FAQ/Help, app downloads |
| Company | About, Contact |
| Legal (footer) | Privacy, Terms, Account Deletion |
| Account actions | Gym Login, Get Started/List Your Gym |

New route names and URLs must be decided before implementation, then covered by route/render tests. Existing public URLs must remain stable.

## 8. Responsive contract

Design and verify at minimum:

- 360x800: smallest supported phone baseline;
- 390x844: common modern phone;
- 430x932: large phone;
- 768x1024: tablet portrait;
- 1024x768: tablet landscape/small laptop;
- 1280x800: standard laptop;
- 1440x900: desktop;
- 1920x1080: wide desktop;
- 200% browser zoom at 1280 CSS pixels;
- mobile landscape at 667x375.

Site-wide responsive rules:

- No horizontal page overflow; do not use `overflow-x: hidden` as the fix.
- Minimum interactive target is 44x44 CSS pixels unless an equivalent spaced target passes WCAG 2.2.
- Body copy remains at least 16px on mobile with comfortable line height.
- Hero copy and primary CTA remain visible on short screens without requiring viewport-locked empty space.
- All forms become one column on phone widths.
- Phone/device screenshot compositions remain readable or switch to an intentional single-screen crop.
- Tables/data-heavy admin explanations use cards, scroll regions with clear affordance, or progressive disclosure.
- Mobile navigation is scroll-safe, keyboard operable, closes with Escape, returns focus, and prevents background interaction.
- Sticky controls do not obscure content, keyboard focus, or validation messages.

## 9. Accessibility acceptance criteria

- WCAG 2.2 AA target for all public pages.
- One clear `h1` per page; headings follow a logical hierarchy.
- Skip link moves focus to a labelled main region.
- All interactions work with keyboard only and have visible `:focus-visible` treatment.
- Mobile/menu/dialog focus is managed and restored.
- All inputs have persistent `<label>` elements, required/optional state, appropriate `autocomplete`, and accessible descriptions.
- Error summary receives focus after failed submission, links to each invalid field, and fields expose `aria-invalid` and the error via `aria-describedby`.
- Success, errors, and dynamic result counts use appropriate live-region semantics without duplicate announcements.
- Custom filter controls expose name, role, checked state, disabled state, and focus visibly.
- Informative images have contextual alt text; decorative images have `alt=""`.
- Lightbox/gallery controls have accessible names, keyboard navigation, Escape close, and focus return.
- Text and non-text contrast pass AA in normal, hover, focus, active, error, disabled, and dark-on-image states.
- Motion honors `prefers-reduced-motion`; content never depends on animation to become visible.
- Content remains usable at 200% and 400% zoom/reflow.
- External/new-window links are announced or avoided where unnecessary.

## 10. SEO acceptance criteria

- Unique, accurate title and meta description for every route and gym profile.
- Self-referencing canonical URL on indexable pages; filtered-query canonical strategy documented.
- Open Graph and Twitter card metadata with local, optimized sharing artwork.
- Organization, WebSite, and SoftwareApplication structured data validated against actual product/store information.
- Gym profile structured data uses only public, accurate data and valid schema types.
- BreadcrumbList and FAQ structured data match visible content exactly.
- XML sitemap contains all intended public pages and eligible gym profiles; robots file links to it.
- Non-indexable/filter/duplicate states have an explicit robots/canonical policy.
- Semantic breadcrumbs and internal links connect product, audience, discovery, help, and legal pages.
- Image filenames, alt text, dimensions, and loading priority are appropriate.
- No marketing claim, price, store link, rating, count, or availability is published without a current source.

## 11. Performance acceptance criteria

- Separate public CSS/JS entry points from authenticated panel code.
- Remove Yogalax dependencies page by page; final public pages do not ship unused carousel, datepicker, timepicker, parallax, animation, jQuery migration, or multiple icon-font bundles.
- Outfit loads once. Prefer a privacy/performance-approved local font strategy or one optimized hosted request.
- No runtime Unsplash dependency in the completed site.
- LCP media is local, responsive, dimensioned, appropriately compressed, and preloaded only when justified.
- Below-the-fold images lazy-load; hero/LCP images do not.
- Page-local `<style>` and page-local inline scripts are eliminated from gym index/detail.
- Reusable Blade components cover buttons, cards, headings, feature sections, screenshot frames, alerts, forms, badges, filters, CTA, and empty states.
- Production Vite build succeeds with no missing assets.
- Lighthouse mobile targets on representative pages: Performance >= 85 initially and >= 90 after optimization; Accessibility >= 95; Best Practices >= 95; SEO >= 95.
- Core Web Vitals target at the 75th percentile where field data is available: LCP <= 2.5s, INP <= 200ms, CLS <= 0.1.
- A bundle/asset inventory records every remaining global dependency and why it is required.

## 12. Content accuracy and product-proof acceptance criteria

- A feature matrix maps every website claim to a real route, permission, app screen, service, or approved roadmap item.
- Each claim is tagged internally as Available, Limited/Beta, or Planned; planned features are visibly identified on the website.
- Member, Trainer, Gym Admin, and Platform Admin each have a complete audience page.
- Each major feature explanation answers: who uses it, what problem it solves, how the workflow works, what the outcome is, and how it connects to another role.
- Real screenshots use sanitized data and correspond to the current shipped UI.
- Marketing compositions are never represented as literal real UI.
- Pricing and legal copy receive business/legal approval before publication.
- Store download buttons use verified live URLs; absent URLs are not replaced with dead or placeholder links.

## 13. Functional regression acceptance criteria

- All existing named public routes continue to resolve.
- Gym search preserves every supported query parameter and pagination state.
- Gym visibility, price visibility, contact visibility, trial availability, branches, plans, trainers, facilities, photos, and opening-hour behavior remain server-authoritative.
- Trial request validation, persistence, redirect, flash messaging, and gym association remain unchanged unless separately approved.
- Contact inquiry types, validation, persistence, redirect allow-list, and flash messaging remain unchanged unless separately approved.
- Account-deletion requests continue through the approved backend path.
- Gym and admin login links continue to use named routes.
- Focus/error behavior is added without changing request contracts.
- Focused Laravel feature tests cover public rendering, filters, trial submissions, contact submissions, legal settings, and deletion-page context.

## 14. Definition of done for the audit-to-build handoff

Implementation can begin when:

1. the current route and feature matrix is approved;
2. current/beta/planned labels are agreed;
3. the backend panel tokens to reuse are documented;
4. the target public information architecture and new routes are approved;
5. current desktop/mobile baseline screenshots are captured;
6. Member/Trainer store assets are classified as real UI or promotional;
7. sanitized Gym Admin and Platform Admin capture accounts/data are ready;
8. legal and pricing sources of truth are identified.

The rebuild is complete only when all shared, page-level, responsive, accessibility, SEO, performance, content-proof, and functional-regression acceptance criteria above pass and every public route has been visually reviewed at the viewport matrix.
