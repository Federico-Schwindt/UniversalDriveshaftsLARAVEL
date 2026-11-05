{{-- Shop styles live in public/css/uds-2026.css (see "SHOP" section). This partial only carries the parts-table
     rules keyed on data-col: the mobile card CSS there matches td[data-label="Part #"], which stops matching once the
     labels are translated (FR "N° de pièce"), so the same rules are repeated on the language-neutral data-col. --}}
@once
<style>
@media (max-width:760px){
  .ptbl tbody td[data-col="add"],.ptbl tbody td[data-col="pic"]{display:flex;align-items:center;border-bottom:0!important;padding:0 0 .75rem}
  .ptbl tbody td[data-col="add"]{grid-column:1;justify-content:flex-start}
  .ptbl tbody td[data-col="pic"]{grid-column:2;justify-content:flex-end}
  .ptbl tbody td[data-col="add"]::before,.ptbl tbody td[data-col="pic"]::before{display:none}
  .ptbl tbody td[data-col="pic"] .p-pic,.ptbl tbody td[data-col="pic"] .p-pic-ph{width:104px;height:104px;min-width:104px}
  .ptbl tbody td[data-col="part"]{border-top:1px solid var(--line-2)!important;padding-top:.7rem;grid-template-columns:auto minmax(0,1fr)}
  .ptbl tbody td[data-col="part"] .p-pn{white-space:normal;word-break:break-all;font-size:1rem;display:inline-flex;justify-content:flex-end;grid-column:2;justify-self:end}
  .ptbl tbody td[data-col="part"] .p-alt{grid-column:2;justify-self:end;margin:6px 0 0}
  .ptbl tbody td[data-col="price"] .p-price{justify-content:flex-end;white-space:normal}
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
