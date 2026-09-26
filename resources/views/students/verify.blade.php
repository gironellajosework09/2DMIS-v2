<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Identity — 2D MIS</title>
    {{-- Batch G migration: standalone public head shares the built app
         stylesheet + ui.css (see auth/login). Birthdate + mobile POST
         verification contract is unchanged. Phase 27: the Bootstrap CSS CDN
         link is removed; ui.css §4.8–4.10 owns the shared families. --}}
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background-color: var(--color-bg);
        }
    </style>
</head>
<body>
    <div class="mx-auto mt-5 max-w-[520px] px-3">
        <div class="data-card !p-[1.75rem]">
            <h1 class="mb-3 text-center text-lg font-semibold text-ink">Verify Your Identity</h1>

            <p class="text-center font-bold text-ink">
                {{ $client->lastname }}, {{ $client->firstname }}
            </p>

            @if ($errors->has('verification'))
                <div class="mb-3 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[12px_16px] text-dense text-ink">{{ $errors->first('verification') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-3 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[12px_16px] text-dense text-ink">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('student.verify.post', $client) }}" class="flex flex-col gap-3">
                @csrf
                <div>
                    <label for="birthdate" class="field-label">Birthdate</label>
                    <input type="date" name="birthdate" id="birthdate" class="form-control" required>
                </div>
                <div>
                    <label for="mobile" class="field-label">Mobile Number</label>
                    <input type="text" name="mobile" id="mobile" class="form-control" required>
                </div>
                <button class="btn-navy w-full">Verify</button>
            </form>
        </div>
    </div>
</body>
</html>
