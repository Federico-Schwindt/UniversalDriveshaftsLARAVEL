import json,re,collections,html
T=json.load(open('dsp_all.json'))
O=json.load(open('ours.json'))['products']
def n(x): return re.sub(r'[^A-Z0-9]','',(x or '').upper())
def U(x): return html.unescape(x or '').replace('\xa0',' ')
SER=r'(?<![\d.])(1000|1100|1210|1280|1310|1330|1350|1410|1480|1550|1610|1710|1760|1810|1880|2C|3R|5\.5C|6\.5C|7290|7260|7280|7270|7285|7290|7300|7310|7320|7330|7340|7350|7360|7380|7390|7400|7410|7430|7440|7460|7480|7490|7495|7500|7510|7520|7525|7530|7540|7550|7560|7570|7580|7590|7600)(?!\d)'
TYPES=[('ujoint',r'u[\s/-]?joint|universal joint|\bu/j\b|cross'),('flange',r'flange'),('endyoke',r'end yoke'),('slip',r'slip'),('weld',r'weld yoke|tube yoke|weld'),('center',r'center (support|bearing)|centre|hanger|cbs'),('stub',r'stub|midship|spline shaft|tube shaft'),('tube',r'\btub(e|ing)\b'),('flex',r'flex disc|rubber coupling|guibo|giubo|flex coupling'),('strap',r'strap'),('ubolt',r'u[\s-]?bolt'),('bolt',r'\bbolts?\b'),('nut',r'\bnuts?\b'),('cv',r'\bcv\b|double cardan|constant velocity|ball stud|centering'),('yokeshaft',r'yoke shaft'),('trans',r'transmission|trans yoke'),('boot',r'\bboot'),('cap',r'dust cap'),('bush',r'bushing'),('weight',r'weight'),('sleeve',r'sleeve'),('prong',r'prong'),('driveshaft',r'driveshaft|drive shaft')]
def types(s):
    s=s.lower(); return {k for k,r in TYPES if re.search(r,s)}
def series(s): return set(re.findall(SER,s.upper()))
# competitor index
tidx=collections.defaultdict(list)
for t in T:
    if not t['price'] or float(t['price'].replace(',',''))<=0: continue
    t['cls']='spicer' if t['brand']=='Spicer' else 'after'
    tidx[n(t['part'])].append(t)
def ourcls(p):
    b={x['name'].upper() for x in p['terms'].get('pa_brand',[])}
    if b & {'SPICER','OEM SPICER'}: return 'spicer'
    if b & {'UDS','PTI','NEAPCO'}: return 'after'
    t=U(p['title'])
    if re.search(r'\bspicer\b',t,re.I) and not re.search(r'\bUDS\b|same as|replaces|cross',t,re.I): return 'spicer'
    return 'after'
def cats(p): return [U(x['name']) for x in p['terms'].get('product_cat',[])]
matches=[];review=[]
for p in O:
    sku=(p['sku'] or '').strip()
    if not sku: continue
    cls=ourcls(p)
    base=sku[:-1] if sku.upper().endswith('U') and len(sku)>3 else sku
    cands=[]
    if cls=='spicer':
        for key in {n(base)+'S', n(base)}:
            cands+= [t for t in tidx.get(key,[]) if t['cls']=='spicer']
    else:
        for key in {n(base), n(sku)}:
            cands+= [t for t in tidx.get(key,[]) if t['cls']=='after']
    # dedupe
    seen=set(); cands=[c for c in cands if not (c['url'] in seen or seen.add(c['url']))]
    if not cands: continue
    ot=U(p['title'])+' '+' '.join(cats(p))
    for c in cands:
        tt=c['name']+' '+c['desc']
        ts,os_=series(tt),series(ot); ty,oy=types(tt),types(ot)
        op=float(p['price']); tp=float(c['price'].replace(',',''))
        flags=[]
        if ts and os_ and not (ts&os_): flags.append('series %s vs %s'%(sorted(os_),sorted(ts)))
        if ty and oy and not (ty&oy): flags.append('type %s vs %s'%(sorted(oy),sorted(ty)))
        r=op/tp
        if r>2.2 or r<0.45: flags.append('ratio %.2f'%r)
        if len(cands)>1: flags.append('multi')
        m={'our_id':p['id'],'sku':sku,'our_title':U(p['title']),'our_price':op,'cls':cls,'cat':cats(p),
           'their_part':c['part'],'their_name':c['name'],'their_desc':c['desc'],'their_brand':c['brand'],'their_price':tp,'their_old':c['old'],'url':c['url'],'flags':flags}
        (review if flags else matches).append(m)
json.dump({'ok':matches,'review':review},open('matches.json','w'),indent=1)
print(len(matches),'clean', len(review),'review')
print(collections.Counter(m['cls'] for m in matches))
print(collections.Counter(f.split()[0] for m in review for f in m['flags']))
