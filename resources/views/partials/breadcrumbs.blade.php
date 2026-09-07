{{-- Batch B stub — adopted by migrated screens starting with the next
     screen batch. Contract: receives $breadcrumbs = ordered array of
     ['label' => string, 'url' => string|null]; the last entry renders as
     the current page. NOT scanned by Tailwind yet (app.css uses an
     explicit allowlist): add an @source line for this file in the same
     change that first includes it on a screen. --}}
@if (! empty($breadcrumbs ?? []) && count($breadcrumbs) > 1)
    <nav aria-label="Breadcrumb" class="mb-[16px] flex min-w-0 items-center gap-1.5 text-dense text-ink-muted">
        @foreach ($breadcrumbs as $crumb)
            @continue($loop->first)
            @if ($loop->last || empty($crumb['url'] ?? null))
                <span class="truncate font-semibold text-ink" @if($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
            @else
                <a href="{{ $crumb['url'] }}" class="shrink-0 text-ink-muted no-underline transition duration-200 ease-standard hover:text-navy">{{ $crumb['label'] }}</a>
            @endif
            @if (! $loop->last)<span aria-hidden="true">›</span>@endif
        @endforeach
    </nav>
@endif
