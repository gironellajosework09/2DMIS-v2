@extends('layouts.app')

@section('title', 'Client Profile — 2D MIS')

@section('content')
    @include('clients._details', ['panel' => false])
@endsection
