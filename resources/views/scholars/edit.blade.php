@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Edit Scholar</h1>
        <form action="{{ route('scholars.update', $scholar->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('scholars._form')
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('scholars.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
@endsection
