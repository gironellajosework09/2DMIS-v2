@extends('layouts.app')

@section('title', 'Add Scholar — 2D MIS')

@section('content')

    @include('partials.page-header', [
        'title' => 'Add Scholar',
        'subtitle' => 'Register a scholar and link them to an existing client record.',
    ])

    <section class="data-card max-w-[880px]" aria-label="Add scholar form">
        <div class="data-card-body">
            <form action="{{ route('scholars.store') }}" method="POST">
                @csrf
                @include('scholars._form')

                <div class="mt-[16px] flex items-center justify-end gap-2 border-t border-line-light pt-[16px]">
                    <a href="{{ route('scholars.index') }}" class="btn-subtle no-underline">Cancel / Return</a>
                    <button type="submit" class="btn-navy">Save Scholar</button>
                </div>
            </form>
        </div>
    </section>
@endsection
