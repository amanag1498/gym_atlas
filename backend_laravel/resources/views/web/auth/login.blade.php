<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --atlas-blue: #2563eb;
            --atlas-blue-dark: #1d4ed8;
            --atlas-ink: #0f172a;
            --atlas-muted: #64748b;
            --atlas-border: rgba(148, 163, 184, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Outfit", sans-serif;
            color: var(--atlas-ink);
            background:
                radial-gradient(circle at 20% 12%, rgba(37, 99, 235, 0.16), transparent 27rem),
                radial-gradient(circle at 82% 16%, rgba(56, 189, 248, 0.14), transparent 25rem),
                linear-gradient(135deg, #f8fbff 0%, #eef5ff 50%, #ffffff 100%);
        }

        .atlas-login-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }

        .atlas-login-card {
            width: min(430px, 100%);
            position: relative;
            overflow: hidden;
            border-radius: 2rem;
            border: 1px solid var(--atlas-border);
            background: rgba(255, 255, 255, 0.82);
            box-shadow:
                0 35px 90px rgba(15, 23, 42, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(22px);
            padding: 2.2rem;
        }

        .atlas-login-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 16rem),
                linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(248, 251, 255, 0.48));
            pointer-events: none;
        }

        .atlas-login-inner {
            position: relative;
            z-index: 2;
        }

        .atlas-logo {
            width: 3.4rem;
            height: 3.4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 1.15rem;
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 800;
            box-shadow: 0 20px 45px rgba(37, 99, 235, 0.28);
        }

        .atlas-kicker {
            margin-top: 1.6rem;
            margin-bottom: 0;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .atlas-title {
            margin: 0.65rem 0 0;
            color: #0f172a;
            font-size: 2.35rem;
            line-height: 0.98;
            font-weight: 800;
            letter-spacing: -0.055em;
        }

        .atlas-panel {
            margin-top: 0.75rem;
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .atlas-google-button {
            width: 100%;
            min-height: 3.5rem;
            margin-top: 2rem;
            border: 0;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            font-family: "Outfit", sans-serif;
            font-size: 0.96rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 20px 48px rgba(37, 99, 235, 0.28);
            transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
        }

        .atlas-google-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 26px 64px rgba(37, 99, 235, 0.35);
        }

        .atlas-google-button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .atlas-google-icon {
            width: 1.35rem;
            height: 1.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: #ffffff;
            color: #2563eb;
            font-size: 0.82rem;
            font-weight: 900;
        }

        .atlas-alert-error,
        .atlas-alert-warning {
            margin-top: 1rem;
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            font-size: 0.86rem;
            line-height: 1.55;
        }

        .atlas-alert-error {
            border: 1px solid rgba(244, 63, 94, 0.18);
            background: rgba(255, 241, 242, 0.95);
            color: #be123c;
        }

        .atlas-alert-warning {
            border: 1px solid rgba(245, 158, 11, 0.22);
            background: rgba(255, 251, 235, 0.95);
            color: #92400e;
        }

        .hidden {
            display: none !important;
        }

        @media (max-width: 520px) {
            .atlas-login-shell {
                padding: 1rem;
            }

            .atlas-login-card {
                border-radius: 1.55rem;
                padding: 1.6rem;
            }

            .atlas-title {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
<div class="atlas-login-shell">
    <main class="atlas-login-card">
        <div class="atlas-login-inner">
            <div class="atlas-logo">A</div>

            <p class="atlas-kicker">
                {{ $panel === 'admin' ? 'Admin Panel' : 'Gym Panel' }}
            </p>

            <h1 class="atlas-title">
                {{ $title }}
            </h1>

            <p class="atlas-panel">
                Continue securely with Google.
            </p>

            @include('web.partials.flash')

            <button
                type="button"
                class="atlas-google-button"
                id="firebase-google-login-button"
                @if (! $firebaseConfig) disabled @endif
            >
                <span class="atlas-google-icon">G</span>
                Continue with Google
            </button>

            <p id="firebase-google-login-error" class="atlas-alert-error hidden"></p>

            @if (! $firebaseConfig)
                <p class="atlas-alert-warning">
                    Firebase web login is not configured yet.
                </p>
            @endif

            <form
                method="POST"
                action="{{ $panel === 'admin' ? route('web.admin.login.firebase') : route('web.gym.login.firebase') }}"
                id="firebase-google-login-form"
                class="hidden"
            >
                @csrf
                <input type="hidden" name="id_token" id="firebase-google-id-token">
                <input type="hidden" name="remember" value="1">
            </form>
        </div>
    </main>
</div>

@if ($firebaseConfig)
    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
        import { getAuth, GoogleAuthProvider, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';

        const firebaseConfig = @json($firebaseConfig);
        const loginButton = document.getElementById('firebase-google-login-button');
        const loginForm = document.getElementById('firebase-google-login-form');
        const tokenInput = document.getElementById('firebase-google-id-token');
        const errorBox = document.getElementById('firebase-google-login-error');

        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const provider = new GoogleAuthProvider();

        const resolveGoogleErrorMessage = (error) => {
            switch (error?.code) {
                case 'auth/popup-blocked':
                    return 'The Google sign-in popup was blocked by your browser.';
                case 'auth/popup-closed-by-user':
                    return 'The Google sign-in popup was closed before login completed.';
                case 'auth/cancelled-popup-request':
                    return 'A Google sign-in popup is already open.';
                case 'auth/unauthorized-domain':
                    return 'This website domain is not authorized in Firebase Authentication.';
                case 'auth/network-request-failed':
                    return 'Google sign-in could not reach Firebase.';
                default:
                    return error?.message || 'Google sign-in failed. Please try again.';
            }
        };

        loginButton?.addEventListener('click', async () => {
            loginButton.disabled = true;
            loginButton.innerHTML = '<span class="atlas-google-icon">G</span> Connecting...';
            errorBox.classList.add('hidden');
            errorBox.textContent = '';

            try {
                const result = await signInWithPopup(auth, provider);
                const idToken = await result.user.getIdToken(true);

                if (!idToken) {
                    throw new Error('Firebase did not return an ID token.');
                }

                tokenInput.value = idToken;
                loginForm.submit();
            } catch (error) {
                errorBox.textContent = resolveGoogleErrorMessage(error);
                errorBox.classList.remove('hidden');
                loginButton.disabled = false;
                loginButton.innerHTML = '<span class="atlas-google-icon">G</span> Continue with Google';
            }
        });
    </script>
@endif
</body>
</html>