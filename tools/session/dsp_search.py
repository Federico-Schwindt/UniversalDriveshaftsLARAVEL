import re, html, json, time, urllib.request, sys
SORT=sys.argv[1]
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36"
def get(u):
    for k in range(3):
        try: return urllib.request.urlopen(urllib.request.Request(u,headers={'User-Agent':UA}),timeout=90).read().decode('utf-8','replace')
        except Exception: time.sleep(3)
    return ''
def clean(x): return re.sub(r'\s+',' ',html.unescape(re.sub(r'<[^>]+>',' ',x))).strip()
out={}
for pg in range(1,41):
    s=get("https://www.driveshaftparts.com/index.php?route=product/search&search=%25&limit=100&sort="+SORT+"&page="+str(pg))
    items=s.split('<div class="product-layout')[1:]
    for it in items:
        u=html.unescape(re.search(r'href="([^"]+)',it).group(1)); pidm=re.search(r'product_id=(\d+)',u); url=('https://www.driveshaftparts.com/index.php?route=product/product&product_id='+pidm.group(1)) if pidm else u.split('?')[0]
        name=clean((re.search(r'<h4[^>]*>(.*?)</h4>',it,re.S) or [0,''])[1])
        brand=(re.search(r'<h5 class="price-new">.*?alt="([^"]*)"',it,re.S) or [0,''])[1]
        ps=re.findall(r'<p style="height: 45px">(.*?)</p>',it,re.S)
        desc=clean(ps[0]) if ps else ''
        part=''
        for x in ps:
            m=re.match(r'\s*Part No:\s*(.*)',clean(x))
            if m: part=m.group(1).strip()
        pm=re.search(r'<p class="price">(.*?)</p>',it,re.S)
        price=None; old=None
        if pm:
            pn=re.search(r'price-new">\$([0-9,]+\.\d\d)',pm.group(1)); po=re.search(r'price-old">\$([0-9,]+\.\d\d)',pm.group(1))
            if pn: price=pn.group(1); old=po.group(1) if po else None
            else:
                m=re.search(r'\$([0-9,]+\.\d\d)',pm.group(1)); price=m.group(1) if m else None
        out[url]={'url':url,'name':name,'brand':brand,'desc':desc,'part':part,'price':price,'old':old,'oos':'Out Of Stock' in it}
    print(pg,len(items),len(out),file=sys.stderr,flush=True)
    if not items: break
    time.sleep(0.5)
json.dump(list(out.values()),open('dsp_s_'+SORT.split('&')[0]+'.json','w'),indent=0)
print(len(out))
