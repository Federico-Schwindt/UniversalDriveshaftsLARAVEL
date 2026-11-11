@php
  $gEs = (include lang_path('i18n/content/guides-fr.php'));
  $guides = collect($guides)->map(fn ($g, $slug) => array_replace_recursive($g, $gEs[$slug] ?? []))->all();
  $catImg = collect(config('uds.categories'))->keyBy(1);
@endphp
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Fil d'Ariane"><a href="/">Accueil</a><x-icon name="caret-right"/><span aria-current="page">Guides</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Tout droit sorti de l'atelier</span>
      <h1>Guides des arbres de transmission : mesurez, identifiez, réparez</h1>
      <p class="lead">Tout ce que nous expliquons au comptoir, par écrit. Comment mesurer un arbre de transmission en une ou deux pièces, lire la série d'un croisillon sur son bol, identifier l'origine d'une vibration, dimensionner le tube, régler l'angle du pignon et décider s'il vaut la peine de réparer l'arbre de transmission.</p>
      <div class="phero__cta">
        <a href="/guides/how-to-measure-a-driveshaft" class="btn btn-primary btn-lg"><x-icon name="ruler"/> Commencez par la mesure</a>
        <a href="/online-builder" class="btn btn-ghost btn-lg">Configurez votre arbre de transmission</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">{{ count($guides) }}</b><span>guides techniques</span></li>
        <li><b class="u-mono">10</b><span>séries de croisillons dans le tableau</span></li>
        <li><b class="u-mono">1</b><span>atelier qui fabrique ce qu'il explique</span></li>
      </ul>
    </div>
    <div class="phero__media phéro__media--photo"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" alt="Soudage d'un tube d'arbre de transmission sur une fourche chez Universal Driveshafts" width="1200" height="900" loading="eager" fetchpriority="high"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Tous les guides</h2><p>Chacun est rédigé pour une personne se tenant sous le véhicule avec un mètre ruban, et renvoie directement aux pièces concernées.</p></div>
  <div class="guide-grid">
    @foreach($guides as $slug => $g)
      <a class="guide-card" href="/guides/{{ $slug }}">
        <span class="guide-card__top"><span class="guide-card__ic"><x-icon :name="$g['icon']"/></span><span class="guide-card__kicker u-mono">{{ $g['kicker'] }}</span></span>
        <h3>{{ strtok($g['h1'], ':') }}</h3>
        <p>{{ $g['card'] }}</p>
        <span class="guide-card__go">Lire le guide <x-icon name="arrow-right"/></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>Où mènent les guides</h2><p>Les guides expliquent les choix. Voici les outils et catégories pour les mettre en pratique.</p></div>
  <div class="uhub guide-hub">
    <a class="uhubcard" href="/online-builder"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Configurez votre arbre de transmission</b><span>Spécifiez-le et consultez le prix</span></span></a>
    <a class="uhubcard" href="/driveshaft-designer"><span class="uhubcard__media"><img src="{{ asset('img/photos/panel-custom.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Concepteur d'arbres de transmission</b><span>Dessinez-le, cotes à l'appui, imprimez-le</span></span></a>
    <a class="uhubcard" href="/catalog/driveshafts"><span class="uhubcard__media"><img src="{{ asset('img/photos/hero-overland-sm.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Recherche par véhicule</b><span>Année, marque et modèle</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/u-joints"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['u-joints'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>Croisillons</b><span>179 pièces par série</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/center-support-bearing"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['center-support-bearing'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>Paliers centraux</b><span>324 pièces</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-tubing/tubing"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-steel-tube.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Tube coupé sur mesure</b><span>Acier DOM, vendu au pied</span></span></a>
  </div>
</div></section>

<section class="section"><div class="wrap"><div class="ctaband">
  <h2>Vous l'avez mesuré ? Nous le fabriquons</h2>
  <p>Arbres de transmission en une et deux pièces fabriqués selon vos dimensions, équilibrés en Floride et expédiés dans tout le pays. Appelez le (813) 524-6491 ou commencez en ligne.</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">Configurez votre arbre de transmission <x-icon name="arrow-right"/></a><a href="/contact" class="btn btn-ghost">Parler à un technicien</a></div>
</div></div></section>