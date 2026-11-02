{{-- UDS Assistant (beta): signed-in customers only; every endpoint is behind auth --}}
<link rel="stylesheet" href="{{ asset('css/assistant.css') }}?v={{ @filemtime(public_path('css/assistant.css')) }}">
<div class="ua" data-ua data-max="{{ (int) config('services.anthropic.max_chars', 800) }}">
  <button type="button" class="ua-launch" aria-haspopup="dialog" aria-controls="ua-panel" aria-expanded="false">
    <span class="ua-launch__mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/></svg></span>
    {!! __('Ask UDS') !!} <span class="ua-launch__beta">{!! __('Beta') !!}</span>
  </button>
  <section class="ua-panel" id="ua-panel" role="dialog" aria-modal="false" aria-label="{!! __('UDS Assistant') !!}" aria-hidden="true" inert>
    <header class="ua-head">
      <span class="ua-head__mark ua-head__mark--av" aria-hidden="true"><img src="{{ \App\Support\Avatar::botUrl() }}" alt="" width="38" height="38"></span>
      <div class="ua-head__txt">
        <div class="ua-head__title">{!! __('UDS Assistant') !!} <span class="ua-beta">{!! __('Beta') !!}</span></div>
        <div class="ua-head__sub">{!! __('Parts, fitment, shipping &amp; your orders') !!}</div>
      </div>
      <button type="button" class="ua-iconbtn" data-ua-hist title="{!! __('Chat history') !!}" aria-label="{!! __('Your past chats') !!}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg></button>
      <button type="button" class="ua-iconbtn" data-ua-new title="{!! __('New chat') !!}" aria-label="{!! __('Start a new chat') !!}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></button>
      <button type="button" class="ua-iconbtn" data-ua-close title="{!! __('Close') !!}" aria-label="{!! __('Close the assistant') !!}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    </header>
    <div class="ua-banner" data-ua-banner hidden role="status"></div>
    <div class="ua-hist" data-ua-histbox hidden>
      <div class="ua-hist__bar"><button type="button" class="ua-hist__back" data-ua-hist-back><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>{!! __('Back') !!}</button><b data-ua-hist-title>{!! __('Your chats') !!} <small>· {!! __('last 15 days') !!}</small></b></div>
      <div class="ua-hist__list" data-ua-hist-list></div>
      <div class="ua-hist__view" data-ua-hist-view hidden></div>
    </div>
    <div class="ua-log" data-ua-log role="log" aria-live="polite" aria-relevant="additions"></div>
    <footer class="ua-foot">
      <button type="button" class="ua-person" data-ua-person><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>{!! __('Talk to a person') !!}</button>
      <div class="ua-tray" data-ua-tray hidden></div>
      <div class="ua-rec" data-ua-rec hidden role="status"><span class="ua-rec__dot" aria-hidden="true"></span><span data-ua-rec-t aria-hidden="true">0:00</span><span class="ua-rec__hint">{!! __('Recording… speak now') !!}</span><button type="button" class="ua-btn ua-btn--ghost" data-ua-rec-cancel>{!! __('Cancel') !!}</button><button type="button" class="ua-btn ua-btn--pri" data-ua-rec-send>{!! __('Send') !!}</button></div>
      <div class="ua-compose">
        <label class="ua-attach" title="{!! __('Add a photo') !!}"><input type="file" data-ua-photo-input accept="image/*,.heic,.heif" multiple hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg><span class="ua-sr">{!! __('Add a photo') !!}</span></label>
        <textarea data-ua-input rows="1" maxlength="{{ (int) config('services.anthropic.max_chars', 800) }}" placeholder="{!! __('Ask about a part or an order…') !!}" aria-label="{!! __('Message the assistant') !!}"></textarea>
        <button type="button" class="ua-mic" data-ua-mic hidden title="{!! __('Send a voice message') !!}" aria-label="{!! __('Record a voice message') !!}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
        <button type="button" class="ua-send" data-ua-send disabled aria-label="{!! __('Send') !!}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
      </div>
      <div class="ua-fine"><span>{!! __('Beta · AI can make mistakes — check before you order.') !!}</span><span><span data-ua-count></span> <span><b data-ua-left></b> {!! __('left today') !!}</span></span></div>
    </footer>
  </section>
</div>
<script src="{{ asset('js/assistant.js') }}?v={{ @filemtime(public_path('js/assistant.js')) }}" defer></script>
