{{-- Shop styles live in public/css/uds-2026.css (see "SHOP" section). This partial only carries the parts-table
     rules keyed on data-col: the mobile card CSS there matches td[data-label="Part #"], which stops matching once the
     labels are translated (FR "N° de pièce"), so the same rules are repeated on the language-neutral data-col. --}}
@once
<style>
@media (max-width:760px){
  /* phones: a compact card (audit 2026-11-11: 182 u-joints made a 23,000 px page). Photo, part number, price and
     Add on top; the specs run on as one wrapped line (label + value); long text (notes, description, fits) last. */
  .ptbl tbody tr{display:flex;flex-wrap:wrap;align-items:baseline;gap:0;position:relative;padding:.7rem .75rem .75rem;margin-bottom:.5rem}
  .ptbl tbody td{order:10;flex:0 0 auto;padding:0 .85rem .2rem 0;font-size:.82rem;line-height:1.35}
  .ptbl tbody td::before{display:inline;margin:0 .3rem 0 0;font-size:.6rem}
  .ptbl tbody td[data-col="pic"]{position:absolute;top:.7rem;left:.75rem;padding:0}
  .ptbl tbody td[data-col="pic"] .p-pic,.ptbl tbody td[data-col="pic"] .p-pic-ph{width:64px;height:64px;min-width:64px}
  .ptbl tbody td[data-col="part"]{order:1;flex:0 0 100%;min-height:28px;padding:0 6.6rem 0 76px}
  .ptbl tbody td[data-col="price"]{order:2;flex:0 0 100%;min-height:44px;padding:.1rem 6.6rem .4rem 76px}
  .ptbl tbody td[data-col="add"]{position:absolute;top:.7rem;right:.75rem;padding:0}
  .ptbl tbody td.l{order:20;flex:0 0 100%;padding:.15rem 0 0}
  .ptbl tbody td[data-empty],.ptbl tbody td:has(> .p-muted:only-child){display:none}
  .ptbl tbody td:is([data-col="pic"],[data-col="part"],[data-col="price"],[data-col="add"])::before{display:none}
  .ptbl tbody td[data-col="part"] .p-pn{font-size:1rem;word-break:break-all}
  .ptbl tbody td[data-col="price"] .p-price{justify-content:flex-start;white-space:normal}
  .ptbl tbody td[data-col="price"] .now{font-size:1.05rem}
  .ptbl tbody td[data-col="add"] .p-add{display:flex;align-items:center;gap:.5rem}
  .ptbl tbody td[data-col="add"] .p-plus,.ptbl tbody td[data-col="add"] .p-call{justify-content:center;height:40px;min-height:40px}
  .ptbl tbody td[data-col="add"] .p-plus{width:auto;padding:0 .85rem;gap:.4rem;font:600 .86rem/1 var(--font-b)}
  .ptbl tbody td[data-col="add"] .p-plus .p-plus__t{display:inline}
  .ptbl tbody td[data-col="add"] .p-call{padding:0 .7rem;font-size:.8rem;max-width:6.2rem;white-space:normal;line-height:1.1}
}
@media (min-width:761px){
  .ptfit.is-cards .ptbl tbody td[data-col="pic"]{grid-column:1;grid-row:1/3;padding:0}
  .ptfit.is-cards .ptbl tbody td[data-col="part"]{grid-column:2;grid-row:1;align-self:end;padding:0}
  .ptfit.is-cards .ptbl tbody td[data-col="price"]{grid-column:2;grid-row:2;align-self:start;padding:.15rem 0 .35rem}
  .ptfit.is-cards .ptbl tbody td[data-col="notes"]{grid-column:1/-1}
  .ptfit.is-cards .ptbl tbody td.l{min-width:0}
  .ptfit.is-cards .ptbl tbody td:is([data-col="desc"],[data-col="fits"]){grid-column:1/-1}
  .ptfit.is-cards .ptbl tbody td[data-col="add"]{grid-column:1/-1;order:99;padding-top:.75rem}
  .ptfit.is-cards .ptbl tbody td:is([data-col="pic"],[data-col="part"],[data-col="price"],[data-col="add"])::before{display:none}
  .ptfit.is-cards .p-pic,.ptfit.is-cards .p-pic-ph{width:96px;height:96px;min-width:96px}
  .ptfit.is-cards .ptbl tbody td[data-col="part"] .p-pn{font-size:1rem;word-break:break-all}
  .ptfit.is-cards .ptbl tbody td[data-col="add"] .p-add{display:flex;gap:.5rem}
  .ptfit.is-cards .ptbl tbody td[data-col="add"] .p-plus{flex:1;justify-content:center;height:40px}
}
/* primary add: cart glyph + "Add" on wide screens; every action ≥ 40px; alternates as a small text link */
.p-add .p-plus .p-plus__t{display:none}
@media (min-width:761px){
  .p-add .p-plus{width:auto;padding:0 .8rem;gap:.35rem;font:600 .82rem/1 var(--font-b)}
  .p-add .p-plus .p-plus__t{display:inline}
}
.p-add .p-call{display:inline-flex;align-items:center;min-height:44px;padding:0 .7rem;border:1.5px solid var(--line-strong);border-radius:var(--r-sm);
  background:#fff;color:var(--ink);font:600 .8rem/1.1 var(--font-b);cursor:pointer;white-space:nowrap}
.p-add .p-call:hover{border-color:var(--blue);color:var(--blue)}
.p-price .call{font-weight:600;color:var(--ink-2);font-size:.85rem;font-family:var(--font-b)}
button.p-alt{background:none;padding:0;margin-left:.5rem;min-height:40px;color:var(--warn);text-decoration:underline;text-underline-offset:2px;font:600 .74rem/1.2 var(--font-b)}
button.p-alt .i{display:none}
button.p-alt span{display:inline}
.xref-sec td[data-col="xref_num"]{font-family:var(--font-m);font-weight:600;color:var(--blue);white-space:nowrap}
/* long notes wrap instead of pushing the table past its container */
@media (min-width:761px){
  .ptbl td.l{min-width:16ch}
  .ptbl .p-notes{display:inline-block;max-width:24ch;white-space:normal;overflow-wrap:break-word}
  .ptbl-wrap{position:relative}
  .ptbl-wrap.is-scroll{-webkit-mask-image:linear-gradient(to right,#000 calc(100% - 40px),transparent);mask-image:linear-gradient(to right,#000 calc(100% - 40px),transparent)}
}
</style>
@endonce
