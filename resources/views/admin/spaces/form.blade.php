@extends('admin.layout')
@php $editing = $space->exists; @endphp
@section('title', $editing ? 'Edit ruangan' : 'Tambah ruangan')
@section('heading', $editing ? 'Edit ruangan' : 'Tambah ruangan')

@section('content')
@include('partials.space-form', [
    'action' => $editing ? route('admin.spaces.update', $space) : route('admin.spaces.store'),
    'cancelUrl' => route('admin.spaces.index'),
])
@endsection
