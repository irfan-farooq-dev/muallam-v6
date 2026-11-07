@extends('layouts.app')

@section('title', 'Page not found')

@section('content')
    @include('errors.message', [
        'code' => 404,
        'heading' => 'Page not found',
        'text' => "The page you're looking for doesn't exist or has been moved.",
    ])
@endsection
