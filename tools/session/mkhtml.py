import json,html,statistics,collections
R=json.load(open('final.json'))
F='file:///var/www/vhosts/dev.udshafts.com/uds-app/public/fonts/'
e=html.escape
def money(x): return '${:,.2f}'.format(x)
def pct(x):
    cls='up' if x>0 else 'dn'
    return '<td class="num d %s">%s%.0f%%</td>'%(cls,'+' if x>0 else '−' if x<0 else '',abs(x))
secs=collections.OrderedDict()
for r in sorted(R,key=lambda r:r['section']): secs.setdefault(r['section'],[]).append(r)
order=sorted(secs,key=lambda s:-len(secs[s]))
D=[r['diff'] for r in R]
cheaper=sum(x<0 for x in D); pricier=sum(x>0 for x in D)
summ=''.join('<tr><td>%s</td><td class="num">%d</td><td class="num">%d</td><td class="num">%d</td>%s</tr>'%(e(s),len(secs[s]),sum(r['diff']<0 for r in secs[s]),sum(r['diff']>0 for r in secs[s]),pct(statistics.median([r['diff'] for r in secs[s]]))) for s in order)
body=''
for s in order:
    rows=''
    for r in sorted(secs[s],key=lambda r:r['sku']):
        tp=r['their_part'] if r['their_part'].replace('-','').upper()!=r['sku'].replace('-','').upper() else '='
        brand='Spicer' if r['cls']=='spicer' else ''
        rows+='<tr><td class="pn">%s</td><td class="pn t">%s</td><td class="ds">%s%s</td><td class="num">%s</td><td class="num">%s</td>%s</tr>'%(e(r['sku']),e(tp),('<b class="sp">Spicer</b> ' if brand else ''),e(r['desc']),money(r['our_price']),money(r['their_price']),pct(r['diff']))
    body+='<section><h2>%s <span>%d</span></h2><table class="t1"><thead><tr><th>UDS SKU</th><th>DSP #</th><th>Description</th><th class="num">UDS</th><th class="num">DSP</th><th class="num">Diff.</th></tr></thead><tbody>%s</tbody></table></section>'%(e(s),len(secs[s]),rows)
doc='''<!doctype html><html><head><meta charset="utf-8"><title>UDS vs DriveshaftParts.com</title><style>
@font-face{font-family:Poppins;src:url(%(F)spoppins-700.woff2);font-weight:700}
@font-face{font-family:Poppins;src:url(%(F)spoppins-600.woff2);font-weight:600}
@font-face{font-family:Hanken;src:url(%(F)shankengrotesk-var.woff2);font-weight:100 900}
@font-face{font-family:Plex;src:url(%(F)sibmplexmono-500.woff2)}
@page{size:Letter;margin:14mm 12mm 14mm 12mm}
*{box-sizing:border-box}body{margin:0;font:9pt/1.35 Hanken,Arial,sans-serif;color:#141b28}
header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2.5px solid #141b28;padding-bottom:8px;margin-bottom:12px}
h1{font:700 19pt Poppins,Arial;margin:0;letter-spacing:-.01em}h1 em{font-style:normal;color:#c02a2a}
.meta{text-align:right;color:#5c6879;font-size:8.5pt}
.kpis{display:flex;gap:10px;margin:0 0 12px}.k{flex:1;border:1px solid #d9dee6;border-radius:6px;padding:8px 10px}
.k b{display:block;font:700 17pt Poppins,Arial}.k span{color:#5c6879;font-size:8pt;text-transform:uppercase;letter-spacing:.05em}
.k.dn b{color:#1d7a46}.k.up b{color:#c02a2a}
h2{font:600 11pt Poppins,Arial;margin:14px 0 4px;break-after:avoid}h2 span{font:500 8pt Plex,monospace;color:#5c6879;margin-left:4px}
table{width:100%%;border-collapse:collapse}th{font:600 7.5pt Hanken,Arial;text-transform:uppercase;letter-spacing:.05em;color:#5c6879;text-align:left;border-bottom:1px solid #141b28;padding:3px 5px}
td{padding:2.6px 5px;border-bottom:1px solid #e8ebf0;vertical-align:top}tr{break-inside:avoid}
.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.pn{font:500 8pt Plex,monospace;white-space:nowrap}.t{color:#5c6879}
.ds{color:#3a4557}.d{font-weight:700}.d.up{color:#c02a2a}.d.dn{color:#1d7a46}
.sp{font:600 6.5pt Hanken;background:#141b28;color:#fff;border-radius:3px;padding:1px 4px;text-transform:uppercase}
.sum td,.sum th{padding:3px 6px}.sum{width:62%%}
.note{color:#5c6879;font-size:7.5pt;margin-top:6px}
thead{display:table-header-group}
</style></head><body>
<header><div><h1>UDS <em>vs</em> DriveshaftParts.com</h1><div style="color:#3a4557;margin-top:2px">Identical parts, public prices</div></div><div class="meta">Nov 1, 2026<br>%(N)d parts compared</div></header>
<div class="kpis"><div class="k"><b>%(N)d</b><span>Matched parts</span></div><div class="k dn"><b>%(C)d</b><span>UDS cheaper</span></div><div class="k up"><b>%(P)d</b><span>UDS higher</span></div><div class="k"><b>%(M)s</b><span>Median difference</span></div></div>
<table class="sum"><thead><tr><th>Category</th><th class="num">Parts</th><th class="num">UDS ↓</th><th class="num">UDS ↑</th><th class="num">Median</th></tr></thead><tbody>%(S)s</tbody></table>
<p class="note">Diff. = (UDS − DSP) ÷ DSP. <span style="color:#c02a2a;font-weight:700">Red</span> = UDS higher, <span style="color:#1d7a46;font-weight:700">green</span> = UDS cheaper. UDS aftermarket is compared with DSP aftermarket; <b class="sp">Spicer</b> with Spicer. "=" = same part number. DSP = DriveshaftParts.com.</p>
%(B)s
</body></html>'''%{'F':F,'N':len(R),'C':cheaper,'P':pricier,'M':('+' if statistics.median(D)>0 else '')+'%.0f%%'%statistics.median(D),'S':summ,'B':body}
open('compare.html','w').write(doc)
