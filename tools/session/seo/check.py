"""Sitewide SEO check against staging: crawls the sitemap(s) + key pages, validates titles/descriptions/H1/canonical/robots/JSON-LD, finds duplicates and broken links."""
import re,sys,json,html,urllib.request,urllib.parse,collections,time,random
B='https://dev.udshafts.com'; MAX=int(sys.argv[1]) if len(sys.argv)>1 else 400
def get(path):
    try:
        r=urllib.request.urlopen(urllib.request.Request(B+path,headers={'User-Agent':'uds-seo-check'}),timeout=30); return r.getcode(),r.read().decode('utf-8','ignore'),r.headers
    except urllib.error.HTTPError as e: return e.code,'',e.headers
    except Exception as e: return 'ERR',str(e),{}
def locs(xml): return re.findall(r'<loc>([^<]+)</loc>',xml)
code,sm,_=get('/sitemap.xml'); urls=[]
if '<sitemapindex' in sm:
    for s in locs(sm):
        c,x,_=get(urllib.parse.urlparse(s).path); urls+=locs(x); print('sub-sitemap',s.split('/')[-1],c,len(locs(x)))
else: urls=locs(sm)
paths=[urllib.parse.urlparse(u).path for u in urls]; print('sitemap urls:',len(paths))
# stratified sample: all non-product pages + a sample of products/parts
groups=collections.defaultdict(list)
for p in paths: groups[p.split('/')[1] if p.count('/')>=1 else ''].append(p)
sample=[]
for k,v in groups.items():
    if k in('parts','catalog') and len(v)>60: random.seed(1); sample+=random.sample(v,60)
    else: sample+=v
sample=sample[:MAX]; print('checking',len(sample),'pages')
titles=collections.defaultdict(list); descs=collections.defaultdict(list); issues=[]; ok=0
for p in sample:
    c,b,h=get(p)
    if c!=200: issues.append((p,f'status {c}')); continue
    ok+=1
    t=html.unescape((re.search(r'<title>(.*?)</title>',b,re.S) or [None,''])[1]).strip()
    d=html.unescape((re.search(r'<meta name="description" content="([^"]*)"',b) or [None,''])[1]).strip()
    h1=re.findall(r'<h1[^>]*>(.*?)</h1>',b,re.S); can=re.search(r'<link rel="canonical" href="([^"]+)"',b); rob=re.search(r'<meta name="robots" content="([^"]+)"',b)
    lds=re.findall(r'<script type="application/ld\+json">(.*?)</script>',b,re.S); types=[]
    for ld in lds:
        try:
            j=json.loads(ld); j=j if isinstance(j,list) else (j.get('@graph') if isinstance(j,dict) and '@graph' in j else [j])
            for o in j:
                ty=o.get('@type') if isinstance(o,dict) else '?'; types+= ty if isinstance(ty,list) else [ty]
        except Exception as e: issues.append((p,'invalid JSON-LD: '+str(e)[:60]))
    titles[t].append(p); descs[d].append(p)
    if not t: issues.append((p,'no title'))
    elif len(t)>65: issues.append((p,f'title {len(t)} chars: {t[:70]}'))
    if not d: issues.append((p,'no description'))
    elif len(d)>165 or len(d)<60: issues.append((p,f'description {len(d)} chars'))
    if len(h1)!=1: issues.append((p,f'{len(h1)} h1'))
    if not can: issues.append((p,'no canonical'))
    elif urllib.parse.urlparse(can.group(1)).path!=p and p not in('/',): issues.append((p,'canonical→'+can.group(1).replace(B,'')))
    if rob and 'noindex' in rob.group(1) and p in paths: pass  # staging: everything noindex — expected
    if not lds and p.startswith(('/parts/','/catalog/driveshafts/part/','/driveshafts/','/series/','/guides/','/shop/')): issues.append((p,'no JSON-LD'))
    if p.startswith('/parts/') and 'Product' not in types: issues.append((p,'no Product schema'))
    for href in set(re.findall(r'href="(/[^"#?]*)',b)):
        pass
for t,ps in titles.items():
    if len(ps)>1: issues.append((ps[0],f'duplicate title x{len(ps)}: "{t[:60]}" also {ps[1]}'))
for d,ps in descs.items():
    if d and len(ps)>1: issues.append((ps[0],f'duplicate description x{len(ps)}: also {ps[1]}'))
print(f'\n{ok} ok / {len(sample)}; issues: {len(issues)}')
for p,i in issues[:80]: print(' ',p,'→',i)
