@php
  $gEs = (include lang_path('i18n/content/guides-es.php'));
  $guides = collect($guides)->map(fn ($g, $slug) => array_replace_recursive($g, $gEs[$slug] ?? []))->all();
  $catImg = collect(config('uds.categories'))->keyBy(1);
@endphp
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="パンくずリスト"><a href="/">ホーム</a><x-icon name="caret-right"/><span aria-current="page">ガイド</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">現場から</span>
      <h1>プロペラシャフトガイド:測定・識別・修理</h1>
      <p class="lead">カウンターでご説明している内容を、すべて文章にまとめました。1ピースおよび2ピースのプロペラシャフトの測り方、ユニバーサルジョイントのシリーズをベアリングキャップで読み取る方法、振動の原因の突き止め方、チューブのサイズ選定、ピニオン角度の調整、そしてプロペラシャフトを修理する価値があるかどうかの判断までを解説します。</p>
      <div class="phero__cta">
        <a href="/guides/how-to-measure-a-driveshaft" class="btn btn-primary btn-lg"><x-icon name="ruler"/> まずは測定から</a>
        <a href="/online-builder" class="btn btn-ghost btn-lg">プロペラシャフトを組み立てる</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">{{ count($guides) }}</b><span>件の技術ガイド</span></li>
        <li><b class="u-mono">10</b><span>シリーズのユニバーサルジョイントを一覧表に掲載</span></li>
        <li><b class="u-mono">1</b><span>解説している製品を自社で製作する工場</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" alt="Universal Driveshafts でプロペラシャフトのチューブをヨークに溶接している様子" width="1200" height="900" loading="eager" fetchpriority="high"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>すべてのガイド</h2><p>各ガイドは、メジャーを持って車の下に立っている方に向けて書かれており、取り上げている部品へ直接リンクしています。</p></div>
  <div class="guide-grid">
    @foreach($guides as $slug => $g)
      <a class="guide-card" href="/guides/{{ $slug }}">
        <span class="guide-card__top"><span class="guide-card__ic"><x-icon :name="$g['icon']"/></span><span class="guide-card__kicker u-mono">{{ $g['kicker'] }}</span></span>
        <h3>{{ strtok($g['h1'], ':') }}</h3>
        <p>{{ $g['card'] }}</p>
        <span class="guide-card__go">ガイドを読む <x-icon name="arrow-right"/></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>ガイドの次のステップ</h2><p>ガイドでは判断のポイントを解説しています。実際に進めるためのツールとカテゴリーはこちらです。</p></div>
  <div class="uhub guide-hub">
    <a class="uhubcard" href="/online-builder"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>プロペラシャフトを組み立てる</b><span>仕様を指定して価格を確認</span></span></a>
    <a class="uhubcard" href="/driveshaft-designer"><span class="uhubcard__media"><img src="{{ asset('img/photos/panel-custom.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>プロペラシャフトデザイナー</b><span>作図し、寸法を入れて、印刷</span></span></a>
    <a class="uhubcard" href="/catalog/driveshafts"><span class="uhubcard__media"><img src="{{ asset('img/photos/hero-overland-sm.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>車両から探す</b><span>年式、メーカー、モデル</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/u-joints"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['u-joints'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>ユニバーサルジョイント</b><span>シリーズ別に179点</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/center-support-bearing"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['center-support-bearing'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>センターサポートベアリング</b><span>324点</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-tubing/tubing"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-steel-tube.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>カット済みチューブ</b><span>DOM鋼管、1フィート単位で販売</span></span></a>
  </div>
</div></section>

<section class="section"><div class="wrap"><div class="ctaband">
  <h2>測定は済みましたか?あとはお任せください</h2>
  <p>お客様の寸法に合わせて製作した1ピースおよび2ピースのプロペラシャフトを、フロリダでバランス調整し、全米へ発送します。(813) 524-6491 までお電話いただくか、オンラインでお申し込みください。</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">プロペラシャフトを組み立てる <x-icon name="arrow-right"/></a><a href="/contact" class="btn btn-ghost">技術スタッフに相談する</a></div>
</div></div></section>