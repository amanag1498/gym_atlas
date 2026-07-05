<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($pageTitle ?? config('app.name')) . ' | ' . config('app.name') }}</title>
    <meta name="description" content="{{ $pageDescription ?? 'Discover premium gyms, modern fitness operations, and coaching experiences built for members, gyms, and trainers.' }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/open-iconic-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/owl.carousel.min.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/owl.theme.default.min.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/aos.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/ionicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/bootstrap-datepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/jquery.timepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/flaticon.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/icomoon.css') }}">
    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/style.css') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --atlas-brand: #2563eb;
            --atlas-brand-soft: #bfdbfe;
            --atlas-dark: #0f172a;
            --atlas-muted: #64748b;
            --atlas-border: rgba(148, 163, 184, 0.18);
            --atlas-bg: #f8fbff;
        }

        body.public-site {
            font-family: "Outfit", sans-serif;
            color: var(--atlas-dark);
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 22rem),
                radial-gradient(circle at top right, rgba(56, 189, 248, 0.10), transparent 22rem),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 52%, #ffffff 100%);
        }

        .public-shell {
            min-height: 100vh;
            overflow-x: hidden;
        }

        .public-main {
            position: relative;
            z-index: 1;
        }

        /*
        IMPORTANT FIX:
        Yogalax hides .ftco-animate elements until JS reveals them.
        In Laravel/Vite setups this sometimes fails, so pages look empty.
        */
        .ftco-animate,
        .ftco-animated,
        .public-reveal,
        .aos-init,
        .aos-animate {
            opacity: 1 !important;
            visibility: visible !important;
        }

        .ftco-animate,
        .public-reveal {
            transform: none !important;
        }

        .ftco-navbar-light {
            background: transparent !important;
            border-bottom: 1px solid transparent;
            backdrop-filter: none;
        }

        .ftco-navbar-light.scrolled {
            background: rgba(255, 255, 255, 0.88) !important;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            backdrop-filter: blur(18px);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
        }

        .ftco-navbar-light .navbar-brand,
        .ftco-navbar-light .navbar-nav > .nav-item > .nav-link {
            color: #f8fbff !important;
            font-family: "Outfit", sans-serif;
        }

        .ftco-navbar-light.scrolled .navbar-brand,
        .ftco-navbar-light.scrolled .navbar-nav > .nav-item > .nav-link {
            color: #0f172a !important;
        }

        .ftco-navbar-light .navbar-nav > .nav-item.active > .nav-link,
        .ftco-navbar-light .navbar-nav > .nav-item > .nav-link:hover {
            color: #93c5fd !important;
        }

        .ftco-navbar-light.scrolled .navbar-nav > .nav-item.active > .nav-link,
        .ftco-navbar-light.scrolled .navbar-nav > .nav-item > .nav-link:hover {
            color: #2563eb !important;
        }

        .ftco-navbar-light .navbar-brand {
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .ftco-navbar-light .navbar-nav > .nav-item > .nav-link {
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            padding-left: 1rem;
            padding-right: 1rem;
            transition: all 180ms ease;
        }

        .public-nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: 1.25rem;
        }

        .public-nav-actions-desktop {
            margin-left: auto;
        }

        .public-nav-actions-mobile {
            display: none;
        }

        .public-nav-action-primary,
        .public-nav-action-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.65rem;
            padding: 0 1.15rem;
            border-radius: 9999px;
            font-size: 0.84rem;
            font-weight: 700;
            transition: all 180ms ease;
        }

        .public-nav-action-primary {
            background: linear-gradient(135deg, #f8fbff, #bfdbfe);
            color: #0f172a;
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.22);
        }

        .ftco-navbar-light.scrolled .public-nav-action-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
        }

        .public-nav-action-secondary {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .ftco-navbar-light.scrolled .public-nav-action-secondary {
            border-color: rgba(37, 99, 235, 0.18);
            background: rgba(37, 99, 235, 0.06);
            color: #1d4ed8;
        }

        .public-nav-action-primary:hover,
        .public-nav-action-secondary:hover {
            transform: translateY(-1px);
            text-decoration: none;
        }

        .public-brand-mark {
            display: inline-flex;
            width: 0.65rem;
            height: 0.65rem;
            margin-right: 0.5rem;
            border-radius: 9999px;
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            box-shadow: 0 0 18px rgba(37, 99, 235, 0.45);
        }

        .hero-wrap {
            position: relative;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .hero-wrap::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(110deg, rgba(10, 17, 32, 0.72) 0%, rgba(15, 23, 42, 0.46) 48%, rgba(37, 99, 235, 0.18) 100%);
            z-index: 0;
            pointer-events: none;
        }

        .hero-wrap .overlay {
            background: transparent !important;
            opacity: 1 !important;
        }

        .hero-wrap .container {
            position: relative;
            z-index: 2;
        }

        .hero-wrap h1,
        .hero-wrap h2,
        .hero-wrap h3,
        .hero-wrap .bread,
        .hero-wrap .text-white {
            color: #ffffff !important;
        }

        .hero-wrap p,
        .hero-wrap .breadcrumbs,
        .hero-wrap .breadcrumbs a,
        .hero-wrap .breadcrumbs span {
            color: rgba(255, 255, 255, 0.82) !important;
        }

        .heading-section .subheading,
        .subheading {
            color: #2563eb !important;
            font-family: "Outfit", sans-serif;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .heading-section h2 {
            color: #0f172a;
            font-family: "Outfit", sans-serif;
            font-weight: 800;
            letter-spacing: -0.035em;
        }

        .ftco-section,
        .contact-section {
            position: relative;
        }

        .bg-light,
        .ftco-section-services.bg-light {
            background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%) !important;
        }

        body.public-site .ftco-section p,
        body.public-site .ftco-section li,
        body.public-site .contact-section p {
            color: #475569;
        }

        .public-card,
        .public-surface,
        .coach .text,
        .services,
        .block-7,
        .package-program .text,
        .contact-info.bg-light {
            background: rgba(255, 255, 255, 0.94) !important;
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 1.45rem;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.07);
            color: #334155 !important;
        }

        .public-card {
            transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
        }

        .public-card:hover,
        .coach .text:hover,
        .services:hover,
        .block-7:hover {
            transform: translateY(-3px);
            border-color: rgba(37, 99, 235, 0.22);
            box-shadow: 0 26px 62px rgba(15, 23, 42, 0.11);
        }

        .coach .text h3,
        .services h3,
        .block-7 h3,
        .package-program .text h3,
        .contact-info h2,
        .contact-info h3 {
            color: #0f172a !important;
            font-family: "Outfit", sans-serif;
            font-weight: 800;
        }

        .public-story-panel {
            position: relative;
            overflow: hidden;
            border-radius: 1.6rem;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 22rem),
                linear-gradient(135deg, #0f172a 0%, #12264a 48%, #0b1530 100%) !important;
            border: 1px solid rgba(191, 219, 254, 0.14);
            box-shadow: 0 26px 72px rgba(15, 23, 42, 0.18);
            color: rgba(255, 255, 255, 0.78) !important;
        }

        .public-story-panel h1,
        .public-story-panel h2,
        .public-story-panel h3,
        .public-story-panel h4,
        .public-story-panel .text-white {
            color: #ffffff !important;
        }

        .public-story-panel p,
        .public-story-panel li {
            color: rgba(255, 255, 255, 0.76) !important;
        }

        .public-kicker,
        .public-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .public-kicker {
            color: #64748b;
        }

        .hero-wrap .public-kicker,
        .public-story-panel .public-kicker {
            color: #bfdbfe !important;
        }

        .public-eyebrow {
            border-radius: 9999px;
            border: 1px solid rgba(191, 219, 254, 0.24);
            background: rgba(37, 99, 235, 0.14);
            color: #dbeafe;
            padding: 0.48rem 0.82rem;
        }

        .public-eyebrow::before,
        .public-pill::before {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 9999px;
            background: linear-gradient(135deg, #2b6df8, #22d3ee);
        }

        .public-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(255, 255, 255, 0.86);
            color: #334155;
            padding: 0.42rem 0.72rem;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .public-story-panel .public-pill {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
        }

        .btn.btn-primary,
        .block-7 .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            border: 1px solid #2563eb !important;
            color: #ffffff !important;
            border-radius: 9999px !important;
            box-shadow: 0 14px 34px rgba(37, 99, 235, 0.20) !important;
            font-family: "Outfit", sans-serif;
            font-weight: 700;
            transition: all 180ms ease;
        }

        .btn.btn-primary:hover,
        .block-7 .btn-primary:hover {
            background: #ffffff !important;
            color: #1d4ed8 !important;
            border-color: #bfdbfe !important;
            transform: translateY(-2px);
        }

        .btn.btn-white.btn-outline-white {
            border-radius: 9999px !important;
            border-color: rgba(255, 255, 255, 0.36) !important;
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(12px);
            font-family: "Outfit", sans-serif;
            font-weight: 700;
            transition: all 180ms ease;
        }

        .btn.btn-white.btn-outline-white:hover {
            background: #ffffff !important;
            color: #1d4ed8 !important;
            border-color: #ffffff !important;
            transform: translateY(-2px);
        }

        .form-control {
            min-height: 3.25rem;
            border-radius: 1rem !important;
            border: 1px solid rgba(148, 163, 184, 0.22) !important;
            background: rgba(255, 255, 255, 0.96) !important;
            color: #0f172a !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            font-family: "Outfit", sans-serif;
        }

        textarea.form-control {
            min-height: 8rem;
        }

        .form-control::placeholder {
            color: #94a3b8 !important;
        }

        .form-control:focus {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: rgba(37, 99, 235, 0.42) !important;
            box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.12) !important;
        }

        select.form-control option {
            color: #0f172a !important;
            background: #ffffff !important;
        }

        .public-story-panel .form-control {
            border-color: rgba(255, 255, 255, 0.12) !important;
            background: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }

        .public-story-panel .form-control::placeholder {
            color: #a8b8d8 !important;
        }

        .public-story-panel .form-control:focus {
            background: rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
            border-color: rgba(191, 219, 254, 0.42) !important;
        }

        .services .icon {
            background: rgba(37, 99, 235, 0.08);
        }

        .services .icon span,
        .block-7 .price sup,
        .block-7 .price .number,
        .ftco-social a span,
        .ftco-counter .icon span,
        .blog-entry span.day,
        .block-27 ul li a,
        .block-27 ul li span {
            color: #2563eb !important;
        }

        .package-program:after {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.2), rgba(37, 99, 235, 0.78)) !important;
        }

        .block-7 {
            overflow: hidden;
        }

        .hero-wrap .slider-text .typewrite > .wrap {
            border-right: 2px solid rgba(255, 255, 255, 0.72);
        }

        .hero-wrap .slider-text .typewrite {
            display: block;
            min-height: 1.15em;
        }

        #ftco-loader {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }

        @media (max-width: 991.98px) {
            .ftco-navbar-light {
                background: rgba(15, 23, 42, 0.92) !important;
                backdrop-filter: blur(18px);
            }

            .ftco-navbar-light .navbar-collapse {
                margin-top: 1rem;
                padding: 1rem 0 0.35rem;
                border-top: 1px solid rgba(255, 255, 255, 0.10);
            }

            .public-nav-actions-desktop {
                display: none;
            }

            .public-nav-actions-mobile {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0.65rem;
                margin: 0.75rem 0 0;
            }

            .public-nav-action-primary,
            .public-nav-action-secondary {
                width: 100%;
            }

            .ftco-navbar-light .navbar-nav > .nav-item > .nav-link,
            .ftco-navbar-light.scrolled .navbar-nav > .nav-item > .nav-link {
                color: #ffffff !important;
                padding-left: 0;
                padding-right: 0;
            }
        }
    </style>
</head>

<body class="public-site">
    <div class="public-shell">
        <x-public.navbar />

        <main class="public-main">
            {{ $slot }}
        </main>

        <x-public.footer />
    </div>

    <div id="ftco-loader" class="show fullscreen">
        <svg class="circular" width="48px" height="48px">
            <circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee"></circle>
            <circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#2b6df8"></circle>
        </svg>
    </div>

    <script src="{{ asset('yogalax/assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery-migrate-3.0.1.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery.easing.1.3.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery.waypoints.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery.stellar.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/aos.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery.animateNumber.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/bootstrap-datepicker.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/jquery.timepicker.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/scrollax.min.js') }}"></script>
    <script src="{{ asset('yogalax/assets/js/main.js') }}"></script>
</body>
</html>