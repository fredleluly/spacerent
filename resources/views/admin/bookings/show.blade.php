@extends('admin.layout')
@section('title', 'Booking '.$booking->code)
@section('heading', 'Detail booking')

@section('content')
@include('partials.panel-booking-detail', ['panel' => 'admin'])
@endsection
