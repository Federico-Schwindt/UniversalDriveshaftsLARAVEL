@extends('layouts.app')
@section('title', app()->getLocale() === 'es' ? 'Aviso de derechos de autor, marcas y DMCA | Universal Driveshafts' : __('Copyright, Trademark & DMCA Notice | Universal Driveshafts'))
@section('desc', app()->getLocale() === 'es' ? 'Aviso de derechos de autor, marcas y DMCA de Universal Driveshafts: nuestra propiedad intelectual, marcas de terceros usadas solo como referencia cruzada y cómo presentar un aviso.' : __('Copyright, trademark and DMCA notice for Universal Driveshafts: our IP, third-party trademarks used for cross-reference only, and how to file a notice.'))
@section('content')
@if(app()->getLocale() === 'es')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">Inicio</a><span class="sep">/</span><span>Derechos de autor y marcas</span></div><div class="accentline"></div>
<h1>Aviso de derechos de autor, marcas y DMCA</h1></div></section>

<section class="section"><div class="wrap prose legal" style="--measure:860px">
<style>
.legal h2{margin-top:34px;padding-top:10px;border-top:1px solid var(--line)}
.legal .box{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin:18px 0}
.legal .caps{text-transform:uppercase;font-weight:700;letter-spacing:.01em}
.legal-tr{background:var(--warn-bg);border:1px solid #ead9b5;border-radius:12px;padding:12px 16px;margin:0 0 18px;font-size:.92rem;color:var(--ink-2)}
</style>
  <p class="legal-tr">Esta es una traducción de cortesía. En caso de discrepancia, prevalece la versión en inglés.</p>
  <p><b>Universal Driveshafts LLC</b> (“<b>UDS</b>”)<br><span style="color:var(--ink-faint)">Fecha de entrada en vigor: 5 de septiembre de 2026</span></p>

  <h2>1. Nuestros derechos de autor</h2>
  <p>© {{ date('Y') }} Universal Driveshafts LLC. Todos los derechos reservados. El contenido de udshafts.com y universaldriveshafts.com, incluidos todos los textos, guías de medición, dibujos técnicos, diagramas, fotografías, imágenes de productos, videos, gráficos, íconos, diseño y maquetación de páginas, HTML/CSS/JavaScript, software, herramientas, calculadoras, resultados del diseñador y la <b>selección, coordinación, disposición y compilación</b> de nuestro catálogo de productos, sistema de números de parte y datos de aplicación, dimensiones e intercambio, es obra protegida por derechos de autor y/o compilación protegida de UDS o de sus licenciantes. Se prohíbe la reproducción, extracción automatizada (scraping), redistribución, enmarcado (framing) o uso no autorizados de cualquiera de ellos, incluida la copia de nuestros datos o imágenes de productos en otro sitio web, publicación en un marketplace, catálogo o base de datos, y dichas conductas serán perseguidas.</p>

  <h2>2. Nuestras marcas</h2>
  <p>UNIVERSAL DRIVESHAFTS, UDS, el logotipo de UDS, el emblema rojo y azul del cardán de UDS, nuestro esquema de números de parte (incluido el sufijo “U” que identifica las piezas de marca UDS) y nuestros eslóganes e imagen comercial (trade dress) son marcas comerciales o marcas de servicio de Universal Driveshafts LLC, estén o no registradas. No pueden utilizarse en relación con ningún producto o servicio que no sea nuestro, de ninguna manera que pueda causar confusión ni de ninguna manera que desprestigie a UDS. Nada de lo contenido en el Sitio otorga licencia alguna para usar ninguna marca de UDS.</p>

  <h2>3. Marcas y números de parte de terceros: uso exclusivo para referencia cruzada</h2>
  <div class="box">
  <p style="margin-top:0"><b>Spicer<sup>®</sup>, Dana<sup>®</sup>, Neapco<sup>®</sup>, Meritor<sup>®</sup>, GMB<sup>®</sup>, Precision<sup>®</sup>, Sonnax<sup>®</sup></b> y todos los demás nombres, logotipos, designaciones de serie y números de parte de fabricantes que aparecen en este Sitio, junto con las marcas y modelos de vehículos (incluidos Ford<sup>®</sup>, GM<sup>®</sup>, Chevrolet<sup>®</sup>, GMC<sup>®</sup>, Dodge<sup>®</sup>, Ram<sup>®</sup>, Jeep<sup>®</sup>, Toyota<sup>®</sup>, Nissan<sup>®</sup>, Honda<sup>®</sup>, BMW<sup>®</sup>, Mercedes-Benz<sup>®</sup>), son <b>marcas registradas o no registradas de sus respectivos titulares</b>.</p>
  <p>Se utilizan en este Sitio <b>únicamente con fines de identificación, descripción de compatibilidad e intercambio/referencia cruzada</b>, para que los clientes puedan encontrar una pieza de reemplazo para una aplicación determinada, lo que constituye un uso legítimo descriptivo y nominativo. <b>Universal Driveshafts LLC es una empresa independiente. No está afiliada, patrocinada, respaldada ni licenciada por ninguna de estas empresas, ni es distribuidora autorizada de ellas, salvo que se indique expresamente en la página del producto específico.</b> La aparición del nombre o del número de parte de un fabricante no significa que la pieza que vendemos haya sido fabricada por ese fabricante. Cuando nuestro catálogo indica una pieza como alternativa al número de otro fabricante, a nuestro juicio ambas piezas tienen un diseño y dimensiones comparables; no son idénticas, y el otro fabricante no ha certificado el intercambio.</p>
  <p style="margin-bottom:0">Los productos identificados expresamente con una marca de terceros (por ejemplo, “Brand: SPICER”) son productos genuinos de ese fabricante, adquiridos en el mercado abierto o a través de distribución autorizada, y revendidos por UDS. Los productos identificados como “Brand: UDS” o que llevan un número de parte de UDS son de nuestra propia marca.</p>
  </div>

  <h2>4. Imágenes y especificaciones de productos</h2>
  <p>Las fotografías de productos son propiedad de UDS o se utilizan con autorización, y son representativas del tipo de producto; el artículo enviado puede diferir en acabado, marcas, embalaje o detalles menores. Las dimensiones, pesos y especificaciones se recopilan de publicaciones de fabricantes, referencias de la industria y nuestras propias mediciones, y se proporcionan “tal cual” como referencia; no constituyen una garantía. Consulte la Sección 8 de nuestros <a href="/terms">Términos de uso</a>.</p>
  <p>Las sugerencias de ciudad y la búsqueda por código postal del checkout usan datos de <a href="https://www.geonames.org/" target="_blank" rel="noopener">GeoNames</a>, con licencia <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.</p>

  <h2>5. Aviso de presunta infracción de derechos de autor (DMCA)</h2>
  <p>UDS respeta la propiedad intelectual de terceros y cumple con la Digital Millennium Copyright Act (Ley de Derechos de Autor del Milenio Digital), 17 U.S.C. § 512. Si cree de buena fe que material de este Sitio infringe derechos de autor de los que usted es titular o que controla, envíe una notificación por escrito a nuestro Agente Designado que contenga <b>todos</b> los elementos siguientes (17 U.S.C. § 512(c)(3)):</p>
  <ol>
    <li>Identificación de la obra protegida por derechos de autor que usted afirma que ha sido infringida (o una lista representativa, si se trata de varias obras);</li>
    <li>Identificación del material que usted afirma que es infractor y su ubicación exacta en el Sitio (URL completa), con detalle suficiente para que podamos localizarlo;</li>
    <li>Su nombre legal completo, dirección postal, número de teléfono y dirección de correo electrónico;</li>
    <li>Una declaración de que usted cree de buena fe que el uso del material en la forma denunciada no está autorizado por el titular de los derechos de autor, su agente o la ley;</li>
    <li>Una declaración, hecha bajo pena de perjurio, de que la información de la notificación es exacta y de que usted es el titular de los derechos de autor o está autorizado a actuar en nombre del titular; y</li>
    <li>Su firma física o electrónica.</li>
  </ol>
  <div class="box"><p style="margin:0"><b>Agente Designado de DMCA</b><br>Copyright Agent, Universal Driveshafts LLC<br>Florida, USA<br>Correo electrónico: <a href="mailto:inquiry@universaldriveshafts.com?subject=DMCA%20Notice">inquiry@universaldriveshafts.com</a> (asunto: “DMCA Notice”)<br>Teléfono: (813) 524-6491</p></div>
  <p>Tenga en cuenta que, conforme a 17 U.S.C. § 512(f), toda persona que, a sabiendas, tergiverse de manera sustancial que un material es infractor puede ser responsable por daños y perjuicios, incluidos los costos y los honorarios de abogados. Es posible que no se tomen medidas respecto de notificaciones que no cumplan sustancialmente con los requisitos anteriores. Antes de presentar una notificación, considere si el uso es un uso legítimo permitido, como el uso descriptivo de un nombre o número de parte con fines de compatibilidad.</p>

  <h2>6. Contranotificación</h2>
  <p>Si un material que usted publicó fue eliminado en respuesta a una notificación y usted cree que la eliminación se debió a un error o a una identificación errónea, puede enviar a nuestro Agente Designado una contranotificación que contenga: su nombre, dirección, número de teléfono y correo electrónico; la identificación del material eliminado y su ubicación anterior; una declaración bajo pena de perjurio de que usted cree de buena fe que el material fue eliminado por error o por identificación errónea; una declaración de que usted acepta la jurisdicción del tribunal federal de distrito correspondiente a su domicilio (o, si se encuentra fuera de EE. UU., del Distrito Medio de Florida) y de que aceptará la notificación de actos procesales de la parte denunciante; y su firma física o electrónica. Podremos restablecer el material en un plazo de 10 a 14 días hábiles, salvo que la parte denunciante nos notifique que ha iniciado una acción judicial.</p>

  <h2>7. Infractores reincidentes</h2>
  <p>En las circunstancias que correspondan, cancelaremos las cuentas de los usuarios que se determine que son infractores reincidentes.</p>

  <h2>8. Reclamos por marcas</h2>
  <p>Si cree que una marca de la que usted es titular se está utilizando en este Sitio de una manera que excede el uso descriptivo de referencia cruzada y que probablemente cause confusión en cuanto al origen o la afiliación, envíe un correo electrónico a nuestro Agente Designado con el asunto “Trademark Inquiry”, identificando la marca y su número de registro, la URL exacta y el fundamento de su inquietud. La revisaremos y responderemos de buena fe.</p>

  <h2>9. Denuncia de infracciones del contenido de UDS</h2>
  <p>Si encuentra nuestras fotografías, guías, datos de catálogo u otro contenido utilizados en otro sitio web o publicación sin autorización, infórmenos a <a href="mailto:inquiry@universaldriveshafts.com?subject=UDS%20Content%20Misuse">inquiry@universaldriveshafts.com</a>. UDS hace valer activamente sus derechos.</p>

  <p style="margin-top:30px;color:var(--ink-faint);font-size:.9rem">Este aviso forma parte de nuestros <a href="/terms">Términos de uso y Condiciones de venta</a> y debe leerse junto con ellos.</p>
</div></section>
@elseif(i18n_view('legal.copyright'))
@include(i18n_view('legal.copyright'))
@else
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">Home</a><span class="sep">/</span><span>Copyright &amp; Trademarks</span></div><div class="accentline"></div>
<h1>Copyright, Trademark &amp; DMCA Notice</h1></div></section>

<section class="section"><div class="wrap prose legal" style="--measure:860px">
<style>
.legal h2{margin-top:34px;padding-top:10px;border-top:1px solid var(--line)}
.legal .box{background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin:18px 0}
.legal .caps{text-transform:uppercase;font-weight:700;letter-spacing:.01em}
</style>
  <p><b>Universal Driveshafts LLC</b> (“<b>UDS</b>”)<br><span style="color:var(--ink-faint)">Effective date: September 5, 2026</span></p>

  <h2>1. Our copyrights</h2>
  <p>© {{ date('Y') }} Universal Driveshafts LLC. All rights reserved. The content of udshafts.com and universaldriveshafts.com, including all text, measuring guides, technical drawings, diagrams, photographs, product images, videos, graphics, icons, page design and layout, HTML/CSS/JavaScript, software, tools, calculators, designer results, and the <b>selection, coordination, arrangement and compilation</b> of our product catalog, part-number system, application, dimension and interchange data, is the copyrighted work and/or protected compilation of UDS or its licensors. Unauthorized reproduction, scraping, redistribution, framing, or use of any of it, including copying our product data or images into another website, marketplace listing, catalog or database, is prohibited and will be pursued.</p>

  <h2>2. Our trademarks</h2>
  <p>UNIVERSAL DRIVESHAFTS, UDS, the UDS logo, the red-and-blue UDS driveshaft device, our part-number scheme (including the “U” suffix denoting UDS-brand parts), and our slogans and trade dress are trademarks or service marks of Universal Driveshafts LLC, whether or not registered. They may not be used in connection with any product or service that is not ours, in any manner likely to cause confusion, or in any manner that disparages UDS. Nothing on the Site grants any license to use any UDS mark.</p>

  <h2>3. Third-party trademarks and part numbers, cross-reference use only</h2>
  <div class="box">
  <p style="margin-top:0"><b>Spicer<sup>®</sup>, Dana<sup>®</sup>, Neapco<sup>®</sup>, Meritor<sup>®</sup>, GMB<sup>®</sup>, Precision<sup>®</sup>, Sonnax<sup>®</sup></b>, and all other manufacturer names, logos, series designations, and part numbers appearing on this Site, together with vehicle makes and models (including Ford<sup>®</sup>, GM<sup>®</sup>, Chevrolet<sup>®</sup>, GMC<sup>®</sup>, Dodge<sup>®</sup>, Ram<sup>®</sup>, Jeep<sup>®</sup>, Toyota<sup>®</sup>, Nissan<sup>®</sup>, Honda<sup>®</sup>, BMW<sup>®</sup>, Mercedes-Benz<sup>®</sup>), are the <b>registered or unregistered trademarks of their respective owners</b>.</p>
  <p>They are used on this Site <b>solely for the purposes of identification, description of compatibility, and interchange/cross-reference</b>, so that customers can locate a replacement part for a given application, a descriptive, nominative fair use. <b>Universal Driveshafts LLC is an independent company. It is not affiliated with, sponsored by, endorsed by, licensed by, or an authorized distributor of any of these companies unless expressly stated on the specific product page.</b> The appearance of a manufacturer’s name or part number does not mean that the part we sell was made by that manufacturer. Where our catalog lists a part as an alternate to another manufacturer’s number, the two parts are of comparable design and dimensions in our judgment; they are not identical, and the other manufacturer has not certified the interchange.</p>
  <p style="margin-bottom:0">Products expressly labeled with a third-party brand (for example “Brand: SPICER”) are genuine products of that manufacturer, purchased through the open market or authorized distribution, and resold by UDS. Products labeled “Brand: UDS” or bearing a UDS part number are our own brand.</p>
  </div>

  <h2>4. Product images and specifications</h2>
  <p>Product photographs are owned by UDS or used with permission and are representative of the product type; the item shipped may differ in finish, markings, packaging or minor detail. Dimensions, weights and specifications are compiled from manufacturer publications, industry references and our own measurements and are provided “as is” for reference; they are not a warranty. See Section 8 of our <a href="/terms">Terms of Use</a>.</p>
  <p>City suggestions and the ZIP code lookup at checkout use data from <a href="https://www.geonames.org/" target="_blank" rel="noopener">GeoNames</a>, licensed under <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.</p>

  <h2>5. Notice of claimed copyright infringement (DMCA)</h2>
  <p>UDS respects the intellectual property of others and complies with the Digital Millennium Copyright Act, 17 U.S.C. § 512. If you believe in good faith that material on this Site infringes a copyright you own or control, send a written notice to our Designated Agent containing <b>all</b> of the following (17 U.S.C. § 512(c)(3)):</p>
  <ol>
    <li>Identification of the copyrighted work you claim has been infringed (or a representative list if multiple works);</li>
    <li>Identification of the material you claim is infringing and its exact location on the Site (full URL), with enough detail for us to locate it;</li>
    <li>Your full legal name, mailing address, telephone number and e-mail address;</li>
    <li>A statement that you have a good-faith belief that use of the material in the manner complained of is not authorized by the copyright owner, its agent, or the law;</li>
    <li>A statement, made under penalty of perjury, that the information in the notice is accurate and that you are the copyright owner or authorized to act on the owner’s behalf; and</li>
    <li>Your physical or electronic signature.</li>
  </ol>
  <div class="box"><p style="margin:0"><b>Designated DMCA Agent</b><br>Copyright Agent, Universal Driveshafts LLC<br>Florida, USA<br>E-mail: <a href="mailto:inquiry@universaldriveshafts.com?subject=DMCA%20Notice">inquiry@universaldriveshafts.com</a> (subject line: “DMCA Notice”)<br>Phone: (813) 524-6491</p></div>
  <p>Please be aware that under 17 U.S.C. § 512(f), any person who knowingly materially misrepresents that material is infringing may be liable for damages, including costs and attorneys’ fees. Notices that do not substantially comply with the requirements above may not be acted upon. Before submitting a notice, consider whether the use is a permitted fair use, such as the descriptive use of a name or part number for compatibility purposes.</p>

  <h2>6. Counter-notice</h2>
  <p>If material you posted was removed in response to a notice and you believe the removal was a mistake or misidentification, you may send our Designated Agent a counter-notice containing: your name, address, phone number and e-mail; identification of the removed material and its former location; a statement under penalty of perjury that you have a good-faith belief the material was removed by mistake or misidentification; a statement that you consent to the jurisdiction of the federal district court for your address (or, if outside the U.S., the Middle District of Florida) and will accept service of process from the complaining party; and your physical or electronic signature. We may restore the material in 10–14 business days unless the complaining party notifies us that it has filed a court action.</p>

  <h2>7. Repeat infringers</h2>
  <p>We will terminate, in appropriate circumstances, the accounts of users who are determined to be repeat infringers.</p>

  <h2>8. Trademark complaints</h2>
  <p>If you believe a trademark you own is being used on this Site in a way that goes beyond descriptive cross-reference use and is likely to cause confusion as to source or affiliation, e-mail our Designated Agent with the subject line “Trademark Inquiry,” identify the mark and its registration number, the exact URL, and the basis of your concern. We will review and respond in good faith.</p>

  <h2>9. Reporting infringement of UDS content</h2>
  <p>If you find our photographs, guides, catalog data or other content being used on another website or listing without permission, please let us know at <a href="mailto:inquiry@universaldriveshafts.com?subject=UDS%20Content%20Misuse">inquiry@universaldriveshafts.com</a>. UDS actively enforces its rights.</p>

  <p style="margin-top:30px;color:var(--ink-faint);font-size:.9rem">This notice is part of, and should be read together with, our <a href="/terms">Terms of Use &amp; Conditions of Sale</a>.</p>
</div></section>
@endif
@endsection
