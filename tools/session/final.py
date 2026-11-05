import json,re,html,statistics,collections
d=json.load(open('matches.json'))
T={x['url']:x for x in json.load(open('dsp_all.json'))}
O={p['id']:p for p in json.load(open('ours.json'))['products']}
def U(x): return html.unescape(x or '').replace('\xa0',' ')
DROP={'5-15111XU','5-1309XU'}   # price error / title-SKU conflict
rows=[]; skipped=collections.Counter()
for m in d['ok']+d['review']:
    if m['sku'] in DROP: skipped['conflict']+=1; continue
    if m['cls']=='spicer' and not re.search(r'spicer',m['our_title'],re.I): skipped['brand']+=1; continue
    rows.append(m)
# extra cross-ref matches verified by hand
X={'3102-329U':'2-2-329','3102-36U,2-2-349,N2-2-349':'2-2-349','2-2-0329':'2-2-329','SPL350U':'SPL350X','3-1448XU':'3-1448','N3-83-072X':'3-83-072X','5-443XS':'5-443X','2-3-1205XU':'2-3-12051X','6-4-7181X':'6-4-7181-1X','5-124XY':'5-124X'}
byPart=collections.defaultdict(list)
for t in T.values():
    if t['brand']!='Spicer' and t['price']: byPart[t['part']].append(t)
bySku={p['sku']:p for p in O.values() if p['sku']}
for sku,part in X.items():
    p=bySku[sku]; t=byPart[part][0]
    rows.append({'our_id':p['id'],'sku':sku.split(',')[0],'our_title':U(p['title']),'our_price':float(p['price']),'cls':'after','cat':[U(c['name']) for c in p['terms'].get('product_cat',[])],'their_part':t['part'],'their_name':t['name'],'their_brand':t['brand'],'their_price':float(t['price'].replace(',','')),'url':t['url'],'xref':True})
GEN={'All Small Parts','Hardware','Custom Driveshaft','Certified Driveshafts','Premade Driveshafts'}
REN={'Stub & Midship Shaft':'Stub & Midship Shafts','Dust cap Press On & Threaded':'Dust Caps','Washers & Snap Rings':'Washers & Snap Rings','Center Support Bearing':'Center Support Bearings','Slip Yoke':'Slip Yokes','Rubber Flex Disc':'Rubber Flex Discs','Unwelded Assemblies':'Unwelded Assemblies'}
for r in rows:
    cs=[c for c in r['cat'] if c not in GEN] or r['cat'] or ['Other']
    c=cs[0]; r['section']=REN.get(c,c)
    r['diff']=(r['our_price']-r['their_price'])/r['their_price']*100
    # short description from our title: drop leading part number
    t=re.sub(r'^\s*(UDS\s*[-–]?\s*)?','',r['our_title'])
    t=re.sub(r'^'+re.escape(r['sku'].rstrip('U'))+r'U?\s*[-–|,]*\s*','',t,flags=re.I)
    t=re.sub(r'\s+',' ',t).strip(' -–|,')
    t=t.replace('Conversión','Conversion'); r['desc']=t[:70]+('…' if len(t)>70 else '')
seen=set(); out=[]
for r in sorted(rows,key=lambda r:(r['section'],r['sku'])):
    k=(r['sku'],r['their_part'])
    if k in seen: continue
    seen.add(k); out.append(r)
json.dump(out,open('final.json','w'),indent=1)
print(len(out),'rows; skipped',dict(skipped))
c=collections.Counter(r['section'] for r in out); print(c.most_common())
D=[r['diff'] for r in out]
print('median %.1f'%statistics.median(D),'cheaper',sum(x<0 for x in D),'pricier',sum(x>0 for x in D),'equal',sum(x==0 for x in D))
