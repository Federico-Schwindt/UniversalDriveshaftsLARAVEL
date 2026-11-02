<style>
/* ===== UDS account portal — 2026-10 redesign, on the uds-2026 tokens ===== */
.acct-page{--acct-gap:24px;background:var(--paper-2);padding:28px 0 64px}
.acct-shell{display:grid;grid-template-columns:240px minmax(0,1fr);gap:var(--acct-gap);align-items:start}

/* identity band */
.acct-hero{background:var(--paper);border-bottom:1px solid var(--line)}
.acct-hero-in{display:flex;align-items:center;gap:18px;padding-block:22px;flex-wrap:wrap}
.acct-av{width:56px;height:56px;flex:none;border-radius:50%;background:var(--blue);color:#fff;display:grid;place-items:center;font:700 1.35rem/1 Poppins,"Poppins Fallback",sans-serif;letter-spacing:.02em}
.acct-idt{min-width:0;flex:1}
.acct-idt h1{margin:0;font-size:1.45rem;line-height:1.2;letter-spacing:-.01em;text-wrap:balance}
.acct-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;margin-top:5px;color:var(--ink-3);font-size:.88rem}
.acct-meta .mono{font-family:"IBM Plex Mono","Plex Mono Fallback",monospace;font-size:.8rem;font-variant-numeric:tabular-nums}
.chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:700;letter-spacing:.01em;white-space:nowrap;line-height:1.5}
.chip svg{width:13px;height:13px}
.chip--ok{background:var(--teal-050);color:var(--teal-ink)}
.chip--warn{background:var(--warn-bg);color:var(--warn)}
.chip--blue{background:var(--blue-050);color:var(--blue-700)}
.acct-hero-act{display:flex;gap:10px;flex-wrap:wrap}

/* navigation: a list on desktop, a scrolling tab strip on phones */
.acct-nav{position:sticky;top:96px;background:var(--paper);border:1px solid var(--line);border-radius:var(--r-lg,16px);padding:8px}
.acct-nav a,.acct-nav button{display:flex;align-items:center;gap:11px;width:100%;padding:10px 12px;border-radius:10px;border:0;background:none;color:var(--ink-2);font:600 .92rem/1.3 inherit;text-decoration:none;cursor:pointer;text-align:left}
.acct-nav a svg,.acct-nav button svg{width:18px;height:18px;flex:none;color:var(--ink-3)}
.acct-nav a:hover{background:var(--paper-2);color:var(--ink)}
.acct-nav a.on{background:var(--blue-050);color:var(--blue-700)}
.acct-nav a.on svg{color:var(--blue)}
.acct-nav .cnt{margin-left:auto;font:500 .74rem/1 "IBM Plex Mono",monospace;color:var(--ink-3);background:var(--paper-3);border-radius:999px;padding:3px 8px}
.acct-nav hr{border:0;border-top:1px solid var(--line);margin:8px 4px}
.acct-nav form{margin:0}
.acct-nav button:hover{background:var(--err-bg);color:var(--err)}
.acct-nav button:hover svg{color:var(--err)}
.acct-help{margin:10px 12px 6px;font-size:.8rem;color:var(--ink-3);line-height:1.5}
.acct-help a{color:var(--blue);font-weight:700;text-decoration:none;font-family:"IBM Plex Mono",monospace}

.acct-main{min-width:0;display:flex;flex-direction:column;gap:var(--acct-gap)}
.acct-h{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap}
.acct-h h2.t{margin:0;font-size:1.35rem;letter-spacing:-.01em}
.acct-h p{margin:4px 0 0;color:var(--ink-3);font-size:.92rem;max-width:62ch}

/* flash + notices */
.acct-ok,.acct-err,.acct-note{display:flex;gap:10px;align-items:flex-start;border-radius:12px;padding:12px 16px;font-size:.92rem;line-height:1.45}
.acct-ok{background:var(--ok-bg);border:1px solid var(--ok-line);color:var(--ok);font-weight:600}
.acct-err{background:var(--err-bg);border:1px solid var(--err-line);color:var(--err);font-weight:600}
.acct-note{background:var(--warn-bg);border:1px solid #f1d9a5;color:var(--ink);align-items:center;flex-wrap:wrap}
.acct-note form{margin:0 0 0 auto}
.acct-ok svg,.acct-err svg{width:18px;height:18px;flex:none;margin-top:1px}

/* cards */
.apanel{background:var(--paper);border:1px solid var(--line);border-radius:var(--r-lg,16px);overflow:hidden}
.apanel-h{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--line-2)}
.apanel-h h2,.apanel-h h3{margin:0;font-size:1.02rem;display:flex;align-items:center;gap:9px}
.apanel-h h2 svg,.apanel-h h3 svg,.apanel-h .hi svg{width:18px;height:18px;color:var(--blue)}
.apanel-h .more{font-size:.85rem;font-weight:700;color:var(--blue);text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:4px}
.apanel-h .more svg{width:14px;height:14px}
.apanel-h .more:hover{text-decoration:underline}
.apanel-sub{padding:12px 20px 0;color:var(--ink-3);font-size:.86rem;margin:0}
.apanel-b{padding:16px 20px 20px}

/* stats */
.astats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.astat{background:var(--paper);border:1px solid var(--line);border-radius:var(--r-md,12px);padding:16px 18px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:2px;transition:border-color .15s}
.astat:hover{border-color:var(--blue-200)}
.astat b{font:600 1.7rem/1.1 "IBM Plex Mono","Plex Mono Fallback",monospace;font-variant-numeric:tabular-nums;color:var(--ink)}
.astat span{font-size:.84rem;color:var(--ink-3)}

/* latest order with its progress */
.alast{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px 16px;align-items:center}
.alast .no{font:600 1rem/1.2 "IBM Plex Mono",monospace}
.alast .sub{color:var(--ink-3);font-size:.86rem}
.alast .tot{font:600 1.15rem/1 "IBM Plex Mono",monospace;text-align:right}
.asteps{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,1fr);margin-top:14px;counter-reset:s}
.asteps li{list-style:none;position:relative;padding-top:22px;font-size:.8rem;color:var(--ink-3);text-align:center}
.asteps li::before{content:"";position:absolute;top:5px;left:50%;width:12px;height:12px;margin-left:-6px;border-radius:50%;background:var(--paper);border:2px solid var(--line-strong);z-index:1}
.asteps li::after{content:"";position:absolute;top:10px;left:-50%;width:100%;height:2px;background:var(--line-strong)}
.asteps li:first-child::after{display:none}
.asteps li.done{color:var(--ink);font-weight:600}
.asteps li.done::before{background:var(--teal);border-color:var(--teal)}
.asteps li.done::after{background:var(--teal)}
.asteps{padding:0;margin-bottom:0}

/* order list: rows on desktop, stacked cards on phones */
.olist{display:flex;flex-direction:column}
.orow{display:grid;grid-template-columns:minmax(170px,1.4fr) minmax(90px,.8fr) .9fr .9fr minmax(120px,1fr) auto;gap:12px;align-items:center;padding:14px 4px;border-bottom:1px solid var(--line-2);text-decoration:none;color:inherit}
.orow:last-child{border-bottom:0}
a.orow:hover{background:var(--paper-2)}
.orow .no{font:600 .92rem/1.2 "IBM Plex Mono",monospace;color:var(--ink)}
.orow .dt{color:var(--ink-3);font-size:.86rem;font-variant-numeric:tabular-nums}
.orow .trk{font-size:.82rem;color:var(--ink-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.orow .trk a{color:var(--blue);font-weight:600;text-decoration:none}
.orow .tot{font:600 .95rem/1 "IBM Plex Mono",monospace;text-align:right;white-space:nowrap}
.orow .go{display:none}
.orow .oparts{display:block;margin-top:3px;font:400 .78rem/1.35 inherit;font-family:inherit;color:var(--ink-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.orow .po{font:500 .86rem/1.2 "IBM Plex Mono",monospace;color:var(--ink-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.orow .po-l{display:none}
.orow mark{background:#fff3b0;color:inherit;border-radius:3px;padding:0 1px}
.osearch{display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:6px 6px 6px 14px;border:1px solid var(--line);border-radius:12px;background:#fff}
.osearch .i{color:var(--ink-3);flex:none}
.osearch input{flex:1;min-width:0;border:0;outline:0;font:inherit;font-size:.95rem;background:transparent;padding:8px 0}
.osearch:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px var(--blue-050)}
.osearch-x{font-size:.85rem;color:var(--ink-3);text-decoration:none;padding:0 6px}
.osearch-n{margin:-4px 0 12px;font-size:.86rem;color:var(--ink-3)}
.orow>span{margin:0;padding:0;align-self:center}
.ohead{display:grid;grid-template-columns:minmax(170px,1.4fr) minmax(90px,.8fr) .9fr .9fr minmax(120px,1fr) auto;gap:12px;padding:0 4px 10px;border-bottom:1px solid var(--line);font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3);font-weight:700}
.ohead span:last-child{text-align:right}

/* status pills (shared with designs / requests / buy again) */
.pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700;white-space:nowrap;line-height:1.5;width:max-content}
.pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
.pill-paid,.pill-completed,.pill-scheduled,.pill-delivered{background:var(--ok-bg);color:var(--ok)}
.pill-pending,.pill-processing,.pill-new{background:var(--warn-bg);color:var(--warn)}
.pill-cancelled,.pill-rejected,.pill-refunded{background:var(--err-bg);color:var(--err)}
.pill-shipped{background:var(--blue-050);color:var(--blue-700)}

/* compact lists (designs, requests) */
.mini{display:flex;flex-direction:column;gap:10px}
.mini-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--line-2);border-radius:12px;background:var(--paper-2)}
.mini-row .mt{flex:1;min-width:0}
.mini-row .mt b{display:block;font-size:.93rem;color:var(--ink);overflow:hidden;text-overflow:ellipsis}
.mini-row .mt span{display:block;font-size:.8rem;color:var(--ink-3);margin-top:2px}
.mini-row .mprice{font:600 .92rem/1 "IBM Plex Mono",monospace;white-space:nowrap}
.mini-rm{background:none;border:0;color:var(--ink-3);cursor:pointer;font-size:.8rem;padding:6px}
.mini-rm:hover{color:var(--err)}

/* dashboard two-column area */
.acol{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:var(--acct-gap)}
.aempty{text-align:center;padding:22px 12px;color:var(--ink-3);font-size:.92rem}
.aempty a{color:var(--blue);font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:6px}
.aempty a svg{width:14px;height:14px}
.aempty .ei{display:block;margin:0 auto 8px;width:28px;height:28px;color:var(--ink-3);opacity:.6}
.aempty .ei svg{width:28px;height:28px}

/* quick links */
.alinks{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.alink{display:flex;gap:12px;align-items:center;padding:14px 16px;border-radius:var(--r-md,12px);border:1px solid var(--line);background:var(--paper);text-decoration:none;color:var(--ink);font-weight:600;font-size:.9rem;line-height:1.3;transition:border-color .15s}
.alink:hover{border-color:var(--blue-200)}
.alink svg{width:20px;height:20px;color:var(--blue);flex:none}

/* address cards */
.addr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}
.addr{position:relative;border:1px solid var(--line);border-radius:var(--r-md,12px);padding:16px 16px 12px;background:var(--paper);display:flex;flex-direction:column;gap:2px;font-size:.92rem;line-height:1.5}
.addr.is-def{border-color:var(--blue-200);box-shadow:inset 0 0 0 1px var(--blue-200)}
.addr .lbl{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px}
.addr .lbl b{font-size:.95rem}
.addr .who{font-weight:600}
.addr .muted{color:var(--ink-3)}
.addr-act{display:flex;flex-wrap:wrap;gap:4px 14px;margin-top:auto;padding-top:10px;border-top:1px solid var(--line-2)}
.addr-act a,.addr-act button{background:none;border:0;padding:4px 0;font:600 .84rem/1.2 inherit;color:var(--blue);cursor:pointer;text-decoration:none}
.addr-act button.rm{color:var(--ink-3)}
.addr-act button.rm:hover{color:var(--err)}
.addr-act form{margin:0}
.addr-add{display:grid;place-items:center;align-content:center;gap:6px;min-height:150px;border:1.5px dashed var(--line-strong);border-radius:var(--r-md,12px);color:var(--blue);font-weight:700;text-decoration:none;background:transparent}
.addr-add:hover{border-color:var(--blue);background:var(--blue-050)}
.addr-add svg{width:22px;height:22px}

/* forms */
.aform{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px}
.aform .full{grid-column:1/-1}
.aform label{display:block;font-size:.82rem;font-weight:700;color:var(--ink-2);margin-bottom:5px}
.aform label small{font-weight:500;color:var(--ink-3)}
.aform input[type=text],.aform input[type=email],.aform input[type=tel],.aform input[type=password],.aform select{width:100%;height:44px;padding:0 12px;border:1px solid var(--line-strong);border-radius:10px;background:var(--paper);font:inherit;font-size:.95rem;color:var(--ink)}
.aform input:focus,.aform select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px var(--blue-100)}
.aform input[disabled]{background:var(--paper-2);color:var(--ink-3)}
.aform .hint{display:block;font-size:.8rem;color:var(--ink-3);margin-top:5px}
.aform .ferr{display:block;font-size:.8rem;color:var(--err);margin-top:5px;font-weight:600}
.aform .chk{display:flex;align-items:center;gap:9px;font-weight:600;font-size:.9rem;color:var(--ink-2);margin:0}
.aform .chk input{width:18px;height:18px;accent-color:var(--blue)}
.aform .addr-kind{border:0;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:10px}
.aform .addr-kind legend{font-weight:700;font-size:.85rem;margin-bottom:8px;padding:0}
.aform .addr-kind .chk{align-items:flex-start;border:1.5px solid var(--line-strong);border-radius:var(--r-md,12px);padding:12px 14px;cursor:pointer;font-weight:400;line-height:1.4}
.aform .addr-kind .chk b{display:block;color:var(--ink)}
.aform .addr-kind .chk:has(input:checked){border-color:var(--blue);background:var(--blue-050)}
@media (max-width:560px){.aform .addr-kind{grid-template-columns:1fr}}
.aform-foot{grid-column:1/-1;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:4px}
.aform-foot .note{font-size:.82rem;color:var(--ink-3)}

/* settings rows: label column + content */
.aset{display:grid;grid-template-columns:220px minmax(0,1fr);gap:20px;padding:22px 20px;border-bottom:1px solid var(--line-2)}
.aset:last-child{border-bottom:0}
.aset-l h3{margin:0;font-size:1rem}
.aset-l p{margin:4px 0 0;font-size:.85rem;color:var(--ink-3);line-height:1.45}
.aset-cur{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-weight:600;margin-bottom:12px}

/* designs/buy-again/requests pages that keep their own markup */
.aset-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--acct-gap)}

@media (max-width:1060px){.alinks{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:960px){
  .acct-page{padding-top:0}
  .acct-shell{grid-template-columns:1fr;gap:18px}
  .acct-nav{position:sticky;top:0;z-index:5;display:flex;gap:4px;overflow-x:auto;scrollbar-width:none;border-radius:0;border-width:0 0 1px;margin:0 calc(var(--gutter) * -1);padding:8px calc(var(--gutter) - 4px);scroll-snap-type:x proximity}
  .acct-nav::-webkit-scrollbar{display:none}
  .acct-nav a{width:auto;flex:none;white-space:nowrap;padding:9px 13px;scroll-snap-align:start}
  .acct-nav .cnt{margin-left:4px}
  .acct-nav hr,.acct-help{display:none}
  /* phones: Sign out stays reachable as the last tab */
  .acct-nav form{flex:none}
  .acct-nav button{width:auto;white-space:nowrap;padding:9px 13px}
  /* more tabs off to the right: fade the edge so the strip reads as scrollable */
  .acct-nav.has-more{-webkit-mask-image:linear-gradient(to right,#000 calc(100% - 40px),transparent);mask-image:linear-gradient(to right,#000 calc(100% - 40px),transparent)}
  .acol,.aset-grid{grid-template-columns:1fr}
  .aset{grid-template-columns:1fr;gap:12px}
}
@media (max-width:640px){
  .acct-hero-in{gap:12px;padding-block:14px;flex-wrap:nowrap}
  .acct-av{width:44px;height:44px;font-size:1.05rem}
  .acct-idt h1{font-size:1.2rem}
  /* phones: a compact identity row; shop links are already in the header menu */
  .acct-hero-act,.acct-meta .mono,.acct-meta .chip--ok{display:none}
  .acct-meta{margin-top:2px;font-size:.84rem}
  .acct-meta span:first-child{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .acct-idt h1{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .acct-h p{display:none}
  .acct-h h2.t{font-size:1.2rem}
  .aempty{display:flex;align-items:center;gap:6px 10px;flex-wrap:wrap;text-align:left;padding:0;border:0;background:none;font-size:.9rem}
  .aempty br{display:none}
  .aempty .ei{display:none}
  .aempty a{margin:0}
  .astats{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
  .astat{padding:12px}
  .astat b{font-size:1.3rem}
  .astat span{font-size:.76rem}
  .alinks{grid-template-columns:1fr 1fr;gap:8px}
  .alink{padding:12px;font-size:.84rem}
  .ohead{display:none}
  .orow{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"no tot" "po st" "dt dt" "trk trk";gap:4px 10px;padding:14px 2px}
  .orow .po{grid-area:po}.orow .po-l{display:inline;font-family:inherit;color:var(--ink-3)}.orow .po .muted{display:none}
  .orow .no{grid-area:no}.orow .dt{grid-area:dt}.orow .st{grid-area:st;justify-self:end}.orow .trk{grid-area:trk}.orow .tot{grid-area:tot}
  .aform{grid-template-columns:1fr}
  .apanel-h,.apanel-b{padding-left:16px;padding-right:16px}
  .aset{padding:18px 16px}
  .asteps li{font-size:.72rem}
}
@media (prefers-reduced-motion:reduce){.astat,.alink{transition:none}}

/* business account (tiers, invoices) */
.biz-pitch{display:flex;flex-direction:column;align-items:flex-start;gap:14px;max-width:62ch}
.biz-pitch p{margin:0;line-height:1.6}
.biz-badge{font-family:var(--font-h);font-weight:700;font-size:.85rem;padding:5px 12px;border-radius:999px;white-space:nowrap}
.biz-badge--bronze{background:#f6e8dc;color:#8a4b1c}
.biz-badge--silver{background:#eceff3;color:#3d4757}
.biz-badge--gold{background:#fbf0cc;color:#7a5800}
.biz-ladder{list-style:none;margin:0 0 20px;padding:0;display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.biz-ladder li{border:1.5px solid var(--line);border-radius:var(--r-md,12px);padding:12px 14px;display:grid;grid-template-columns:1fr auto;gap:2px 8px}
.biz-ladder li small{grid-column:1/-1;color:var(--ink-faint);font-size:.8rem}
.biz-ladder .biz-pct{font-family:var(--font-m);font-weight:600;color:var(--ink-2)}
.biz-ladder li.is-cur{border-color:var(--blue);background:var(--blue-050)}
.biz-ladder li.is-cur .biz-pct{color:var(--blue)}
.biz-ladder li.is-past{opacity:.75}
@media (max-width:560px){.biz-ladder{grid-template-columns:1fr}}
.biz-meter__track{position:relative;height:10px;border-radius:999px;background:var(--paper-3,#eef1f5);overflow:visible}
.biz-meter__track i{display:block;height:100%;border-radius:inherit;background:var(--blue);max-width:100%}
.biz-meter__tick{position:absolute;top:-3px;width:2px;height:16px;background:var(--ink-faint);border-radius:2px}
.biz-meter__lbl{display:flex;justify-content:space-between;gap:10px;margin-top:8px;font-size:.86rem;color:var(--ink-2);font-variant-numeric:tabular-nums}
.biz-facts{margin:16px 0 0;padding-left:18px;display:grid;gap:8px;font-size:.9rem;line-height:1.5;max-width:70ch}
.biz-log{margin-top:16px;font-size:.88rem}
.biz-log summary{cursor:pointer;font-weight:700;color:var(--blue)}
.biz-log ul{list-style:none;padding:0;margin:10px 0 0;display:grid;gap:6px}
.biz-credit{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.biz-credit div{background:var(--paper-2,#f6f8fb);border-radius:var(--r-md,12px);padding:12px 14px;display:flex;flex-direction:column;gap:2px}
.biz-credit small{color:var(--ink-faint);font-size:.78rem}
.biz-credit b{font-variant-numeric:tabular-nums;font-size:1.05rem}
@media (max-width:560px){.biz-credit{grid-template-columns:1fr}}
.biz-inv{display:grid;gap:8px}
.biz-inv__row{display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--line);border-radius:var(--r-md,12px)}
.biz-inv__row small{display:block;color:var(--ink-faint);font-size:.8rem;margin-top:2px}
.biz-inv__row.is-late{border-color:var(--warn,#b45309);background:var(--warn-bg,#fff7ed)}
.biz-inv__row.is-late small{color:var(--warn,#b45309);font-weight:600}
.biz-inv__amt{font-variant-numeric:tabular-nums}
.acct-av--img{background:var(--paper-2,#eef2f7);overflow:hidden;padding:0;box-shadow:0 0 0 3px #fff,0 0 0 4px var(--line)}
.acct-av--img img{width:100%;height:100%;display:block}
.avp{display:grid;gap:14px}
.avp-cur{display:flex;align-items:center;gap:14px}
.avp-cur img{width:72px;height:72px;border-radius:50%;background:var(--paper-2,#eef2f7);box-shadow:0 0 0 1px var(--line)}
.avp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:10px;margin:0;padding:0;border:0}
.avp-opt{position:relative;display:grid;justify-items:center;gap:4px;cursor:pointer;font-size:.72rem;color:var(--ink-faint);text-align:center;line-height:1.2}
.avp-opt input{position:absolute;opacity:0;pointer-events:none}
.avp-opt img{width:56px;height:56px;border-radius:50%;background:var(--paper-2,#eef2f7);box-shadow:0 0 0 1px var(--line);transition:box-shadow .15s,transform .15s}
.avp-opt:hover img{transform:translateY(-1px)}
.avp-opt input:checked+img{box-shadow:0 0 0 3px var(--blue)}
.avp-opt input:focus-visible+img{outline:2px solid var(--blue);outline-offset:3px}
.avp-act{display:flex;gap:10px;flex-wrap:wrap}
.cards-list{display:grid;gap:8px}
.card-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid var(--line);border-radius:12px;background:#fff}
.card-row__brand{font-weight:700}
.card-row__exp{color:var(--ink-faint);font-size:.85rem;margin-left:auto;font-variant-numeric:tabular-nums}
.card-row form{margin:0}
/* profile picture picker folds away: current picture + Change */
.avp-fold>summary{list-style:none;display:flex;align-items:center;gap:14px;cursor:pointer}
.avp-fold>summary::-webkit-details-marker{display:none}
.avp-fold>summary img{width:64px;height:64px;border-radius:50%;border:1px solid var(--line)}
.avp-fold>summary .btn{pointer-events:none}
.avp-fold[open]>summary{margin-bottom:14px}
.avp-fold[open]>summary .avp-chg{display:none}
</style>
