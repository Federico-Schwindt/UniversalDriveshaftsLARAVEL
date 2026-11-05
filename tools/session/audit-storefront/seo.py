import re,json,sys,csv,html
from collections import Counter,defaultdict
rows=[l.rstrip('\n').split('\t') for l in open('crawl.tsv')]
out=[]; titles=Counter(); descs=Counter(); ldbad=[]; mixed=[]; devlinks=[]
for r in rows:
    u,f,code=r[0],r[1],r[2]
    if code!='200' or '.xml' in u or '/api/' in u: continue
    b=open(f'bodies/{f}.html',errors='ignore').read()
    if not b.lstrip().lower().startswith('<!doctype html'): continue
    t=re.search(r'<title>(.*?)</title>',b,re.S); t=html.unescape(t.group(1).strip()) if t else ''
    d=re.search(r'<meta name="description" content="(.*?)"',b,re.S); d=html.unescape(d.group(1)) if d else ''
    c=re.search(r'<link rel="canonical" href="(.*?)"',b); c=c.group(1) if c else ''
    rb=re.search(r'<meta name="robots" content="(.*?)"',b); rb=rb.group(1) if rb else ''
    og=len(re.findall(r'property="og:',b)); h1=len(re.findall(r'<h1[\s>]',b))
    imgs=re.findall(r'<img\b[^>]*>',b)
    noalt=[i for i in imgs if not re.search(r'\balt=',i)]
    nodim=[i for i in imgs if not (re.search(r'\bwidth=',i) and re.search(r'\bheight=',i))]
    lds=re.findall(r'<script type="application/ld\+json">(.*?)</script>',b,re.S); ldn=len(lds); lderr=''
    for s in lds:
        try: json.loads(s)
        except Exception as e: lderr=str(e)[:60]; ldbad.append((u,lderr))
    m=re.findall(r'(?:src|href)="http://[^"]+',b)
    if m: mixed.append((u,m[:3]))
    titles[t]+=1; descs[d]+=1
    path=u.replace('https://dev.udshafts.com','')
    expc='https://dev.udshafts.com'+(path.split('?')[0].rstrip('/') or '/')
    out.append(dict(url=u,title=t,tlen=len(t),desc=d,dlen=len(d),canon=c,canon_ok=(c==expc),robots=rb,og=og,h1=h1,imgs=len(imgs),noalt=len(noalt),nodim=len(nodim),ld=ldn,lderr=lderr))
w=csv.DictWriter(open('seo.csv','w'),fieldnames=out[0].keys()); w.writeheader(); w.writerows(out)
print('html pages analysed',len(out))
print('h1!=1:',[(o['url'].split('.com')[1],o['h1']) for o in out if o['h1']!=1][:40])
print('canonical mismatch:',[(o['url'].split('.com')[1],o['canon']) for o in out if not o['canon_ok']][:30])
print('robots not noindex:',[o['url'] for o in out if 'noindex' not in o['robots']][:10])
print('title too long >60:',[(o['url'].split('.com')[1],o['tlen']) for o in out if o['tlen']>60][:25], 'count',sum(1 for o in out if o['tlen']>60))
print('title empty/default:',[o['url'].split('.com')[1] for o in out if o['title'] in ('','Universal Driveshafts')][:20])
print('desc <70 or >160:',[(o['url'].split('.com')[1],o['dlen']) for o in out if o['dlen']<70 or o['dlen']>160][:25],'count',sum(1 for o in out if o['dlen']<70 or o['dlen']>160))
print('dup titles:',[(t,n) for t,n in titles.items() if n>1][:15])
print('dup descs:',[(t[:70],n) for t,n in descs.items() if n>1][:15])
print('no JSON-LD:',[o['url'].split('.com')[1] for o in out if o['ld']==0][:30])
print('JSON-LD invalid:',ldbad[:10])
print('mixed http://:',mixed[:10])
print('img no alt total:',sum(o['noalt'] for o in out),'pages:',[(o['url'].split('.com')[1],o['noalt']) for o in out if o['noalt']][:15])
print('img no w/h: pages with any:',sum(1 for o in out if o['nodim']),'sample',[(o['url'].split('.com')[1],o['nodim'],o['imgs']) for o in out if o['nodim']][:12])
print('og<5:',[o['url'].split('.com')[1] for o in out if o['og']<5][:10])
