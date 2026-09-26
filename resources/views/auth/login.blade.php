<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>Login — 2D MIS</title>
    {{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 6): standalone public
         head pulls the same built stylesheet the authenticated shell uses
         (@vite) plus the ui.css shared layer, so the button / card / label
         vocabulary and the remaining Bootstrap-parity utilities are available
         without converting this view to the layout. Phase 27: the Bootstrap
         CDN stylesheet link was removed; ui.css §4.8–4.10 owns the leftovers.
         The POST login.attempt contract and session-status messaging are
         unchanged. --}}
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background-color: var(--color-bg);
        }
        .logo {
            display: block;
            margin: 0 auto 1rem auto;
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
    </style>
</head>
<body>

<div class="flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="w-full max-w-[420px]">
        <div class="data-card !p-[1.75rem]">
            <div class="mb-4 text-center">
                <img src="{{ asset('seal_logo.png') }}" alt="Logo" class="logo">
                <h1 class="mb-1 text-xl font-bold text-ink">2D MIS</h1>
                <p class="mb-0 text-dense text-ink-muted">Welcome</p>
            </div>

            @if (session('login_status') === 'expired')
                <div class="ui-notice mb-3">Session expired. Please login again.</div>
            @endif

            @if (session('login_status') === 'forced')
                <div class="ui-notice mb-3">You have been logged out by the system.</div>
            @endif

            @if ($errors->has('username'))
                <div class="mb-3 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[12px_16px] text-center text-dense text-ink">{{ $errors->first('username') }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="flex flex-col gap-3">
                @csrf
                <div>
                    <label class="field-label" for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required autofocus>
                </div>

                <div>
                    <label class="field-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>

                <button type="submit" class="btn-navy w-full">Login</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
