@php
  $gEs = (include lang_path('i18n/content/guides-es.php'));
  $guides = collect($guides)->map(fn ($g, $slug) => array_replace_recursive($g, $gEs[$slug] ?? []))->all();
  $catImg = collect(config('uds.categories'))->keyBy(1);
@endphp
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="面包屑导航"><a href="/">首页</a><x-icon name="caret-right"/><span aria-current="page">指南</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">来自车间</span>
      <h1>传动轴指南：测量、识别、修理</h1>
      <p class="lead">我们在柜台前讲解的一切，现在都已文字化。如何测量单根或双根传动轴、从万向节盖上读取万向节系列、追踪振动源头、计算传动轴管尺寸、调整小齿轮角度以及判断传动轴是否值得修复。</p>
      <div class="phero__cta">
        <a href="/guides/how-to-measure-a-driveshaft" class="btn btn-primary btn-lg"><x-icon name="ruler"/> 从测量开始</a>
        <a href="/online-builder" class="btn btn-ghost btn-lg">组装传动轴</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">{{ count($guides) }}</b><span>篇技术指南</span></li>
        <li><b class="u-mono">10</b><span>种图表万向节系列</span></li>
        <li><b class="u-mono">1</b><span>家亲自动手制造所解释产品的车间</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" alt="在 Universal Driveshafts 将传动轴管焊接至万向节叉" width="1200" height="900" loading="eager" fetchpriority="high"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>所有指南</h2><p>每一篇指南都是为正拿着卷尺站在车底下的人编写的，并且直接链接到它所讨论的零件。</p></div>
  <div class="guide-grid">
    @foreach($guides as $slug => $g)
      <a class="guide-card" href="/guides/{{ $slug }}">
        <span class="guide-card__top"><span class="guide-card__ic"><x-icon :name="$g['icon']"/></span><span class="guide-card__kicker u-mono">{{ $g['kicker'] }}</span></span>
        <h3>{{ strtok($g['h1'], ':') }}</h3>
        <p>{{ $g['card'] }}</p>
        <span class="guide-card__go">阅读指南 <x-icon name="arrow-right"/></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>指南通往何处</h2><p>指南阐明了决策。这些是将其付诸实践的工具和分类。</p></div>
  <div class="uhub guide-hub">
    <a class="uhubcard" href="/online-builder"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>组装传动轴</b><span>指定规格并查看价格</span></span></a>
    <a class="uhubcard" href="/driveshaft-designer"><span class="uhubcard__media"><img src="{{ asset('img/photos/panel-custom.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>传动轴设计器</b><span>绘制、标注尺寸、打印</span></span></a>
    <a class="uhubcard" href="/catalog/driveshafts"><span class="uhubcard__media"><img src="{{ asset('img/photos/hero-overland-sm.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>按车型搜索</b><span>年份、品牌和型号</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/u-joints"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['u-joints'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>万向节</b><span>每系列 179 种零件</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/center-support-bearing"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['center-support-bearing'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>中间支撑轴承</b><span>324 种零件</span></span></a>
    <a class="uhubcard" href="/shop/custom-cut-tubing/tubing"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-steel-tube.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>按尺寸切割传动轴管</b><span>DOM 钢材，按英尺出售</span></span></a>
  </div>
</div></section>

<section class="section"><div class="wrap"><div class="ctaband">
  <h2>量好尺寸了？我们来制造</h2>
  <p>根据您的尺寸定制单根和双根传动轴，在佛罗里达州完成动平衡，并向全国发货。请致电 (813) 524-6491 或在线开始定制。</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">组装传动轴 <x-icon name="arrow-right"/></a><a href="/contact" class="btn btn-ghost">与技术人员沟通</a></div>
</div></div></section>