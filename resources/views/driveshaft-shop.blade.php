@extends('layouts.app')
@section('title','Driveshaft Shop')
@section('desc','Browse premade driveshafts, yokes and parts.')
@section('content')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">{{ __('Home') }}</a><span class="sep">/</span><span>{{ __('Shop') }}</span></div><div class="accentline"></div>
<h1>{{ __('Driveshaft Shop') }}</h1><p class="lead">{{ __('The full parts catalog is coming in the next phase. In the meantime, tell us what you need and we will match the right part.') }}</p></div></section>
<section class="section"><div class="wrap"><div class="help-band">
<h2>{{ __('Need a part now?') }}</h2><p>{{ __('Send us your measurements or part number and we will get you the exact fit.') }}</p>
<a href="/contact" class="btn btn-primary">{{ __('Contact Us') }} <x-icon name="arrow-right"/></a><a href="/online-builder" class="btn btn-ghost" style="margin-left:10px">{{ __('Open the online builder') }}</a>
</div></div></section>
@endsection
