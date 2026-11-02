@extends('admin.layout')

@section('title', 'Chat')
@section('heading', 'Chat Pembeli & Penjual')

@section('content')
@include('chat.inbox', ['indexRoute' => 'admin.chats.index', 'showRoute' => 'admin.chats.show', 'showSeller' => true])
@endsection
