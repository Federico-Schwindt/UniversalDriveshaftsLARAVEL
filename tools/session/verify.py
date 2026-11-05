import json, re, sys, html, urllib.request
from html.parser import HTMLParser
BASE="https://dev.udshafts.com"
slugs=["how-to-measure-a-driveshaft","u-joint-series-identification-chart","driveshaft-vibration-and-noise-diagnosis","slip-yoke-vs-weld-yoke-vs-flange-yoke","center-support-bearing-replacement","choosing-driveshaft-tubing","custom-driveshaft-cost-and-lead-time","one-piece-vs-two-piece-driveshaft","driveshaft-angles-and-pinion-angle","replace-or-repair-a-driveshaft"]
paths=["/guides"]+["/guides/"+s for s in slugs]+["/services","/about-us","/sitemap.xml"]
class T(HTMLParser):
    def __init__(s): super().__init__(); s.out=[]; s.skip=0
    def handle_starttag(s,t,a):
        if t in("script","style","nav","header","footer"): s.skip+=1
    def handle_endtag(s,t):
        if t in("script","style","nav","header","footer"): s.skip-=1
    def handle_data(s,d):
        if s.skip<=0: s.out.append(d)
for p in paths:
    try:
        r=urllib.request.urlopen(urllib.request.Request(BASE+p,headers={"User-Agent":"verify"}),timeout=60); code=r.status; body=r.read().decode("utf-8","replace")
    except urllib.error.HTTPError as e: print(f"{p}: HTTP {e.code}"); continue
    if p.endswith(".xml"):
        print(f"{p}: {code} guides-in-sitemap={body.count('/guides')}"); continue
    title=re.search(r"<title>(.*?)</title>",body,re.S); h1=re.findall(r"<h1[^>]*>(.*?)</h1>",body,re.S)
    desc=re.search(r'name="description" content="(.*?)"',body,re.S)
    canon=re.search(r'rel="canonical" href="(.*?)"',body)
    lds=re.findall(r'<script type="application/ld\+json">(.*?)</script>',body,re.S)
    types=[]; ok=True
    for ld in lds:
        try:
            d=json.loads(ld); types.append(d.get("@type") or [g.get("@type") for g in d.get("@graph",[])])
        except Exception as e: ok=False; types.append("INVALID:"+str(e)[:40])
    art=re.search(r'<article class="guide-body".*?</article>',body,re.S)
    wc=None
    if art:
        t=T(); t.feed(art.group(0)); wc=len(re.findall(r"\b\w[\w'-]*\b",html.unescape(" ".join(t.out))))
    tl=len(html.unescape(title.group(1))) if title else 0; dl=len(html.unescape(desc.group(1))) if desc else 0
    print(f"{p}: {code} title={tl}ch desc={dl}ch h1={len(h1)} words={wc} jsonld_ok={ok} canon={canon.group(1) if canon else None}\n   types={types}")
