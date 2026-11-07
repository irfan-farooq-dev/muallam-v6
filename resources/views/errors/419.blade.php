@extends('layouts.app')

@section('title', 'Page expired')

@section('content')
    @include('errors.message', [
        'code' => 419,
        'heading' => 'Page expired',
        'text' => "Your session expired. Please go back, refresh the page and try again.",
    ])
@endsection
