@php
  $gEs = (include lang_path('i18n/content/guides-es.php'));
  $guides = collect($guides)->map(fn ($g, $slug) => array_replace_recursive($g, $gEs[$slug] ?? []))->all();
  $catImg = collect(config('uds.categories'))->keyBy(1);
@endphp
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Ruta de navegación"><a href="/">Inicio</a><x-icon name="caret-right"/><span aria-current="page">Guías</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Desde el taller</span>
      <h1>Guías de cardanes: mídelo, identifícalo, arréglalo</h1>
      <p class="lead">Todo lo que explicamos en el mostrador, por escrito. Cómo medir un cardán de una o dos piezas, leer la serie de una cruceta en su tapa, rastrear una vibración hasta su causa, dimensionar el tubo, ajustar el ángulo del piñón y decidir si vale la pena reparar el cardán.</p>
      <div class="phero__cta">
        <a href="/guides/how-to-measure-a-driveshaft" class="btn btn-primary btn-lg"><x-icon name="ruler"/> Empieza por la medición</a>
        <a href="/online-builder" class="btn btn-ghost btn-lg">Arma tu cardán</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">{{ count($guides) }}</b><span>guías técnicas</span></li>
        <li><b class="u-mono">10</b><span>series de crucetas en la tabla</span></li>
        <li><b class="u-mono">1</b><span>taller que fabrica lo que explica</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" alt="Soldadura de un tubo de cardán a un yugo en Universal Driveshafts" width="1200" height="900" loading="eager" fetchpriority="high"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Todas las guías</h2><p>Cada una está escrita para alguien parado debajo del vehículo con una cinta métrica, y enlaza directamente con las piezas de las que habla.</p></div>
  <div class="guide-grid">
    @foreach($guides as $slug => $g)
      <a class="guide-card" href="/guides/{{ $slug }}">
        <span class="guide-card__top"><span class="guide-card__ic"><x-icon :name="$g['icon']"/></span><span class="guide-card__kicker u-mono">{{ $g['kicker'] }}</span></span>
        <h3>{{ strtok($g['h1'], ':') }}</h3>
        <p>{{ $g['card'] }}</p>
        <span class="guide-card__go">Leer la guía <x-icon name="arrow-right"/></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>Adónde llevan las guías</h2><p>Las guías explican las decisiones. Estas son las herramientas y categorías para ponerlas en práctica.</p></div>
  <div class="uhub guide-hub">
    <a class="uhubcard" href="/online-builder"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Arma tu cardán</b><span>Especifícalo y ve el precio</span></span></a>
    <a class="uhubcard" href="/driveshaft-designer"><span class="uhubcard__media"><img src="{{ asset('img/photos/panel-custom.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Diseñador de cardanes</b><span>Dibújalo, acótalo, imprímelo</span></span></a>
    <a class="uhubcard" href="/catalog/driveshafts"><span class="uhubcard__media"><img src="{{ asset('img/photos/hero-overland-sm.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Buscar por vehículo</b><span>Año, marca y modelo</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/u-joints"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['u-joints'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>Crucetas</b><span>179 piezas por serie</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/center-support-bearing"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['center-support-bearing'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>Soportes centrales</b><span>324 piezas</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-tubing/tubing"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-steel-tube.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Tubo cortado a medida</b><span>Acero DOM, se vende por pie</span></span></a>
  </div>
</div></section>

<section class="section"><div class="wrap"><div class="ctaband">
  <h2>¿Ya lo mediste? Nosotros lo fabricamos</h2>
  <p>Cardanes de una y dos piezas fabricados con tus medidas, balanceados en Florida, y enviados a todo el país. Llama al (813) 524-6491 o empieza en línea.</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">Arma tu cardán <x-icon name="arrow-right"/></a><a href="/contact" class="btn btn-ghost">Hablar con un técnico</a></div>
</div></div></section>
