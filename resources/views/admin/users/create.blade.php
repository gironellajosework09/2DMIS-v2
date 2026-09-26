@extends('layouts.app')

@section('title', 'Create User — 2D MIS')

@section('content')

    @include('partials.page-header', [
        'title' => 'Create User',
        'subtitle' => 'Add a new system account.',
    ])

    <section class="data-card max-w-[520px]" aria-label="Create user form">
        <div class="data-card-body">
            <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-col gap-[12px]">
                @csrf

                <div>
                    <label for="username" class="field-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required autofocus>
                </div>

                <div>
                    <label for="password" class="field-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>

                <div>
                    <label for="password_confirmation" class="field-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                </div>

                <div class="mt-[4px] flex items-center justify-end gap-2 border-t border-line-light pt-[16px]">
                    <a href="{{ route('admin.users.index') }}" class="btn-subtle no-underline">Cancel / Return</a>
                    <button type="submit" class="btn-navy">Create User</button>
                </div>
            </form>
        </div>
    </section>
@endsection
