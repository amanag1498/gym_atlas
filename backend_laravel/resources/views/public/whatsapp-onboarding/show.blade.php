<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Connect WhatsApp Business | Gym Atlas</title>
    <style>
        body{margin:0;font-family:Inter,system-ui,sans-serif;background:#f4f7fb;color:#172033;display:grid;min-height:100vh;place-items:center}.card{width:min(520px,calc(100% - 40px));background:#fff;border-radius:24px;padding:32px;box-shadow:0 24px 70px #243b6b22}.badge{display:inline-block;background:#e8fff3;color:#087a45;border-radius:99px;padding:7px 12px;font-weight:700}.button{width:100%;border:0;border-radius:14px;padding:15px;background:#1769e0;color:#fff;font-weight:800;font-size:16px;cursor:pointer}.button:disabled{opacity:.55}.muted{color:#637083;line-height:1.55}.error{color:#b42318}.scope{font-weight:800}</style>
</head>
<body>
<main class="card">
    <span class="badge">Secure Meta connection</span>
    <h1>Connect WhatsApp Business</h1>
    <p class="muted">You are connecting WhatsApp for <span class="scope">{{ $session->gym?->name ?? 'Gym Atlas Platform' }}</span>. Atlas receives a revocable access token from Meta; you never paste an API key.</p>
    @if(! $configuration['ready'])
        <p class="error">Meta Embedded Signup is not configured in this environment. Ask the platform administrator to complete the production Meta settings.</p>
    @endif
    <p id="status" class="muted">Choose the WhatsApp Business account and phone number you want Atlas to use.</p>
    <button id="connect" class="button" type="button" @disabled(! $configuration['ready'])>Continue with Meta</button>
    <form id="complete" method="post" action="{{ route('whatsapp.onboarding.complete', ['token' => $token]) }}" hidden>
        @csrf
        <input id="code" name="code"><input id="waba_id" name="waba_id"><input id="phone_number_id" name="phone_number_id">
    </form>
</main>
<script>
let signupCode = null, signupData = null;
const statusNode = document.getElementById('status');
function submitWhenReady() {
    if (!signupCode || !signupData?.waba_id || !signupData?.phone_number_id) return;
    document.getElementById('code').value = signupCode;
    document.getElementById('waba_id').value = signupData.waba_id;
    document.getElementById('phone_number_id').value = signupData.phone_number_id;
    document.getElementById('complete').submit();
}
window.addEventListener('message', (event) => {
    if (!['https://www.facebook.com', 'https://web.facebook.com'].includes(event.origin)) return;
    let payload;
    try { payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data; } catch (_) { return; }
    if (payload?.type === 'WA_EMBEDDED_SIGNUP' && payload.event === 'FINISH') {
        signupData = payload.data;
        statusNode.textContent = 'Account selected. Completing secure connection...';
        submitWhenReady();
    }
});
window.fbAsyncInit = () => FB.init({appId: @json($configuration['app_id']), autoLogAppEvents: true, xfbml: true, version: @json($configuration['graph_version'])});
document.getElementById('connect').addEventListener('click', () => {
    if (typeof FB === 'undefined') { statusNode.textContent = 'Meta signup is still loading. Please try again in a moment.'; return; }
    statusNode.textContent = 'Waiting for Meta Embedded Signup...';
    FB.login((response) => {
        signupCode = response?.authResponse?.code ?? null;
        if (!signupCode) statusNode.textContent = 'Meta signup was cancelled or did not return a code.';
        submitWhenReady();
    }, {config_id: @json($configuration['configuration_id']), response_type: 'code', override_default_response_type: true, extras: {setup: {}}});
});
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
</body>
</html>
