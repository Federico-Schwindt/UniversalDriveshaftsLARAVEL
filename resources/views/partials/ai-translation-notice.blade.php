{{-- right after switching away from English: the text on the site is machine-translated (owner, 2026-10-24) --}}
@if(app()->getLocale() !== 'en' && in_array(request()->query('lang'), \App\Http\Middleware\SetLocale::SUPPORTED, true))
<div class="aitr" role="status" data-aitr>
  <svg class="aitr__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/></svg>
  <p>{!! __('This translation was made with AI and may contain errors. If something is unclear, call (813) 524-6491 or switch to English.') !!}</p>
  <button type="button" class="aitr__x" aria-label="{{ __('Close') }}" data-aitr-x>&times;</button>
</div>
<script>(function(){var t=document.querySelector('[data-aitr]');if(!t)return;var x=function(){t.classList.add('is-out');setTimeout(function(){t.remove();},250);};t.querySelector('[data-aitr-x]').addEventListener('click',x);setTimeout(x,12000);
 try{var u=new URL(location.href);u.searchParams.delete('lang');history.replaceState(null,'',u.pathname+u.search+u.hash);}catch(e){}})();</script>
@endif
