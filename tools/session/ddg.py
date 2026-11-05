import sys,re,json,urllib.parse,urllib.request
q=sys.argv[1]; n=int(sys.argv[2]) if len(sys.argv)>2 else 10
UA={"User-Agent":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36","Accept-Language":"en-US,en;q=0.9"}
h=urllib.request.urlopen(urllib.request.Request("https://duckduckgo.com/?q="+urllib.parse.quote(q)+"&iax=images&ia=images",headers=UA),timeout=25).read().decode('utf8','ignore')
m=re.search(r'vqd=["\']?([\d-]+)',h)
if not m: print("NOVQD"); sys.exit()
u="https://duckduckgo.com/i.js?l=us-en&o=json&q="+urllib.parse.quote(q)+"&vqd="+m.group(1)+"&f=size:Large,,,,,&p=1"
d=json.load(urllib.request.urlopen(urllib.request.Request(u,headers=dict(UA,Referer="https://duckduckgo.com/")),timeout=25))
for r in d.get("results",[])[:n]: print(r["image"], r.get("width"), r.get("height"), r.get("title","")[:60])
