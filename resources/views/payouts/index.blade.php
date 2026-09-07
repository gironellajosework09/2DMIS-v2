@extends('layouts.app')

@section('title', 'Payouts — 2D MIS')

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Payouts'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Payouts',
        'subtitle' => 'Payout attendance, unpaid grantees and payout scanners',
    ])

    @if (empty($destinations))
        <div class="data-card">
            <div class="data-card-body">
                <p class="m-0 py-6 text-center text-ink-muted">No payout screens are available for your account.</p>
            </div>
        </div>
    @else
        {{-- UX-4: Payouts unified tab workspace. Presentation/navigation only —
             every tab opens the EXISTING per-variant route; the underlying
             payout variants, routes, permission keys and business rules are
             untouched and never merged. Tab availability follows the same
             per-page-key ACL filtering that previously drove the card grid. --}}
        <section class="data-card" aria-label="Payout workspace">
            <div class="data-card-body">
                <nav class="flex flex-wrap gap-1" role="tablist" aria-label="Payout screens">
                    @foreach ($destinations as $index => $destination)
                        @php($icon = $destination['kind'] === 'scanner' ? 'scan' : ($destination['kind'] === 'unpaid' ? 'user-x' : 'dollar'))
                        @php($active = $index === 0)
                        <a href="{{ $destination['url'] }}"
                           role="tab"
                           aria-selected="{{ $active ? 'true' : 'false' }}"
                           class="inline-flex items-center gap-2 rounded-btn border px-4 py-2 text-dense font-medium no-underline transition duration-200 ease-standard {{ $active ? 'border-navy bg-navy text-white' : 'border-line bg-surface text-ink/70 hover:bg-surface-hover hover:text-navy' }}">
                            <span class="[&>svg]:h-[16px] [&>svg]:w-[16px]" aria-hidden="true">@include('partials.sidebar-icon', ['name' => $icon])</span>
                            {{ $destination['title'] }}
                        </a>
                    @endforeach
                </nav>
                <p class="m-0 mt-4 text-dense text-ink-muted">
                    Select a screen above. Payout attendance, unpaid grantees and the
                    payout scanners open in their existing screens — permissions are
                    enforced per screen exactly as before.
                </p>
            </div>
        </section>
    @endif
@endsection