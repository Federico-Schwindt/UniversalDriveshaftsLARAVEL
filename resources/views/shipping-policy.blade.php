@extends('layouts.app')
@section('title', app()->getLocale() === 'es' ? 'Política de envíos | Universal Driveshafts' : __('Shipping Policy | Universal Driveshafts'))
@section('desc', app()->getLocale() === 'es' ? 'Cómo envía Universal Driveshafts: tarifas de UPS y FedEx en tiempo real, envío el mismo día antes de las 2 p. m. (EST), flete LTL para pallets, envío gratis en pedidos de más de $' . number_format(config('uds.free_shipping_min')) . ' en los 48 estados contiguos, retiro en el local en Florida, y envíos internacionales.' : __('How Universal Driveshafts ships: live UPS and FedEx rates, same-day shipping before 2pm EST, LTL freight for pallets, free shipping over $:min in the lower 48, local pickup in Florida and international shipping.', ['min' => number_format(config('uds.free_shipping_min'))]))
@section('content')
@php $min = number_format(config('uds.free_shipping_min')); @endphp
@if(app()->getLocale() === 'es')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">Inicio</a><span class="sep">/</span><span>Política de envíos</span></div><div class="accentline"></div>
<h1>Política de envíos</h1></div></section>

<section class="section"><div class="wrap prose legal" style="--measure:860px">
<style>
.legal h2{margin-top:34px;padding-top:10px;border-top:1px solid var(--line)}
.legal .box{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin:18px 0}
.legal table{width:100%;border-collapse:collapse;font-size:.93rem;margin:14px 0}
.legal td,.legal th{border:1px solid var(--line);padding:9px 12px;text-align:left;vertical-align:top}
.legal th{background:var(--bg-soft)}
.legal-tr{background:var(--warn-bg);border:1px solid #ead9b5;border-radius:12px;padding:12px 16px;margin:0 0 18px;font-size:.92rem;color:var(--ink-2)}
</style>
  <p class="legal-tr">Esta es una traducción de cortesía. En caso de discrepancia, prevalece la versión en inglés.</p>
  <p><b>Universal Driveshafts LLC</b> (“<b>UDS</b>”) realiza los envíos desde nuestro taller en Florida. Esta página explica cómo se embala, se cotiza y se entrega su pedido. Forma parte de nuestros <a href="/terms#s7">Términos de uso y Condiciones de venta</a>.</p>

  <div class="box">
    <p style="margin-top:0"><b>Resumen</b></p>
    <ul style="margin-bottom:0">
      <li>Los pedidos realizados antes de las 2 p. m. (EST) en un día hábil se envían el mismo día (artículos en stock).</li>
      <li>Tarifas de UPS y FedEx en tiempo real al finalizar la compra, calculadas sobre las cajas reales que necesita su pedido. No realizamos envíos por USPS.</li>
      <li>Los pedidos grandes se envían en un pallet por flete terrestre LTL, cotizado en tiempo real al finalizar la compra.</li>
      <li>Envío terrestre gratis en EE. UU. en pedidos de más de <b>${{ $min }}</b> dentro de los 48 estados contiguos (se excluyen los tubos y los cardanes completos).</li>
      <li>Retiro en el local gratis en Florida.</li>
      <li>Envíos internacionales por UPS Worldwide y DHL Express; los aranceles e impuestos los paga el destinatario.</li>
    </ul>
  </div>

  <h2>1. Tiempo de procesamiento</h2>
  <p>Los pedidos realizados antes de las 2 p. m. (EST) en un día hábil se envían el mismo día. Los pedidos realizados más tarde, en fines de semana o en días feriados se envían el siguiente día hábil. Los cardanes fabricados a medida, los tubos cortados a medida y los artículos de pedido especial se envían cuando se fabrican o se reciben; el plazo de entrega estimado se muestra en la página del producto o se le confirma después de realizar el pedido. Los tiempos de entrega indicados por los transportistas son estimaciones y no están garantizados.</p>

  <h2>2. Cómo se calcula el envío</h2>
  <p>El envío se calcula al finalizar la compra a partir de las tarifas de UPS y FedEx en tiempo real para su dirección y las cajas reales que necesita su pedido. Embalamos según las normas de los transportistas:</p>
  <table>
    <tr><th style="width:42%">Lo que pide</th><th>Cómo se envía</th></tr>
    <tr><td>Cada cardán completo</td><td>En su propia caja</td></tr>
    <tr><td>Cada tramo de tubo</td><td>En su propia caja (el tubo vendido por pie se envía como una sola pieza continua; los tramos muy largos se cortan para que un transportista pueda aceptarlos)</td></tr>
    <tr><td>Cualquier pieza individual de más de 35 lb</td><td>En su propia caja</td></tr>
    <tr><td>Otros componentes (crucetas, yugos, rodamientos, tornillería)</td><td>Agrupados en cajas de hasta 50 lb</td></tr>
  </table>
  <p>Cada caja lleva su propia etiqueta y se cotiza por separado, por lo que a un pedido que necesita varias cajas se le cobra la suma de esas tarifas. Las cajas largas pueden tener cargos del transportista por sobredimensión o por manipulación adicional; esos cargos ya están incluidos en el precio que ve al finalizar la compra. Si no podemos obtener una tarifa en tiempo real para su dirección, el proceso de compra se lo indicará; <a href="/contact">comuníquese con nosotros</a> y le cotizaremos el envío antes de despachar el pedido.</p>

  <h2>3. Envíos por flete (LTL)</h2>
  <p>Los pedidos de <b>cinco o más tubos o cardanes completos</b>, los pedidos de <b>100 lb o más</b> y cualquier pieza individual <b>de más de 108 pulgadas de largo</b> se sujetan a un pallet y se envían por flete terrestre LTL. Los demás artículos del mismo pedido viajan en el mismo pallet. El flete se cotiza en tiempo real al finalizar la compra.</p>
  <ul>
    <li><b>Lugar de entrega.</b> Las tarifas de flete suponen la entrega en un negocio con muelle de carga. Si la entrega es en un domicilio, o el lugar no tiene muelle y necesita una plataforma elevadora (liftgate), indíquelo al finalizar la compra para que la tarifa lo incluya.</li>
    <li><b>Recepción de la entrega.</b> El transportista llamará para programarla. Alguien debe estar presente para recibirla. Inspeccione el pallet antes de firmar y anote cualquier daño visible en el comprobante de entrega.</li>
    <li><b>El envío gratis no se aplica al flete,</b> y los cupones de envío gratis no cubren los cargos de flete.</li>
  </ul>

  <h2>4. Envío gratis</h2>
  <p>Ofrecemos envío terrestre gratis en EE. UU. en pedidos de más de <b>${{ $min }}</b> enviados dentro de los Estados Unidos contiguos (los 48 estados continentales).</p>
  <ul>
    <li><b>Los tubos cortados a medida y los cardanes completos</b> (prefabricados, de acople corto, ensambles sin soldar, cardanes específicos para vehículos y ejes fabricados mediante «Arma tu cardán») no cuentan para los ${{ $min }} y nunca se envían gratis. Cuando el resto de su pedido califica, esas piezas se envían gratis y usted paga solo la tarifa del transportista por las cajas de tubos y cardanes, que se muestra como una opción aparte al finalizar la compra.</li>
    <li>Los pedidos que se envían por flete en un pallet nunca se envían gratis.</li>
    <li>El envío gratis es por servicio terrestre. Los servicios acelerados, Alaska, Hawái, los territorios de EE. UU., las direcciones APO/FPO y los destinos internacionales siempre se cobran a la tarifa cotizada.</li>
  </ul>

  <h2>5. Retiro en el local</h2>
  <p>Elija retiro en el local al finalizar la compra para recoger su pedido en nuestro taller, sin cargo: {{ config('uds.pickup.label') }}. El retiro está disponible para pedidos de EE. UU. Espere nuestro correo electrónico que le avisa que su pedido está listo antes de venir.</p>

  <h2>6. Envíos internacionales</h2>
  <p>Realizamos envíos internacionales por UPS Worldwide y DHL Express a la tarifa cotizada al finalizar la compra, con la documentación aduanera preparada por nosotros. Los pedidos internacionales se envían <b>DAP</b> (entregado en lugar convenido): los aranceles de importación, impuestos, gastos de despacho aduanero y cualquier cargo que aplique el país de destino no están incluidos en nuestro precio y los paga el destinatario al momento de la entrega. Algunos artículos largos o pesados no pueden viajar internacionalmente como paquetes; si su pedido no muestra una tarifa internacional, <a href="/contact">comuníquese con nosotros</a> para obtener una cotización de flete.</p>

  <h2>7. Direcciones, seguimiento y problemas con la entrega</h2>
  <p>UPS y FedEx no entregan en apartados postales (P.O. boxes); indique una dirección física. Una vez despachado su pedido, le enviamos por correo electrónico el número de seguimiento (y por mensaje de texto, si aceptó recibir mensajes), y puede seguirlo desde su <a href="/account">cuenta</a> o desde nuestra <a href="/track-order">página de seguimiento de pedidos</a>.</p>
  <p>Inspeccione cada envío al recibirlo e informe faltantes, artículos equivocados o daños de envío dentro de los <b>5 días hábiles</b> posteriores a la entrega, con fotografías del embalaje, la etiqueta y el producto. Conserve todo el embalaje hasta que se resuelva el reclamo. Consulte <a href="/return-policy#damage">Faltantes, artículos equivocados y daños de envío</a> en nuestra Política de devoluciones.</p>

  <h2>8. Contacto</h2>
  <p>¿Tiene preguntas sobre el envío de un pedido específico? Llame al (813) 524-6491 o escriba a <a href="mailto:inquiry@universaldriveshafts.com">inquiry@universaldriveshafts.com</a>.</p>
</div></section>
@elseif(i18n_view('legal.shipping-policy'))
@include(i18n_view('legal.shipping-policy'))
@else
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">Home</a><span class="sep">/</span><span>Shipping Policy</span></div><div class="accentline"></div>
<h1>Shipping policy</h1></div></section>

<section class="section"><div class="wrap prose legal" style="--measure:860px">
<style>
.legal h2{margin-top:34px;padding-top:10px;border-top:1px solid var(--line)}
.legal .box{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin:18px 0}
.legal table{width:100%;border-collapse:collapse;font-size:.93rem;margin:14px 0}
.legal td,.legal th{border:1px solid var(--line);padding:9px 12px;text-align:left;vertical-align:top}
.legal th{background:var(--bg-soft)}
</style>
  <p><b>Universal Driveshafts LLC</b> (“<b>UDS</b>”) ships from our shop in Florida. This page explains how your order is packed, priced and delivered. It is part of our <a href="/terms#s7">Terms of Use &amp; Conditions of Sale</a>.</p>

  <div class="box">
    <p style="margin-top:0"><b>At a glance</b></p>
    <ul style="margin-bottom:0">
      <li>Orders placed before 2pm EST on a weekday ship the same day (in-stock items).</li>
      <li>Live UPS and FedEx rates at checkout, priced on the real boxes your order needs. We do not ship by USPS.</li>
      <li>Big orders ship on a pallet by LTL truck freight, priced live at checkout.</li>
      <li>Free U.S. ground shipping over <b>${{ $min }}</b> in the lower 48 states (tubing and complete driveshafts excluded).</li>
      <li>Free local pickup in Florida.</li>
      <li>International shipping by UPS Worldwide and DHL Express; duties and taxes are paid by the recipient.</li>
    </ul>
  </div>

  <h2>1. Processing time</h2>
  <p>Orders placed before 2pm EST on a weekday ship the same day. Orders placed later, on weekends or on holidays ship the next business day. Custom-built driveshafts, cut-to-length tubing and special-order items ship when they are built or received; the expected lead time is shown on the product page or confirmed with you after ordering. Delivery times quoted by carriers are estimates and are not guaranteed.</p>

  <h2>2. How shipping is priced</h2>
  <p>Shipping is calculated at checkout from live UPS and FedEx rates for your address and the actual boxes your order needs. We pack to carrier rules:</p>
  <table>
    <tr><th style="width:42%">What you order</th><th>How it ships</th></tr>
    <tr><td>Each complete driveshaft</td><td>Its own box</td></tr>
    <tr><td>Each length of tubing</td><td>Its own box (tubing sold by the foot ships as one continuous piece; very long runs are cut so a carrier can take them)</td></tr>
    <tr><td>Any single part over 35 lb</td><td>Its own box</td></tr>
    <tr><td>Other components (u-joints, yokes, bearings, hardware)</td><td>Combined in boxes of up to 50 lb</td></tr>
  </table>
  <p>Each box carries its own label and is rated separately, so an order that needs several boxes is charged the sum of those rates. Long boxes can carry carrier oversize or additional-handling charges; those are already included in the price you see at checkout. If we cannot get a live rate for your address, the checkout will tell you; <a href="/contact">contact us</a> and we will quote shipping before the order ships.</p>

  <h2>3. Freight (LTL) shipments</h2>
  <p>Orders of <b>five or more tubes or complete driveshafts</b>, orders of <b>100 lb or more</b>, and any single piece <b>longer than 108 inches</b> are strapped to a pallet and shipped by LTL truck freight. Other items in the same order ride on the same pallet. Freight is priced live at checkout.</p>
  <ul>
    <li><b>Delivery location.</b> Freight rates assume delivery to a business with a loading dock. If the delivery is to a residence, or the site has no dock and needs a liftgate, say so at checkout so the rate includes it.</li>
    <li><b>Receiving the delivery.</b> The carrier will call to schedule. Someone must be there to receive it. Inspect the pallet before signing and write any visible damage on the delivery receipt.</li>
    <li><b>Free shipping does not apply to freight,</b> and free-shipping coupons do not cover freight charges.</li>
  </ul>

  <h2>4. Free shipping</h2>
  <p>We offer free U.S. ground shipping on orders over <b>${{ $min }}</b> shipped within the contiguous (lower 48) United States.</p>
  <ul>
    <li><b>Custom cut tubing and complete driveshafts</b> (premade, short couple, unwelded assemblies, vehicle-specific driveshafts and shafts built through the online builder) do not count toward the ${{ $min }} and never ship free. When the rest of your order qualifies, those parts ship free and you pay only the carrier rate for the tube and driveshaft boxes, shown as its own option at checkout.</li>
    <li>Orders that ship by freight on a pallet never ship free.</li>
    <li>Free shipping is ground service. Expedited services, Alaska, Hawaii, U.S. territories, APO/FPO and international destinations are always charged at the quoted rate.</li>
  </ul>

  <h2>5. Local pickup</h2>
  <p>Choose local pickup at checkout to collect your order from our shop at no charge: {{ config('uds.pickup.label') }}. Pickup is available for U.S. orders. Please wait for our e-mail saying your order is ready before coming in.</p>

  <h2>6. International shipping</h2>
  <p>We ship internationally by UPS Worldwide and DHL Express at the rate quoted at checkout, with the customs paperwork prepared by us. International orders ship <b>DAP</b> (delivered at place): import duties, taxes, customs brokerage and any fees charged by the destination country are not included in our price and are paid by the recipient on delivery. Some long or heavy items cannot travel internationally as parcels; if your order shows no international rate, <a href="/contact">contact us</a> for a freight quote.</p>

  <h2>7. Addresses, tracking and delivery problems</h2>
  <p>UPS and FedEx do not deliver to P.O. boxes; please give a street address. Once your order ships we e-mail you the tracking number (and text it, if you opted in to texts), and you can follow it from your <a href="/account">account</a> or our <a href="/track-order">order tracking page</a>.</p>
  <p>Inspect every shipment on arrival and report shortages, wrong items or shipping damage within <b>5 business days</b> of delivery, with photographs of the packaging, label and product. Keep all packaging until the claim is resolved. See <a href="/return-policy#damage">Shortages, wrong items and shipping damage</a> in our Return Policy.</p>

  <h2>8. Contact</h2>
  <p>Questions about shipping a specific order? Call (813) 524-6491 or e-mail <a href="mailto:inquiry@universaldriveshafts.com">inquiry@universaldriveshafts.com</a>.</p>
</div></section>
@endif
@endsection
