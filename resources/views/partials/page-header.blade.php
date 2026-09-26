{{-- Batch B stub — adopted by migrated screens starting with the next
     screen batch. Contract: receives $title (string), optional
     $subtitle (string), optional $actions slot. NOT scanned by Tailwind
     yet (app.css uses an explicit allowlist): add an @source line for
     this file in the same change that first includes it on a screen. --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0 px-4 py-4">
        <h1 class="text-page-title font-heading font-bold leading-tight text-ink">{{ $title }}</h1>
        @isset($subtitle)
            <p class="mt-1 text-dense text-ink-secondary">{{ $subtitle }}</p>
        @endisset
    </div>
    @isset($actions)
        {{-- Raw output BY CONTRACT: callers pass pre-built button markup
             (same trust level as view-authored markup). Escaped echo here
             rendered buttons as literal text on adopted screens. --}}
        <div class="ml-auto flex shrink-0 items-center gap-2">{!! $actions !!}</div>
    @endisset
</div>
