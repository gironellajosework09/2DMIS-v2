@extends('layouts.app')

@section('title', 'Manage Multiple Device Exemptions — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): single-device
     exemption toggle. GET select + POST toggle contract unchanged. --}}
@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Multiple Device Exemptions'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Manage Multiple Device Exemptions',
        'subtitle' => 'Exempt a user from the single-device login rule.',
    ])

    <div class="flex flex-col gap-[16px]">
        <section class="data-card" aria-label="Select user">
            <div class="data-card-body max-w-[420px]">
                <form method="GET" action="{{ route('admin.exemptions.pages') }}">
                    <label class="field-label" for="user_select">Select User</label>
                    <select name="user_id" id="user_select" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select User --</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </section>

        @if ($selectedUser)
            <section class="data-card max-w-[640px]" aria-label="Exemption toggle">
                <div class="data-card-body">
                    <form method="POST" action="{{ route('admin.exemptions.toggle', $selectedUser->id) }}">
                        @csrf

                        <div class="mb-[16px] form-check">
                            <input class="form-check-input" type="checkbox" name="grant" value="1" id="grant"
                                   @checked($isExempt)>
                            <label class="form-check-label" for="grant">
                                Allow this user to login on multiple devices
                            </label>
                        </div>

                        <button type="submit" class="btn-navy">Save Changes</button>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
