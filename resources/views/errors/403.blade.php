@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
    @include('errors.message', [
        'code' => 403,
        'heading' => 'Access denied',
        'text' => $exception->getMessage() ?: "You don't have permission to view this page.",
    ])
@endsection
