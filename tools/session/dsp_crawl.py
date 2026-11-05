import re, html, json, time, urllib.request, sys
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36"
BASE="https://www.driveshaftparts.com/index.php?route=product/category&path="
def get(u):
    for k in range(3):
        try:
            r=urllib.request.urlopen(urllib.request.Request(u,headers={'User-Agent':UA}),timeout=60)
            return r.read().decode('utf-8','replace')
        except Exception as e:
            time.sleep(2)
    return ''
def clean(x): return re.sub(r'\s+',' ',html.unescape(re.sub(r'<[^>]+>',' ',x))).strip()
home=open('dsp.html').read()
queue=sorted(set(re.findall(r'path=([0-9_]+)',home)))
seen=set(); prods={}; cats={}
while queue:
    p=queue.pop(0)
    if p in seen: continue
    seen.add(p)
    page=1
    while True:
        s=get(BASE+p+"&limit=2000"+("&page=%d"%page if page>1 else ""))
        title=(re.search(r'<title>([^<]*)',s) or [None,''])[1]
        cats[p]=html.unescape(title).strip()
        for q in re.findall(r'path=([0-9_]+)',s):
            if q not in seen and q not in queue and q.startswith(p.split('_')[0]): queue.append(q)
        i=s.find('<table'); j=s.find('</table>',i)
        rows=re.findall(r'<tr>(.*?)</tr>',s[i:j],re.S) if i>=0 else []
        n=0
        for r in rows:
            m=re.search(r'cart\.add\(\'(\d+)\'',r)
            pr=re.findall(r'\$([0-9,]+\.\d\d)',r)
            tip=re.search(r'class="tiptext">(.*?)<div class="description">(.*?)</div>',r,re.S)
            if not (m and tip): continue
            brand=(re.search(r'<span style="display: none">([^<]*)</span>',r) or [None,''])[1].strip()
            url=(re.search(r'href="([^"?]+)',r) or [None,''])[1]
            pid=m.group(1)
            d=prods.setdefault(pid,{'id':pid,'part':clean(tip.group(1)),'desc':clean(tip.group(2)),'brand':brand,'url':url,'prices':pr,'cats':[], 'oos':'Out of Stock' in r})
            d['cats'].append(p); n+=1
        if n>=2000 and page<20: page+=1; continue
        break
    print(p, cats[p], n, len(prods), file=sys.stderr, flush=True)
    time.sleep(0.4)
json.dump({'cats':cats,'prods':list(prods.values())},open('dsp_products.json','w'),indent=0)
print('done',len(prods),len(cats))
