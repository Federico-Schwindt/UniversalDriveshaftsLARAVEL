@extends('layouts.app')
@section('title', app()->getLocale() === 'es' ? 'Política de devoluciones, reembolsos y garantía | Universal Driveshafts' : __('Return, Refund & Warranty Policy | Universal Driveshafts'))
@section('desc', app()->getLocale() === 'es' ? 'Devoluciones, cambios, cancelaciones y garantía limitada de 12 meses de Universal Driveshafts: devoluciones de piezas de stock en 14 días con RMA, cómo funcionan los reembolsos, fabricaciones a medida y daños de envío.' : __('Universal Driveshafts returns, exchanges, cancellations and 12-month limited warranty: 14-day returns on stock parts with an RMA, how refunds work, custom builds and shipping damage.'))
@section('content')
@if(app()->getLocale() === 'es')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">Inicio</a><span class="sep">/</span><span>Devoluciones y garantía</span></div><div class="accentline"></div>
<h1>Política de devoluciones, reembolsos y garantía limitada</h1></div></section>

<section class="section"><div class="wrap prose legal" style="--measure:860px">
<style>
.legal h2{margin-top:34px;padding-top:10px;border-top:1px solid var(--line)}
.legal .box{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin:18px 0}
.legal .caps{text-transform:uppercase;font-weight:700;letter-spacing:.01em}
.legal table{width:100%;border-collapse:collapse;font-size:.93rem;margin:14px 0}
.legal td,.legal th{border:1px solid var(--line);padding:9px 12px;text-align:left;vertical-align:top}
.legal th{background:var(--bg-soft)}
.legal .glance{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:18px 0 6px}
.legal .glance div{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
.legal .glance b{display:block;font-family:var(--font-h);font-size:1.25rem;line-height:1.2;color:var(--ink)}
.legal .glance span{font-size:.88rem;color:var(--ink-2)}
.legal .paths{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0}
.legal .paths a{display:block;border:1px solid var(--line);border-radius:12px;padding:14px 16px;text-decoration:none;color:inherit;transition:border-color .15s,box-shadow .15s}
.legal .paths a:hover{border-color:var(--blue-200);box-shadow:var(--sh-2)}
.legal .paths a:focus-visible{outline:3px solid var(--blue-100);outline-offset:2px}
.legal .paths b{display:block;color:var(--blue-700);margin-bottom:3px}
.legal .paths span{font-size:.88rem;color:var(--ink-2)}
.legal details{border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin:8px 0}
.legal summary{cursor:pointer;font-weight:600}
.legal details p{margin:8px 0 2px}
.legal-tr{background:var(--warn-bg);border:1px solid #ead9b5;border-radius:12px;padding:12px 16px;margin:0 0 18px;font-size:.92rem;color:var(--ink-2)}
</style>
  <p class="legal-tr">Esta es una traducción de cortesía. En caso de discrepancia, prevalece la versión en inglés.</p>
  <p><b>Universal Driveshafts LLC</b> (“<b>UDS</b>”)<br><span style="color:var(--ink-faint)">Fecha de entrada en vigor: 1 de noviembre de 2026</span></p>
  <p>Queremos que su vehículo tenga la pieza correcta. Esta política explica cómo funcionan las devoluciones, los cambios, las cancelaciones, los reembolsos y los reclamos de garantía. Forma parte de nuestros <a href="/terms">Términos de uso y Condiciones de venta</a>.</p>

  <div class="glance" role="list" aria-label="Resumen de la política">
    <div role="listitem"><b>14 días</b><span>para devolver piezas de stock nuevas y sin instalar, con un número de RMA</span></div>
    <div role="listitem"><b>20%</b><span>de cargo de reposición en devoluciones de piezas de stock; el envío original no se reembolsa</span></div>
    <div role="listitem"><b>No retornables</b><span>fabricaciones a medida, artículos cortados a medida y artículos fabricados por pedido</span></div>
    <div role="listitem"><b>5 días hábiles</b><span>para informar daños, faltantes o un artículo equivocado, que corregimos a nuestro cargo</span></div>
    <div role="listitem"><b>12 meses</b><span>de garantía limitada en productos de marca UDS</span></div>
  </div>

  <h2 id="start" style="border-top:0;margin-top:22px">Empiece aquí</h2>
  <div class="paths">
    <a href="/return-request-form"><b>Devolver una pieza →</b><span>Pieza de stock sin usar, dentro de los 14 días de la entrega</span></a>
    <a href="/exchange-request-form"><b>Cambiar una pieza →</b><span>Cambio por otra pieza o medida</span></a>
    <a href="/product-cancel-request-form"><b>Cancelar un pedido →</b><span>Pedidos de stock, antes de que se envíen</span></a>
    <a href="/contact"><b>Dañado, equivocado o garantía →</b><span>Envíe fotos y su número de pedido</span></a>
  </div>

  <h2>1. Resumen</h2>
  <table>
    <tr><th style="width:38%">Artículo</th><th>¿Se puede devolver?</th></tr>
    <tr><td>Piezas de stock (crucetas, yugos, rodamientos, tornillería, tubos cortados en largos estándar)</td><td>Sí, dentro de los <b>14 días</b> de la entrega, nuevas, sin instalar, en su embalaje original y con un número de RMA. <b>Cargo de reposición del 20%</b>; el envío original no es reembolsable.</td></tr>
    <tr><td>Cardanes y ensambles fabricados a medida, cortados a medida, balanceados o fabricados por pedido</td><td><b>No.</b> Se fabrican según sus especificaciones; no se pueden cancelar una vez iniciada la fabricación y no son retornables (consulte la Sección 4).</td></tr>
    <tr><td>Artículos de pedido especial / envío directo que normalmente no tenemos en stock</td><td>Solo si el proveedor acepta la devolución; se aplica el cargo de reposición del proveedor (normalmente del 25 al 35%) más el flete.</td></tr>
    <tr><td>Artículos eléctricos, kits con bolsas selladas abiertas, lubricantes, artículos en liquidación y de “venta final”</td><td><b>No.</b></td></tr>
    <tr><td>Artículos instalados, usados, modificados, soldados, pintados, dañados o incompletos</td><td><b>No.</b></td></tr>
    <tr><td>Artículo equivocado o dañado en tránsito (error nuestro)</td><td>Sí, infórmelo dentro de los <b>5 días hábiles</b>; cubrimos el envío de devolución y lo reemplazamos o reembolsamos en su totalidad (consulte la Sección 5).</td></tr>
  </table>

  <h2>2. Cómo devolver una pieza de stock (se requiere RMA)</h2>
  <ol>
    <li>Solicite una Autorización de Devolución de Mercadería (RMA) dentro de los 14 días de la entrega desde su <a href="/account">cuenta</a>, nuestro <a href="/return-request-form">formulario de solicitud de devolución</a> o comunicándose con nosotros con su número de pedido, el número de parte y el motivo.</li>
    <li>Le enviaremos por correo electrónico su número de RMA y las instrucciones de devolución. <b>Las devoluciones sin número de RMA, o recibidas más de 15 días después de emitida la RMA, pueden ser rechazadas o devueltas a usted a su cargo.</b></li>
    <li>Embale la pieza en su embalaje original y sin daños, incluya una copia de la factura y escriba el número de RMA en el exterior de la caja. No escriba ni pegue cinta sobre la caja del producto.</li>
    <li>Envíela con flete prepagado y asegurada a la dirección que figura en la RMA. El envío de devolución corre por su cuenta, salvo que la devolución se deba a un error nuestro. Usted asume el riesgo de pérdida hasta que recibamos la mercadería.</li>
  </ol>

  <h2>3. Inspección, reembolsos y cargos</h2>
  <p>Los artículos devueltos se inspeccionan al recibirlos. Los artículos que se encuentren nuevos, sin instalar, completos y en condiciones de reventa se reembolsan al <b>medio de pago original</b>, menos un <b>cargo de reposición del 20%</b> y menos el envío original de salida (incluido cualquier envío que hayamos absorbido en una promoción de envío gratis), normalmente dentro de los 10 días hábiles posteriores a su recepción. Los artículos que presenten marcas de instalación, marcas de herramientas, grasa, óxido, tornillería faltante o embalaje dañado pueden ser rechazados, devueltos a su cargo o reembolsados por un valor reducido, a nuestra discreción. Los reembolsos de pedidos pagados con crédito comercial se emiten como crédito en la cuenta. No reembolsamos mano de obra, remolque, alquileres, tiempo de inactividad ni ningún otro costo que exceda el precio de la pieza.</p>

  <h2>4. Fabricaciones a medida y artículos fabricados por pedido</h2>
  <p>Los cardanes a medida, los tubos cortados a medida, los ensambles balanceados, los ejes de conversión y cualquier artículo fabricado o pedido según las especificaciones que usted proporcionó <b>no se pueden cancelar una vez iniciada la fabricación o el pedido, y no son retornables</b>. Usted es el único responsable de la exactitud de cada medida y especificación que proporciona; utilice nuestras <a href="/shop">guías de medición</a> y llámenos ante cualquier duda <i>antes</i> de hacer el pedido. Una fabricación que no encaja debido a una medida, serie, yugo o selección de estrías que usted proporcionó no constituye un defecto. Si fabricamos algo que se aparta de las especificaciones que usted proporcionó, lo corregiremos o lo volveremos a fabricar sin cargo; esa es su reparación exclusiva.</p>

  <h2>4b. Cargos de envío en devoluciones</h2>
  <p>Los cargos de envío originales no se reembolsan en las devoluciones, salvo que la devolución se deba a un error nuestro. La forma en que se embalan y cotizan los pedidos, los envíos por flete y las reglas de envío gratis se explican en nuestra <a href="/shipping-policy">Política de envíos</a>.</p>

  <h2 id="damage">5. Faltantes, artículos equivocados y daños de envío</h2>
  <p>Inspeccione cada envío al recibirlo. Infórmenos los faltantes, los artículos incorrectos o los daños dentro de los <b>5 días hábiles</b> posteriores a la entrega, con su número de pedido y fotografías del embalaje, la etiqueta y el producto. Si el error es nuestro, le enviaremos una etiqueta de devolución prepagada y le enviaremos el artículo correcto o le emitiremos un reembolso total. Los daños en tránsito también deben informarse al transportista cuando sea posible; conserve todo el embalaje hasta que se resuelva el reclamo. Los reclamos realizados después de los 5 días hábiles pueden ser rechazados en la medida en que lo permita la ley.</p>

  <h2 id="warranty">6. Garantía limitada, productos de marca UDS</h2>
  <div class="box">
  <p style="margin-top:0"><b>Qué cubre.</b> UDS garantiza al comprador original que los productos de marca UDS (productos identificados como “Brand: UDS” o que llevan un número de parte de UDS) estarán libres de defectos de materiales y de fabricación en condiciones normales de uso y servicio durante <b>doce (12) meses a partir de la fecha de entrega</b>. Además, se garantiza que los cardanes fabricados a medida se fabricaron según las especificaciones que usted proporcionó y se balancearon con la tolerancia estándar de la industria al momento del envío.</p>
  <p><b>Reparación exclusiva.</b> Si durante el período de garantía aparece un defecto cubierto y usted sigue el procedimiento de reclamo que se indica a continuación, UDS, a su elección, reparará el producto, lo reemplazará por el mismo producto o uno equivalente, o reembolsará el precio de compra. <b>Esta es su única y exclusiva reparación.</b> Los productos reparados o reemplazados están garantizados por el resto del período original.</p>
  <p style="margin-bottom:0"><b>Qué no cubre.</b> El desgaste normal; la falta de lubricación o mantenimiento; la instalación, la fase, el torque o la alineación incorrectos; el funcionamiento por encima del torque, la velocidad, el ángulo o la carga nominales; carreras, competencias, uso todoterreno, remolque por encima de la capacidad nominal del vehículo, vehículos elevados o modificados; los daños por accidente, abuso, negligencia, corrosión, calor, contaminación o residuos de la carretera; las vibraciones causadas por ángulos, componentes acoplados, el estado del vehículo o la instalación; los productos alterados, soldados, mecanizados, pintados o reparados por cualquier persona que no sea UDS; los productos instalados en un vehículo distinto de la aplicación para la que se pidieron; los productos comprados a cualquier persona que no sea UDS; los consumibles; y cualquier costo de mano de obra, desmontaje, instalación, remolque, alquiler, flete, tiempo de inactividad o costo consecuente.</p>
  </div>
  <p><b>Los productos de marcas de terceros</b> (por ejemplo, artículos identificados como Spicer<sup>®</sup>, Neapco<sup>®</sup> u otra marca de fabricante) solo están cubiertos por la garantía propia de ese fabricante, si la hubiera. UDS no otorga ninguna garantía propia sobre esos productos, pero lo ayudará a presentar un reclamo ante el fabricante.</p>
  <p><b>Cómo presentar un reclamo de garantía.</b> Comuníquese con nosotros dentro del período de garantía con su número de pedido, el número de parte, una descripción de la falla, fotografías y los datos del vehículo o la aplicación. Emitiremos una RMA. Devuelva el producto con flete prepagado; si el reclamo se aprueba, le reembolsaremos un envío terrestre de devolución razonable dentro de los Estados Unidos continentales. Los productos deben devolverse completos y sin desarmar para su inspección. El análisis de la falla queda a criterio exclusivo y razonable de UDS. Los productos devueltos sin RMA, o que no se encuentren defectuosos, pueden ser devueltos a usted a su cargo.</p>
  <p class="caps">Esta garantía limitada es la única garantía expresa otorgada por UDS. En la máxima medida permitida por la ley, cualquier garantía implícita, incluidas las de comerciabilidad y de idoneidad para un fin determinado, se limita en su duración al período de doce meses indicado arriba, y UDS no es responsable por daños incidentales o consecuentes. Algunos estados no permiten limitaciones sobre la duración de una garantía implícita ni la exclusión de daños incidentales o consecuentes, por lo que es posible que estas limitaciones no se apliquen a usted. Esta garantía le otorga derechos legales específicos, y es posible que también tenga otros derechos que varían de un estado a otro.</p>

  <h2 id="exchanges">7. Cascos (cores), depósitos, cambios y cancelaciones</h2>
  <p>Cuando se aplica un cargo por casco (core), se reembolsa al recibir un casco completo y reconstruible del mismo tipo dentro de los 30 días. Los cascos agrietados, soldados, trabados o incompletos no son elegibles.</p>
  <p><b>Cambios.</b> Para cambiar una pieza de stock por otra, envíe una <a href="/exchange-request-form">solicitud de cambio</a> con su número de pedido; le responderemos con un número de RMA y las instrucciones.</p>
  <p><b>Cancelaciones.</b> Los pedidos de stock pueden cancelarse antes de su envío, mediante nuestro <a href="/product-cancel-request-form">formulario de solicitud de cancelación</a> o llamándonos. Los artículos fabricados a medida y por pedido no pueden cancelarse una vez iniciada la fabricación o el pedido (consulte la Sección 4).</p>

  <h2>8. Contracargos</h2>
  <p>Comuníquese con nosotros antes de disputar un cargo con el emisor de su tarjeta; resolvemos directamente casi todos los problemas. Los contracargos presentados sobre mercadería entregada y conforme, o mientras hay una RMA en curso, se impugnan con el comprobante de entrega y esta política, y pueden dar lugar a la suspensión de la cuenta y al recupero de los costos según lo previsto en nuestros Términos.</p>

  <h2 id="faq">Preguntas frecuentes</h2>
  <details><summary>¿Puedo devolver una pieza que ya instalé?</summary><p>No. Los artículos instalados, usados, modificados o dañados no son retornables. Si falló una pieza de marca UDS, puede estar cubierta por la garantía limitada de la Sección 6.</p></details>
  <details><summary>Pedí la medida equivocada en un cardán a medida. ¿Puedo devolverlo?</summary><p>No. Las fabricaciones a medida se hacen con las medidas que usted proporciona y no son retornables. Si lo fabricamos de manera distinta a sus especificaciones, lo corregiremos o lo volveremos a fabricar sin cargo (Sección 4).</p></details>
  <details><summary>¿Cuánto tarda un reembolso?</summary><p>Los artículos devueltos se inspeccionan al recibirlos y los reembolsos se acreditan en el medio de pago original, normalmente dentro de los 10 días hábiles posteriores a su recepción.</p></details>
  <details><summary>¿Quién paga el envío de devolución?</summary><p>Usted, salvo que la devolución se deba a un error nuestro (un artículo equivocado, un faltante o un daño de envío), en cuyo caso le enviamos una etiqueta de devolución prepagada. En un reclamo de garantía aprobado, reembolsamos un envío terrestre de devolución razonable dentro de los Estados Unidos continentales.</p></details>
  <details><summary>Mi pedido llegó dañado. ¿Qué hago?</summary><p>Fotografíe el embalaje, la etiqueta y el producto, conserve todo el embalaje y comuníquese con nosotros dentro de los 5 días hábiles posteriores a la entrega. En el caso de flete, anote el daño en el comprobante de entrega antes de firmar.</p></details>
  <details><summary>¿Se reembolsa el envío si devuelvo algo?</summary><p>No, el envío original no se reembolsa salvo que la devolución se deba a un error nuestro, incluido el envío que hayamos absorbido en una promoción de envío gratis.</p></details>

  <h2>9. Contacto</h2>
  <p>Universal Driveshafts LLC · Florida, USA<br>Teléfono (813) 524-6491 · <a href="mailto:inquiry@universaldriveshafts.com">inquiry@universaldriveshafts.com</a><br><a href="/return-request-form" class="btn btn-primary" style="margin-top:14px;display:inline-block">Iniciar una solicitud de devolución <x-icon name="arrow-right"/></a></p>
</div></section>
@elseif(i18n_view('legal.return-policy'))
@include(i18n_view('legal.return-policy'))
@else
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">Home</a><span class="sep">/</span><span>Returns &amp; Warranty</span></div><div class="accentline"></div>
<h1>Return, refund &amp; limited warranty policy</h1></div></section>

<section class="section"><div class="wrap prose legal" style="--measure:860px">
<style>
.legal h2{margin-top:34px;padding-top:10px;border-top:1px solid var(--line)}
.legal .box{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin:18px 0}
.legal .caps{text-transform:uppercase;font-weight:700;letter-spacing:.01em}
.legal table{width:100%;border-collapse:collapse;font-size:.93rem;margin:14px 0}
.legal td,.legal th{border:1px solid var(--line);padding:9px 12px;text-align:left;vertical-align:top}
.legal th{background:var(--bg-soft)}
.legal .glance{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:18px 0 6px}
.legal .glance div{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
.legal .glance b{display:block;font-family:var(--font-h);font-size:1.25rem;line-height:1.2;color:var(--ink)}
.legal .glance span{font-size:.88rem;color:var(--ink-2)}
.legal .paths{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0}
.legal .paths a{display:block;border:1px solid var(--line);border-radius:12px;padding:14px 16px;text-decoration:none;color:inherit;transition:border-color .15s,box-shadow .15s}
.legal .paths a:hover{border-color:var(--blue-200);box-shadow:var(--sh-2)}
.legal .paths a:focus-visible{outline:3px solid var(--blue-100);outline-offset:2px}
.legal .paths b{display:block;color:var(--blue-700);margin-bottom:3px}
.legal .paths span{font-size:.88rem;color:var(--ink-2)}
.legal details{border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin:8px 0}
.legal summary{cursor:pointer;font-weight:600}
.legal details p{margin:8px 0 2px}
</style>
  <p><b>Universal Driveshafts LLC</b> (“<b>UDS</b>”)<br><span style="color:var(--ink-faint)">Effective date: November 1, 2026</span></p>
  <p>We want the right part on your vehicle. This policy explains how returns, exchanges, cancellations, refunds and warranty claims work. It is part of our <a href="/terms">Terms of Use &amp; Conditions of Sale</a>.</p>

  <div class="glance" role="list" aria-label="Policy at a glance">
    <div role="listitem"><b>14 days</b><span>to return new, uninstalled stock parts, with an RMA number</span></div>
    <div role="listitem"><b>20%</b><span>restocking fee on stock-part returns; original shipping not refunded</span></div>
    <div role="listitem"><b>Not returnable</b><span>custom builds, cut-to-length and made-to-order items</span></div>
    <div role="listitem"><b>5 business days</b><span>to report damage, shortages or a wrong item, which we fix at our cost</span></div>
    <div role="listitem"><b>12 months</b><span>limited warranty on UDS-brand products</span></div>
  </div>

  <h2 id="start" style="border-top:0;margin-top:22px">Start here</h2>
  <div class="paths">
    <a href="/return-request-form"><b>Return a part →</b><span>Unused stock part, within 14 days of delivery</span></a>
    <a href="/exchange-request-form"><b>Exchange a part →</b><span>Swap for a different part or size</span></a>
    <a href="/product-cancel-request-form"><b>Cancel an order →</b><span>Stock orders, before they ship</span></a>
    <a href="/contact"><b>Damaged, wrong or warranty →</b><span>Send photos and your order number</span></a>
  </div>

  <h2>1. Summary</h2>
  <table>
    <tr><th style="width:38%">Item</th><th>Returnable?</th></tr>
    <tr><td>Stock parts (U-joints, yokes, bearings, hardware, tubing cut to standard lengths)</td><td>Yes, within <b>14 days</b> of delivery, new, uninstalled, in original packaging, with an RMA number. <b>20% restocking fee</b>; original shipping non-refundable.</td></tr>
    <tr><td>Custom-built, cut-to-length, balanced or made-to-order driveshafts and assemblies</td><td><b>No.</b> Built to your specifications; non-cancellable once fabrication begins and non-returnable (see Section 4).</td></tr>
    <tr><td>Special-order / drop-ship items not normally stocked</td><td>Only if the supplier accepts the return; supplier restocking fee (typically 25–35%) plus freight applies.</td></tr>
    <tr><td>Electrical items, kits with opened sealed bags, lubricants, clearance and “final sale” items</td><td><b>No.</b></td></tr>
    <tr><td>Installed, used, modified, welded, painted, damaged, or incomplete items</td><td><b>No.</b></td></tr>
    <tr><td>Wrong item or damaged in transit (our error)</td><td>Yes, report within <b>5 business days</b>; we cover return shipping and replace or refund in full (see Section 5).</td></tr>
  </table>

  <h2>2. How to return a stock part (RMA required)</h2>
  <ol>
    <li>Request a Return Merchandise Authorization (RMA) within 14 days of delivery from your <a href="/account">account</a>, our <a href="/return-request-form">return request form</a>, or by contacting us with your order number, part number and reason.</li>
    <li>We will e-mail your RMA number and return instructions. <b>Returns without an RMA number, or received more than 15 days after the RMA is issued, may be refused or returned to you at your cost.</b></li>
    <li>Pack the part in its original, undamaged packaging, include a copy of the invoice, and write the RMA number on the outside of the box. Do not write on or tape the product box itself.</li>
    <li>Ship prepaid and insured to the address on the RMA. Return shipping is at your expense unless the return is due to our error. You bear the risk of loss until we receive the goods.</li>
  </ol>

  <h2>3. Inspection, refunds and fees</h2>
  <p>Returned items are inspected on receipt. Items found new, uninstalled, complete and resalable are refunded to the <b>original payment method</b>, less a <b>20% restocking fee</b> and less original outbound shipping (including any shipping we absorbed under a free-shipping promotion), typically within 10 business days of receipt. Items that show installation marks, tool marks, grease, rust, missing hardware or damaged packaging may be refused, returned at your expense, or refunded at a reduced value at our discretion. Refunds for orders paid on trade credit are issued as account credit. We do not refund labor, towing, rental, downtime, or any other cost beyond the price of the part.</p>

  <h2>4. Custom builds and made-to-order items</h2>
  <p>Custom driveshafts, cut-to-length tubing, balanced assemblies, conversion shafts and any item built or ordered to the specifications you provided are <b>non-cancellable once fabrication or ordering begins and are non-returnable</b>. You are solely responsible for the accuracy of every measurement and specification you supply; please use our <a href="/shop">measuring guides</a> and call us with any doubt <i>before</i> ordering. A build that does not fit because of a measurement, series, yoke or spline selection you provided is not a defect. If we made a build that departs from the specifications you supplied, we will correct or rebuild it at no charge, that is your exclusive remedy.</p>

  <h2>4b. Shipping charges on returns</h2>
  <p>Original shipping charges are not refunded on returns unless the return is due to our error. How orders are packed and priced, freight shipments and the free-shipping rules are explained in our <a href="/shipping-policy">Shipping Policy</a>.</p>

  <h2 id="damage">5. Shortages, wrong items and shipping damage</h2>
  <p>Inspect every shipment on arrival. Report shortages, incorrect items or damage to us within <b>5 business days</b> of delivery with your order number and photographs of the packaging, label and product. For our error we will send a prepaid return label and ship the correct item or issue a full refund. Damage in transit must also be noted with the carrier where possible; keep all packaging until the claim is resolved. Claims made after 5 business days may be denied to the extent permitted by law.</p>

  <h2 id="warranty">6. Limited warranty, UDS-brand products</h2>
  <div class="box">
  <p style="margin-top:0"><b>What is covered.</b> UDS warrants to the original purchaser that UDS-brand products (products labeled “Brand: UDS” or bearing a UDS part number) will be free from defects in materials and workmanship under normal use and service for <b>twelve (12) months from the date of delivery</b>. Custom-built driveshafts are additionally warranted to have been built to the specifications you supplied and balanced to industry-standard tolerance at the time of shipment.</p>
  <p><b>Exclusive remedy.</b> If a covered defect appears during the warranty period and you follow the claim procedure below, UDS will, at its option, repair the product, replace it with the same or an equivalent product, or refund the purchase price. <b>This is your sole and exclusive remedy.</b> Repaired or replaced products are warranted for the remainder of the original period.</p>
  <p style="margin-bottom:0"><b>What is not covered.</b> Normal wear; failure to lubricate or maintain; improper installation, phasing, torque or alignment; operation beyond rated torque, speed, angle or load; racing, competition, off-road, towing beyond vehicle rating, lifted or modified vehicles; damage from accident, abuse, neglect, corrosion, heat, contamination or road debris; vibration caused by angles, mating components, vehicle condition or installation; products altered, welded, machined, painted or repaired by anyone other than UDS; products installed on a vehicle other than the application for which they were ordered; products purchased from anyone other than UDS; consumables; and any labor, removal, installation, towing, rental, freight, downtime or consequential cost.</p>
  </div>
  <p><b>Third-party brand products</b> (for example items labeled Spicer<sup>®</sup>, Neapco<sup>®</sup> or another manufacturer’s brand) are covered only by that manufacturer’s own warranty, if any. UDS makes no warranty of its own on those products but will assist you in submitting a claim to the manufacturer.</p>
  <p><b>How to make a warranty claim.</b> Contact us within the warranty period with your order number, part number, a description of the failure, photographs, and the vehicle/application details. We will issue an RMA. Return the product prepaid; if the claim is approved we will reimburse reasonable ground return shipping within the continental U.S. Products must be returned complete and un-disassembled for inspection. Failure analysis is at UDS’s sole reasonable discretion. Products returned without an RMA, or found not defective, may be returned to you at your expense.</p>
  <p class="caps">This limited warranty is the only express warranty given by UDS. To the fullest extent permitted by law, any implied warranties, including merchantability and fitness for a particular purpose, are limited in duration to the twelve-month period above, and UDS is not liable for incidental or consequential damages. Some states do not allow limitations on how long an implied warranty lasts or the exclusion of incidental or consequential damages, so these limitations may not apply to you. This warranty gives you specific legal rights, and you may also have other rights which vary from state to state.</p>

  <h2 id="exchanges">7. Cores, deposits, exchanges and cancellations</h2>
  <p>Where a core charge applies it is refunded on receipt of a complete, rebuildable core of the same type within 30 days. Cores that are cracked, welded, seized or incomplete are not eligible.</p>
  <p><b>Exchanges.</b> To swap a stock part for a different one, submit an <a href="/exchange-request-form">exchange request</a> with your order number; we will reply with an RMA number and instructions.</p>
  <p><b>Cancellations.</b> Stock orders may be cancelled before they ship, using our <a href="/product-cancel-request-form">cancellation request form</a> or by calling us. Custom-built and made-to-order items cannot be cancelled once fabrication or ordering begins (see Section 4).</p>

  <h2>8. Chargebacks</h2>
  <p>Please contact us before disputing a charge with your card issuer, we resolve nearly every issue directly. Chargebacks filed on delivered, conforming goods, or while an RMA is in progress, are contested with proof of delivery and this policy, and may result in account suspension and recovery of costs as provided in our Terms.</p>

  <h2 id="faq">Frequently asked questions</h2>
  <details><summary>Can I return a part I already installed?</summary><p>No. Installed, used, modified or damaged items are not returnable. If a UDS-brand part failed, it may be covered by the limited warranty in Section 6.</p></details>
  <details><summary>I ordered the wrong size on a custom driveshaft. Can I return it?</summary><p>No. Custom builds are made to the measurements you supply and are non-returnable. If we built it differently from your specifications, we will correct or rebuild it at no charge (Section 4).</p></details>
  <details><summary>How long does a refund take?</summary><p>Returned items are inspected on receipt and refunds go to the original payment method, typically within 10 business days of receipt.</p></details>
  <details><summary>Who pays return shipping?</summary><p>You do, unless the return is due to our error (a wrong item, a shortage or shipping damage), in which case we send a prepaid return label. For an approved warranty claim we reimburse reasonable ground return shipping within the continental U.S.</p></details>
  <details><summary>My order arrived damaged. What do I do?</summary><p>Photograph the packaging, label and product, keep all packaging, and contact us within 5 business days of delivery. For freight, note the damage on the delivery receipt before signing.</p></details>
  <details><summary>Is shipping refunded if I return something?</summary><p>No, original shipping is not refunded unless the return is due to our error, including shipping we absorbed under a free-shipping promotion.</p></details>

  <h2>9. Contact</h2>
  <p>Universal Driveshafts LLC · Florida, USA<br>Phone (813) 524-6491 · <a href="mailto:inquiry@universaldriveshafts.com">inquiry@universaldriveshafts.com</a><br><a href="/return-request-form" class="btn btn-primary" style="margin-top:14px;display:inline-block">Start a return request <x-icon name="arrow-right"/></a></p>
</div></section>
@endif
@endsection
