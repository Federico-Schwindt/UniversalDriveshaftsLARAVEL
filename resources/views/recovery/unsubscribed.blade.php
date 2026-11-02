@extends('layouts.app')
@section('title', __('Cart reminders stopped | Universal Driveshafts'))
@section('robots', 'noindex,nofollow')
@section('content')
<section class="section"><div class="wrap rcv-page">
  <div class="aempty">
    <b>{{ __('Cart reminders are off') }}</b>
    <p style="margin:.2rem 0 1rem">{{ $email ? __('We will not e-mail :email about saved carts again. Order confirmations and anything you ask for still arrive as usual.', ['email' => $email]) : __('We will not e-mail you about saved carts again. Order confirmations and anything you ask for still arrive as usual.') }}</p>
    <div class="btn-row" style="justify-content:center"><a class="btn btn-primary" href="/cart">{{ __('Open my cart') }}</a><a class="btn btn-ghost" href="/">{{ __('Back to the site') }}</a></div>
  </div>
</div></section>
@endsection
