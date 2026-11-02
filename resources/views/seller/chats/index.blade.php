@extends('seller.layout')

@section('title', 'Chat')
@section('heading', 'Chat Pembeli')

@section('content')
@include('chat.inbox', ['indexRoute' => 'seller.chats.index', 'showRoute' => 'seller.chats.show'])
@endsection
