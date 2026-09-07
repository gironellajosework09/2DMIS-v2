@extends('layouts.app')

@section('title', 'Edit Scholar — 2D MIS')

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Scholars', 'url' => route('scholars.index')],
            ['label' => 'Edit Scholar'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Edit Scholar',
        'subtitle' => 'Update this scholar record and its linked client.',
    ])

    <section class="data-card max-w-[880px]" aria-label="Edit scholar form">
        <div class="data-card-body">
            <form action="{{ route('scholars.update', $scholar->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('scholars._form')

                <div class="mt-[16px] flex items-center justify-end gap-2 border-t border-line-light pt-[16px]">
                    <a href="{{ route('scholars.index') }}" class="btn-subtle no-underline">Cancel / Return</a>
                    <button type="submit" class="btn-navy">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
@endsection
