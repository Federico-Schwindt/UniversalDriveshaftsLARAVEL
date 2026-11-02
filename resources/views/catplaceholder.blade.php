@extends('layouts.app')
@section('title', $title)
@section('desc','This part of the Universal Driveshafts catalog is being rebuilt. Contact us and we will find the exact part you need.')
@section('content')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">{{ __('Home') }}</a><span class="sep">/</span><a href="/shop">{{ __('Shop') }}</a><span class="sep">/</span><span>{{ $title }}</span></div><div class="accentline"></div>
<h1>{{ $title }}</h1></div></section>
<section class="section"><div class="wrap"><div class="help-band">
<h2>{{ __('This catalog section is on its way') }}</h2>
<p>{!! __('We are rebuilding the parts catalog. Need a part from :title right now? Tell us the part number or your measurements and we will get you the exact fit today.', ['title' => '<b>' . e($title) . '</b>']) !!}</p>
<a href="/contact-us" class="btn btn-primary">{{ __('Contact Us') }} <x-icon name="arrow-right"/></a>
<a href="/online-builder" class="btn btn-ghost" style="margin-left:10px;border-color:var(--line);color:var(--ink)">{{ __('Open the online builder') }}</a>
</div></div></section>
@endsection
