import re, sys, urllib.request, urllib.parse, xml.etree.ElementTree as ET, collections, ssl
BASE='https://dev.udshafts.com'
ctx=ssl.create_default_context()
def get(u, redirects=False):
    class NR(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*a,**k): return None
    op=urllib.request.build_opener(NR, urllib.request.HTTPSHandler(context=ctx))
    req=urllib.request.Request(u, headers={'User-Agent':'uds-seo-crawl'})
    try:
        r=op.open(req, timeout=30); return r.status, r.headers.get('Location'), r.read()
    except urllib.error.HTTPError as e: return e.code, e.headers.get('Location'), b''
    except Exception as e: return 0, str(e), b''
urls=[]
# sitemap index + children
st,_,b=get(BASE+'/sitemap.xml'); root=ET.fromstring(b)
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
subs=[e.text for e in root.findall('s:sitemap/s:loc',ns)]
print('index', st, subs)
per={}
for s in subs:
    st,_,b=get(s); r=ET.fromstring(b); locs=[e.text for e in r.findall('s:url/s:loc',ns)]
    imgs=len(r.findall('.//{http://www.google.com/schemas/sitemap-image/1.1}loc')); lm=len(r.findall('s:url/s:lastmod',ns))
    print(s.split('/')[-1], st, len(locs), 'urls', lm, 'lastmod', imgs, 'images'); per[s]=locs
sample=[]
for s,l in per.items():
    k=s.split('-')[-1].split('.')[0]
    n=200 if k in('pages','categories','extra') else 40
    step=max(1,len(l)//n); sample+=l[::step][:n]
# home links
st,_,b=get(BASE+'/'); hrefs=set(re.findall(r'href="(/[^"#?]*)',b.decode('utf-8','ignore')))
sample+= [BASE+h for h in hrefs]
sample=list(dict.fromkeys(sample))[:320]
print('crawling', len(sample))
bad=collections.Counter(); chains=[]
for u in sample:
    st,loc,_=get(u); hops=0; cur=u
    while st in (301,302,303,307,308) and hops<5:
        hops+=1; cur=urllib.parse.urljoin(cur,loc); st,loc,_=get(cur)
    if hops: chains.append((u,hops,cur,st))
    if st>=400 or st==0: bad[st]+=1; print('BAD',st,u)
print('redirects:', len(chains)); [print(' ', *c) for c in chains if c[1]>1 or c[3]>=400]
print('status summary bad:', dict(bad), 'ok', len(sample)-sum(bad.values()))
