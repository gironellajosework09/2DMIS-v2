@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Add Scholar</h1>
        <form action="{{ route('scholars.store') }}" method="POST">
            @csrf
            @include('scholars._form')
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('scholars.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
@endsection
