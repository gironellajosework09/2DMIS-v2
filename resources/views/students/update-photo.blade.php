<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile Photo Update — 2D MIS</title>
    {{-- Batch G migration: standalone public head shares the built app
         stylesheet + ui.css (see auth/login). GET search + verify redirect
         contract unchanged. Phase 27: the Bootstrap CSS CDN link is removed;
         ui.css §4.8–4.10 owns the list-group + utility classes here. --}}
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
    <div class="mx-auto mt-5 max-w-[560px] px-3">
        <div class="data-card !p-[1.75rem]">
            <h1 class="mb-3 text-center text-lg font-semibold text-ink">Search Your Name</h1>

            <form method="GET" class="input-group mb-3">
                <input type="text" name="search" class="form-control" aria-label="Search your name"
                    placeholder="Enter your name..." value="{{ $search }}" required>
                <button class="btn-navy">Search</button>
            </form>

            @if (count($results) > 0)
                <ul class="list-group list-group-flush">
                    @foreach ($results as $row)
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                            {{ $row->lastname }}, {{ $row->firstname }} {{ $row->middlename }}
                            <a href="{{ route('student.verify', $row->id) }}" class="btn-gold no-underline">Select</a>
                        </li>
                    @endforeach
                </ul>
            @elseif ($search !== '')
                <div class="ui-notice text-center">No record found.</div>
            @endif
        </div>
    </div>
</body>
</html>
