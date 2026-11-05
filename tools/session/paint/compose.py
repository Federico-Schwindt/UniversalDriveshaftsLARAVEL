import re,sys,os,json
SP=sys.argv[1]; GRAY='#d9dcdf'
SEC='public/img/designer/sections/'
STYLE=('<style>#canvas_background{display:none}.points{display:none}.uds-fill path,.tube-fill{fill:%(g)s}'
 '.tube-line{stroke:#0b1a33!important;stroke-width:1!important}'
 '.parts path,.parts line,.parts polyline,.parts ellipse,.parts polygon,.parts rect,.parts circle{stroke:#0b1a33!important;stroke-width:.45!important}'
 '.parts [stroke="none"],.parts [fill="none"][stroke="null"]{stroke:none!important}'
 '.parts .uds-fill path,.parts .tube-fill,.tube-fill,.uds-fill path{stroke:none!important}'
 '[fill="#ffffff"],[fill="white"],[fill="#9d9fa2"],[fill="#9c9a9b"],[style*="fill: rgb(255, 255, 255)"]{fill:%(g)s!important}'
 '.dim-line,.dim-ext,.dim-line-t,.radius-line{stroke:#1246ab!important;stroke-width:.6!important}.dim-arrow{fill:#1246ab!important}'
 'text{font-family:Poppins,Arial,sans-serif!important;font-weight:600!important;fill:#8392b0}text.dim-text{fill:#1246ab;font-size:11px!important}'
 'text.dim-text.is-set{fill:#0b1a33;font-family:"IBM Plex Mono",Menlo,monospace!important}'
 'text.front{fill:#5c6879;font-family:Menlo,monospace!important;font-weight:500!important;font-size:7px!important;letter-spacing:.1em}</style>')%{'g':GRAY}
def num(m,k,d=0.0):
    r=re.search(r'\b%s="([^"]*)"'%k,m); return float(r.group(1)) if r and r.group(1).strip() not in ('','undefined','null') else d
def tube_rects(svg):
    """Rects between the section's own y=40 / y=60 tube lines (closes supplier gaps like section 60)."""
    top=[];bot=[]
    for m in re.findall(r'<line\b[^>]*>',svg):
        y1,y2=num(m,'y1'),num(m,'y2')
        if y1!=y2: continue
        x1,x2=sorted([num(m,'x1'),num(m,'x2')])
        if x2-x1<1: continue
        (top if abs(y1-40)<.01 else bot if abs(y1-60)<.01 else []).append((x1,x2))
    out=[]
    for a1,a2 in top:
        for b1,b2 in bot:
            x1,x2=max(a1,b1),min(a2,b2)
            if x2-x1>1: out.append('<rect class="tube-fill" x="%.3f" y="40" width="%.3f" height="20"/>'%(x1,x2-x1))
    return ''.join(out)
def fill_for(code):
    frag=open(f'{SP}/paint/fills/{code}.frag').read()
    return frag.replace('</g>', tube_rects(open(SEC+code+'.svg').read())+'</g>',1)
def section(code):
    s=open(SEC+code+'.svg').read(); s=re.sub(r'<\?xml[^>]*\?>','',s)
    return re.sub(r'(<svg\b[^>]*>)',lambda m:m.group(1)+'<g class="parts">'+fill_for(code),s,1).replace('</svg>','</g></svg>')
def compose(name,dims):
    s=open(f'storage/app/designer/{name}.svg').read(); codes=name.split('+'); i=[0]
    def inj(m):
        c=codes[i[0]]; i[0]+=1; return m.group(0)+fill_for(c)
    s=re.sub(r'<svg\b[^>]*viewBox="0 0 \d+ \d+"[^>]*>',inj,s)
    assert i[0]==len(codes),(i[0],codes)
    lines=re.findall(r'<line class="tube-line"[^>]*>',s); rects=[]
    for a in lines:
        for b in lines:
            if a<b and num(a,'x1')==num(b,'x1') and num(a,'x2')==num(b,'x2') and num(a,'y1')!=num(b,'y1'):
                y=min(num(a,'y1'),num(b,'y1')); rects.append('<rect class="tube-fill" x="%.3f" y="%.3f" width="%.3f" height="%.3f"/>'%(num(a,'x1'),y,num(a,'x2')-num(a,'x1'),abs(num(a,'y1')-num(b,'y1'))))
    if lines: s=s.replace(lines[0],''.join(rects)+lines[0],1)
    for k,v in dims.items():
        s=re.sub(r'(<text class=")dim-text(" id="dim-text-%s" name="%s"[^>]*>)\{%s\}'%(k.lower(),k,k),lambda m:m.group(1)+'dim-text is-set'+m.group(2)+v,s,1)
    s=re.sub(r'(<svg\b[^>]*>)',r'\1'+STYLE,s,1)
    return s
one=compose('01+01R',{'C1':'52.25','W1':'3.00','T1':'.083'})
two=compose('01+50+01R',{'A1':'26.50','B1':'24.75','B2':'27.50','C1':'52.25','C2':'31.00','W1':'3.00','W2':'3.50','T1':'.083','T2':'.083'})
open(f'{SP}/paint/one-piece.svg','w').write(one); open(f'{SP}/paint/two-piece.svg','w').write(two)
grid="background:linear-gradient(0deg,transparent 23px,#e6ecf5 23px,#e6ecf5 24px,transparent 24px) 0 0/100% 24px,linear-gradient(90deg,transparent 23px,#e6ecf5 23px,#e6ecf5 24px,transparent 24px) 0 0/24px 100%,#fff"
lab='font:600 13px IBM Plex Mono,monospace;letter-spacing:.12em;color:#5c6879;margin:0 0 10px'
def strip(x): x=re.sub(r'<\?xml[^>]*\?>','',x); return re.sub(r'<svg\b([^>]*)>',lambda m:'<svg'+re.sub(r'\s(width|height)="[^"]*"','',m.group(1))+' style="width:100%;height:auto;display:block">',x,1)
for nm,svg,title in [('one',one,'ONE PIECE · 01 + 01R · COMPLETED'),('two',two,'TWO PIECE · 01 + 50 + 01R · COMPLETED')]:
    open(f'{SP}/paint/{nm}.html','w').write(f'<!doctype html><meta charset="utf-8"><body style="margin:0;padding:34px 40px;{grid}"><div style="{lab}">{title}</div>{strip(svg)}</body>')
print('composed', len(one), len(two))
