import sys,json,urllib.parse,urllib.request
q=sys.argv[1]; n=int(sys.argv[2]) if len(sys.argv)>2 else 12
data=json.dumps({"options":{"query":q,"scope":"pins","page_size":25},"context":{}})
url="https://www.pinterest.com/resource/BaseSearchResource/get/?source_url="+urllib.parse.quote("/search/pins/?q="+q)+"&data="+urllib.parse.quote(data)
req=urllib.request.Request(url,headers={"User-Agent":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36","Accept":"application/json","X-Requested-With":"XMLHttpRequest"})
try:
    d=json.load(urllib.request.urlopen(req,timeout=25))
except Exception as e:
    print("ERR",e); sys.exit()
res=d.get("resource_response",{}).get("data",{}).get("results",[])
out=0
for r in res:
    im=(r.get("images") or {}).get("orig") or {}
    if im.get("url"):
        print(im["url"], im.get("width"), im.get("height"), (r.get("title") or r.get("grid_title") or "")[:60].replace("\n"," "))
        out+=1
        if out>=n: break
