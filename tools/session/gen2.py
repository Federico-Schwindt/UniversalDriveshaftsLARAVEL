#!/usr/bin/env python3
# Writes the v2 social artboards (P05–P12, S01–S12) into social2/project.
import os
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'social2', 'project')
CSS = '/_blob/f1795d10bcd304006c86680e07099521'
B = {
    'logo': '4b7a3d67674c578b9254396106bb11be',
    'companion': '13c185e7bc5707c5563c92e5c3fc5c54', 'end': '29e87c34ba80387f0d8d592b906effd1',
    'flange': 'c23f86849bab4191173026255d52ce5f', 'slip': '09fd5234745ce2771c3e1ff4b68fa41b',
    'ujoint': '252f089ab61eb8cac5e656f39f84b66a', 'pair': '0bae5d3c28010bf9cc9b99f42b9317f7',
    'weldyoke': '211b271fdc422d82ccc380f1931870fc',
    'mud': '9516ada9832371d5fdda5aa2ab9a87d9', 'semi': '361858fea5f89b7d81d80e3a24a1d8b5',
    'shaftclose': '2f20f8d978391aaac80b824a22044b31', 'under': '7d24f7c68f555388be3410c7a8482eda',
    'weld': '2f9ba2ca94c2dbaae7ca36374df42783', 'ujointclose': 'e6139ab032892b52246f56a9c5699684',
    'shopblue': '82a586282c2db72430e5513fc6e99b4b', 'lift': 'ff6b6c2b74fbba04cf1d2b2eeb266363',
    'undertruck': '6d701b892a856d33e21214ff8f35e670', 'duo': '407e8e118f3c5ca593a18cabf989c1d3',
    'smile': '8600e8aa3f379c0648e9ef2baa51f10a',
    'phone': 'bd24269d4ae7546dbcf920f2fe5fd63f', 'pdp': '3c80f40fcc143c3a885b265d4c96cef8',
    'flat': '40da062172b37cc19d11c6f4c75d9a50', 'laptop': '8143ec29af31f67f80e74651cefcf333',
    'finder': 'fa15453d22396e7d6ed1fe18dd667252',
    'pr_178x': 'a47226f63657215058052ee7878e8cfd',
    'pr_slip': '9eb03dea083750ee53bdfb4bf51f2e0e',
    'pr_end': 'c9aa6037039aa78069916d9d334dffff',
    'pr_flange': 'f66acde41a119b568178fd23a71e2c6b',
    'pr_weld': '53a7e4e37012869f58cc5c62adc058b9',
    'pr_csb': 'cf06c9014f56578841b61296e70e544c',
    'pr_comp': '3795f4583f72ce19901de7dd4ab2eb5d',
    'pr_tube': 'e93e578f8947f29d3a052d370e324e2f',
    'pr_cv': '14c3a00ce619823e8b400d78ba79fd26',

    'n_kit': 'd731498f907f709201ec42baa9c0f0f9',
    'n_truck': '04826e849a67a3734ab742dfc5f0b572',
    'n_balancer': 'bb4ac9d6b2b29feecd49778445e94314',
    'dev_builder': '9f1c955913657ed6412b7420a9a724f5',
    'dev_designer': '281f6b54b766d48283a27873ce3fad43',

    'n_over': '4d557c403c193246fe55e7a0ea37746b',
    'n_camper': '989cca5506b274cccda7f5302dba7308',
    'n_fleet': '53916437b2f473c414ddf143abdf8865',
    'n_label': '6a391f876729ad062020857d039815ab',
    'n_packer': '204a68157481efdbe836f37e30900069',
    'n_rust': 'b340b5cb3e2b201deb6c60b0c0b2683e',

}
def u(k): return '/_blob/' + B[k]

def svg(d, sw='2.2'):
    return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{sw}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{d}</svg>'
I = {
    'arrow': svg('<path d="M5 12h14M13 6l6 6-6 6"/>', '2.6'),
    'check': svg('<path d="M4 12.5l5 5L20 6.5"/>', '2.8'),
    'search': svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
    'truck': svg('<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>'),
    'case': svg('<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>'),
    'chat': svg('<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>'),
    'mic': svg('<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>'),
    'bolt': svg('<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'),
    'gauge': svg('<path d="M12 14l4-4"/><path d="M3.5 18a10 10 0 1 1 17 0"/>'),
    'wave': svg('<path d="M2 12c2-4 4-4 6 0s4 4 6 0 4-4 6 0"/>'),
    'drop': svg('<path d="M12 3s7 7.5 7 12a7 7 0 0 1-14 0c0-4.5 7-12 7-12z"/>'),
    'ruler': svg('<path d="M3 17 17 3l4 4L7 21z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/>'),
    'cog': svg('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'),
    'tag': svg('<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>'),
    'cal': svg('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'),
    'phone': svg('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>', '2'),
    'globe': svg('<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>', '2'),
    'link': svg('<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>'),
}
STAR = '<svg viewBox="0 0 100 100" aria-hidden="true"><path fill="{c}" d="M50.00 0.00L55.04 5.28L61.13 1.25L64.86 7.53L71.69 4.95L73.94 11.90L81.17 10.91L81.82 18.18L89.09 18.83L88.10 26.06L95.05 28.31L92.47 35.14L98.75 38.87L94.72 44.96L100.00 50.00L94.72 55.04L98.75 61.13L92.47 64.86L95.05 71.69L88.10 73.94L89.09 81.17L81.82 81.82L81.17 89.09L73.94 88.10L71.69 95.05L64.86 92.47L61.13 98.75L55.04 94.72L50.00 100.00L44.96 94.72L38.87 98.75L35.14 92.47L28.31 95.05L26.06 88.10L18.83 89.09L18.18 81.82L10.91 81.17L11.90 73.94L4.95 71.69L7.53 64.86L1.25 61.13L5.28 55.04L0.00 50.00L5.28 44.96L1.25 38.87L7.53 35.14L4.95 28.31L11.90 26.06L10.91 18.83L18.18 18.18L18.83 10.91L26.06 11.90L28.31 4.95L35.14 7.53L38.87 1.25L44.96 5.28Z"/><circle cx="50" cy="50" r="41" fill="{c}" stroke="#fff" stroke-width="1.8"/><circle cx="50" cy="50" r="36.5" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="1" stroke-dasharray="1.5 2.2"/></svg>'

FOOT = f'''  <div class="foot">
    <img src="{u('logo')}" alt="Universal Driveshafts">
    <div class="f-r">
      <span>{I['phone']}(813) 524-6491</span>
      <span>{I['globe']}universaldriveshafts.com</span>
    </div>
  </div>'''

def story_top(dark=True):
    # logo plate sits just under Instagram's top UI (~250px)
    return f'''  <div style="position: absolute; left: 64px; top: 262px; height: 74px; padding: 0 26px; border-radius: 999px; background: #fff; display: flex; align-items: center; box-shadow: 0 16px 34px -20px rgba(2,10,35,.6)"><img src="{u('logo')}" alt="Universal Driveshafts" style="height: 40px; display: block"></div>'''

def story_link(text='universaldriveshafts.com', dark=False, pos='left: 50%; top: 1540px; transform: translateX(-50%);'):
    bg = '#fff' if not dark else '#0b1630'
    fg = '#0b1630' if not dark else '#fff'
    return f'''  <div style="position: absolute; {pos} height: 84px; padding: 0 34px 0 14px; border-radius: 999px; background: {bg}; color: {fg}; display: flex; align-items: center; gap: 16px; font-weight: 700; font-size: 27px; white-space: nowrap; box-shadow: 0 20px 40px -22px rgba(2,10,35,.7)"><span style="display: grid; place-items: center; width: 58px; height: 58px; border-radius: 50%; background: #1246ab; color: #fff">{I['link'].replace('<svg', '<svg style="width:28px;height:28px"')}</span>{text}</div>'''

def cta(text, cls='cta-r', extra=''):
    return f'<span class="{cls}"{extra}>{text} <i>{I["arrow"]}</i></span>'

def chip(icon, text, cls='chip'):
    return f'<span class="{cls}"><i>{I[icon]}</i>{text}</span>'

def page(fname, title, w, h, body):
    html = f'''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{title}</title>
<script src="./support.js"></script>
<link rel="stylesheet" href="{CSS}">
</head>
<body>
<x-dc>
<helmet>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,700;0,800;1,800&amp;family=Poppins:wght@400;500;600;700&amp;family=IBM+Plex+Mono:wght@500;600&amp;display=swap" rel="stylesheet">
<style>body{{margin:0}}</style>
</helmet>
{body}
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{{"$preview":{{"width":{w},"height":{h}}}}}'>
class Component extends DCLogic {{
  renderVals() {{ return {{}}; }}
}}
</script>
</body>
</html>
'''
    open(os.path.join(OUT, fname), 'w').write(html)

def post(fname, title, cls, inner, foot=True):
    page(fname, title, 1080, 1350, f'<div class="art {cls}" style="width: 1080px; height: 1350px">\n{inner}\n{FOOT if foot else ""}\n</div>')

def story(fname, title, cls, inner):
    page(fname, title, 1080, 1920, f'<div class="art {cls}" style="width: 1080px; height: 1920px">\n{inner}\n</div>')

def wave(n=26, color='currentColor'):
    hs = [10, 18, 28, 22, 34, 16, 26, 38, 20, 30, 14, 24, 36, 18, 28, 12, 22, 32, 16, 26, 20, 34, 14, 24, 18, 10]
    return '<span class="wave" style="color: %s">' % color + ''.join(f'<i style="height: {hs[i % len(hs)]}px"></i>' for i in range(n)) + '</span>'

# ───────────────────────── POSTS ─────────────────────────

post('P05-Assistant.dc.html', 'Post 05 · UDS Assistant', 'bg-d', f'''  <img class="photo" src="{u('smile')}" alt="A smiling mechanic in a repair shop" style="object-position: 40% center">
  <div class="ov-l"></div>
  <div style="position: absolute; left: 64px; top: 92px; width: 640px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">Meet UDS Assistant</span>
    <h1 class="h" style="font-size: 104px">Ask a driveline<br>pro. <span class="hl-r">Anytime.</span></h1>
    <p class="p-s" style="width: 520px; color: rgba(255,255,255,.9)">Type or talk. It checks fitment, stock and your orders in seconds.</p>
  </div>
  <div style="position: absolute; left: 64px; top: 600px; width: 620px; display: flex; flex-direction: column; gap: 16px">
    <div class="bub bub-me">Where's my order?</div>
    <div class="bub bub-bot" style="display: flex; flex-direction: column; gap: 14px; max-width: 92%">
      <span>Shipped today with UPS. It arrives <b>Thursday</b>.</span>
      <span style="display: flex; align-items: center; gap: 10px">
        <i style="display: block; width: 22px; height: 22px; border-radius: 50%; background: #1246ab"></i><i style="display: block; flex: 1; height: 6px; border-radius: 3px; background: #1246ab"></i>
        <i style="display: block; width: 22px; height: 22px; border-radius: 50%; background: #1246ab"></i><i style="display: block; flex: 1; height: 6px; border-radius: 3px; background: #d6e0f5"></i>
        <i style="display: block; width: 22px; height: 22px; border-radius: 50%; background: #d6e0f5"></i>
      </span>
      <span class="mono" style="font-size: 15px; color: #5a6782; display: flex; justify-content: space-between"><span>Packed</span><span>Shipped</span><span>Delivered</span></span>
    </div>
    <div class="bub bub-me" style="display: flex; align-items: center; gap: 16px"><span style="display: grid; place-items: center; width: 50px; height: 50px; border-radius: 50%; background: #fff; color: #1d5fe8">{I['mic'].replace('<svg', '<svg style="width:26px;height:26px"')}</span>{wave(22, '#fff')}<span class="mono" style="font-size: 16px">0:04</span></div>
  </div>''')

post('P06-Coupon.dc.html', 'Post 06 · 5% coupon', 'bg-e', f'''  <div class="mark" style="font-size: 640px; left: 380px; top: -40px">5%</div>
  <div class="slash" style="left: 640px; top: 560px; width: 150px; height: 1000px"></div>
  <div class="slash-o" style="left: 860px; top: 560px; width: 60px; height: 1000px"></div>
  <div style="position: absolute; left: 64px; top: 92px; width: 640px; display: flex; flex-direction: column; gap: 20px">
    <span class="kick">A thank-you before the big switch</span>
    <div style="display: flex; align-items: flex-end; gap: 18px">
      <span class="big-n" style="font-size: 300px; line-height: .8">5%</span>
      <span class="h" style="font-size: 120px; line-height: .9; padding-bottom: 6px">Off</span>
    </div>
    <h1 class="h" style="font-size: 64px">Your next order.<br><span class="hl">Any part.</span></h1>
  </div>
  <div class="ticket" style="left: 64px; top: 760px; width: 520px; height: 170px; --notch: #1446c4; align-items: center; padding: 0 40px; gap: 30px">
    <div style="display: flex; flex-direction: column; gap: 4px"><span class="mono" style="font-size: 16px; color: #5a6782">Use code</span><span class="big-n" style="font-size: 96px; color: #1246ab; letter-spacing: .04em">UDS5</span></div>
    <span class="dash" style="height: 110px"></span>
    <span class="mono" style="font-size: 16px; color: #e11d2a; line-height: 1.4">Valid<br>through<br>Nov 20</span>
  </div>
  <div style="position: absolute; left: 64px; top: 966px; display: flex; flex-direction: column; gap: 18px">
    {cta('Shop now')}
    <span style="font-size: 18px; color: rgba(255,255,255,.8)">One use per customer · universaldriveshafts.com</span>
  </div>
  <img class="prod" src="{u('flange')}" alt="Flange yoke" style="position: absolute; left: 600px; top: 420px; width: 430px; height: 450px; transform: rotate(-8deg)">
  <img class="prod" src="{u('pair')}" alt="U-joints" style="position: absolute; left: 650px; top: 790px; width: 330px; height: 327px">''')

post('P07-UjointSigns.dc.html', 'Post 07 · Bad u-joint signs', 'bg-w', f'''  <div class="slash-b" style="left: 560px; top: -100px; width: 900px; height: 1600px; background: #0a2462"></div>
  <div class="slash" style="left: 520px; top: -100px; width: 26px; height: 1600px; background: #e11d2a"></div>
  <img class="prod" src="{u('ujoint')}" alt="A universal joint" style="position: absolute; left: 600px; top: 120px; width: 440px; height: 355px; transform: rotate(-12deg)">
  <div style="position: absolute; left: 64px; top: 92px; width: 560px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick" style="color: #1246ab">Driveline 101</span>
    <h1 class="h" style="font-size: 108px; color: #0b1630">That clunk<br>isn't<br><span class="hl-r">normal.</span></h1>
    <p class="p-s" style="width: 480px; color: #5a6782">4 signs your u-joint is on its way out:</p>
  </div>
  <div style="position: absolute; left: 64px; top: 640px; width: 952px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px">
    <div class="card" style="padding: 26px 26px; display: flex; gap: 18px; align-items: center"><span class="ic">{I['cog']}</span><span style="font-size: 24px; font-weight: 600; line-height: 1.25">Clunk when you shift into gear</span></div>
    <div class="card" style="padding: 26px 26px; display: flex; gap: 18px; align-items: center"><span class="ic">{I['gauge']}</span><span style="font-size: 24px; font-weight: 600; line-height: 1.25">Vibration at highway speed</span></div>
    <div class="card" style="padding: 26px 26px; display: flex; gap: 18px; align-items: center"><span class="ic">{I['wave']}</span><span style="font-size: 24px; font-weight: 600; line-height: 1.25">Squeak that follows wheel speed</span></div>
    <div class="card" style="padding: 26px 26px; display: flex; gap: 18px; align-items: center"><span class="ic ic-r">{I['drop']}</span><span style="font-size: 24px; font-weight: 600; line-height: 1.25">Rust dust around the caps</span></div>
  </div>
  <div style="position: absolute; left: 64px; top: 990px">{cta('Find your u-joint')}</div>''')

post('P08-Yokes.dc.html', 'Post 08 · Every yoke', 'bg-i', f'''  <div class="mark mark-b" style="font-size: 420px; left: -20px; top: 860px">YOKES</div>
  <div style="position: absolute; left: 64px; top: 92px; width: 952px; display: flex; flex-direction: column; gap: 20px">
    <span class="kick" style="color: #1246ab">In stock, 1310 to 1810 series</span>
    <h1 class="h" style="font-size: 112px; color: #0b1630">Every yoke.<br><span class="hl-b">Every series.</span></h1>
  </div>
  <div style="position: absolute; left: 64px; top: 450px; width: 952px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px">
    <div class="card" style="height: 400px; padding: 24px 18px 22px; display: flex; flex-direction: column; align-items: center; justify-content: space-between"><img class="prod" src="{u('end')}" alt="End yoke" style="width: 190px; height: 200px; margin-top: 20px"><span class="h" style="font-size: 34px; color: #0b1630; font-weight: 700">End yokes</span></div>
    <div class="card" style="height: 400px; padding: 24px 18px 22px; display: flex; flex-direction: column; align-items: center; justify-content: space-between; background: #1246ab; color: #fff"><img class="prod" src="{u('slip')}" alt="Slip yoke" style="width: 150px; height: 250px"><span class="h" style="font-size: 34px; font-weight: 700">Slip yokes</span></div>
    <div class="card" style="height: 400px; padding: 24px 18px 22px; display: flex; flex-direction: column; align-items: center; justify-content: space-between"><img class="prod" src="{u('weldyoke')}" alt="Weld yoke" style="width: 190px; height: 210px; margin-top: 16px"><span class="h" style="font-size: 34px; color: #0b1630; font-weight: 700">Weld yokes</span></div>
    <div class="card" style="height: 400px; padding: 24px 18px 22px; display: flex; flex-direction: column; align-items: center; justify-content: space-between"><img class="prod" src="{u('flange')}" alt="Flange yoke" style="width: 200px; height: 210px; margin-top: 16px"><span class="h" style="font-size: 34px; color: #0b1630; font-weight: 700">Flange yokes</span></div>
  </div>
  <div style="position: absolute; left: 64px; top: 900px; width: 952px; display: flex; align-items: center; justify-content: space-between">
    <div style="display: flex; align-items: baseline; gap: 16px"><span class="big-n" style="font-size: 110px; color: #e11d2a; line-height: 1">6,821</span><span class="mono" style="font-size: 18px; color: #0b1630; line-height: 1.4">driveline parts<br>ready to ship</span></div>
    {cta('Shop yokes')}
  </div>''')

post('P09-BuiltInFlorida.dc.html', 'Post 09 · Built in Florida', 'bg-d', f'''  <img class="photo" src="{u('weld')}" alt="A welder building a driveshaft" style="object-position: center 40%">
  <div class="ov-b"></div>
  <div class="ov-t" style="opacity: .7"></div>
  <div class="seal" style="left: 816px; top: 72px; width: 200px; height: 200px; transform: rotate(-10deg)">{STAR.format(c='#1d5fe8')}<span style="font-size: 32px">Balanced<br>in-house</span></div>
  <div style="position: absolute; left: 64px; top: 92px"><span class="kick">Behind every shaft</span></div>
  <div style="position: absolute; left: 64px; top: 600px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <h1 class="h" style="font-size: 112px">Welded here.<br>Balanced here.<br><span class="hl">Shipped from here.</span></h1>
    <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-top: 6px">
      {chip('check', 'Built in our Florida shop', 'chip chip-d')}
      {chip('check', 'Ships same day by 2 pm ET', 'chip chip-d')}
    </div>
  </div>''')

post('P10-OffRoad.dc.html', 'Post 10 · Off-road', 'bg-d', f'''  <img class="photo" src="{u('mud')}" alt="A pickup truck splashing through mud" style="object-position: 40% center">
  <div class="ov-t"></div>
  <div style="position: absolute; inset: auto 0 0 0; height: 560px; background: linear-gradient(180deg, rgba(7,20,49,0), rgba(7,20,49,.85) 45%)"></div>
  <div style="position: absolute; left: 64px; top: 92px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">Lifted. Loaded. Driven hard.</span>
    <h1 class="h" style="font-size: 112px">Mud doesn't care<br>about <span class="hl-r">stock parts.</span></h1>
  </div>
  <div style="position: absolute; left: 64px; top: 830px; width: 952px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px">
    <div class="card" style="padding: 26px 24px; display: flex; flex-direction: column; gap: 14px"><span class="ic">{I['ruler']}</span><span style="font-size: 24px; font-weight: 700; line-height: 1.2">Built to your lift height</span></div>
    <div class="card" style="padding: 26px 24px; display: flex; flex-direction: column; gap: 14px"><span class="ic">{I['cog']}</span><span style="font-size: 24px; font-weight: 700; line-height: 1.2">Greasable u-joints available</span></div>
    <div class="card" style="padding: 26px 24px; display: flex; flex-direction: column; gap: 14px"><span class="ic ic-r">{I['bolt']}</span><span style="font-size: 24px; font-weight: 700; line-height: 1.2">1310 to 1810 series</span></div>
  </div>''')

post('P11-Business.dc.html', 'Post 11 · Business pricing', 'bg-w', f'''  <img src="{u('lift')}" alt="A mechanic under a car on a lift" style="position: absolute; left: 0; top: 0; width: 1080px; height: 560px; object-fit: cover; object-position: center 60%">
  <div style="position: absolute; left: 0; top: 0; width: 1080px; height: 560px; background: linear-gradient(90deg, rgba(7,20,49,.92) 0%, rgba(7,20,49,.6) 55%, rgba(7,20,49,.1) 100%)"></div>
  <div class="slash" style="left: -80px; top: 520px; width: 1300px; height: 90px; transform: skewY(-3deg)"></div>
  <div style="position: absolute; left: 64px; top: 92px; width: 760px; display: flex; flex-direction: column; gap: 22px; color: #fff">
    <span class="kick">For shops and fleets</span>
    <h1 class="h" style="font-size: 104px">Buy more.<br><span class="hl">Pay less.</span></h1>
    <p class="p-s" style="width: 560px; color: rgba(255,255,255,.9)">Your business discount applies automatically at checkout.</p>
  </div>
  <div style="position: absolute; left: 64px; top: 640px; width: 952px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: end">
    <div class="card" style="padding: 30px 26px; display: flex; flex-direction: column; gap: 8px; border: 2px solid #e3eaf8; box-shadow: none"><span class="mono" style="font-size: 16px; color: #a0612b">Bronze</span><span class="big-n" style="font-size: 104px; color: #0b1630">5%</span><span style="font-size: 19px; color: #5a6782; line-height: 1.35">Approved business accounts</span></div>
    <div class="card" style="padding: 30px 26px; display: flex; flex-direction: column; gap: 8px; border: 2px solid #e3eaf8; box-shadow: none"><span class="mono" style="font-size: 16px; color: #6b7a90">Silver</span><span class="big-n" style="font-size: 104px; color: #1246ab">8%</span><span style="font-size: 19px; color: #5a6782; line-height: 1.35">From $5,500 a month</span></div>
    <div class="card" style="padding: 40px 26px; display: flex; flex-direction: column; gap: 8px; background: #1246ab; color: #fff"><span class="mono" style="font-size: 16px; color: #ffd36b">Gold</span><span class="big-n" style="font-size: 124px">10%</span><span style="font-size: 19px; color: rgba(255,255,255,.88); line-height: 1.35">From $7,000 a month</span></div>
  </div>
  <div style="position: absolute; left: 64px; top: 1010px; width: 952px; display: flex; align-items: center; justify-content: space-between">
    <span style="display: flex; align-items: center; gap: 14px; font-size: 24px; font-weight: 600; color: #0b1630"><span class="ic" style="width: 52px; height: 52px; border-radius: 14px">{I['case']}</span>Net 20 terms for approved accounts</span>
    {cta('Apply', extra=' style="height: 76px; font-size: 26px"')}
  </div>''')

post('P12-Live.dc.html', 'Post 12 · We are live', 'bg-e', f'''  <div class="mark" style="font-size: 600px; left: -40px; top: 820px">LIVE</div>
  <div class="slash" style="left: 700px; top: -200px; width: 200px; height: 1800px; opacity: .95"></div>
  <div class="slash" style="left: 960px; top: -200px; width: 40px; height: 1800px; background: #e11d2a"></div>
  <img src="{u('pdp')}" alt="A product page on the new site" style="position: absolute; left: 520px; top: 110px; width: 576px; height: 1084px">
  <div style="position: absolute; left: 64px; top: 92px; width: 560px; display: flex; flex-direction: column; gap: 24px">
    <span style="display: inline-flex; align-items: center; gap: 14px; align-self: flex-start; height: 52px; padding: 0 22px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1.5px solid rgba(255,255,255,.35); font-family: 'IBM Plex Mono', monospace; font-weight: 600; font-size: 19px; letter-spacing: .14em; text-transform: uppercase"><span class="dot"></span>Now live</span>
    <h1 class="h" style="font-size: 150px; line-height: .9">It's<br><span class="hl">live.</span></h1>
    <p class="p-s" style="width: 440px; color: rgba(255,255,255,.92)">The new universaldriveshafts.com is open. Same parts, same people. A much better way to order.</p>
    <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 4px">
      {chip('search', 'Find parts by vehicle')}
      {chip('truck', 'Track every order live')}
    </div>
    {cta('Shop the new site', extra=' style="margin-top: 14px"')}
  </div>''')

# ───────────────────────── STORIES ─────────────────────────

story('S01-Teaser.dc.html', 'Story 01 · Teaser', 'bg-d', f'''  <img class="photo" src="{u('shopblue')}" alt="A modern repair shop with a truck on a lift">
  <div class="ov-blue"></div>
  <div style="position: absolute; inset: auto 0 0 0; height: 1100px; background: linear-gradient(180deg, rgba(7,20,49,0), rgba(7,20,49,.92) 55%)"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 880px; width: 952px; display: flex; flex-direction: column; gap: 26px">
    <span class="kick">Something new is coming</span>
    <h1 class="h" style="font-size: 132px">We've been<br>building<br><span class="hl">something.</span></h1>
    <p class="p" style="width: 820px; color: rgba(255,255,255,.92)">A brand-new universaldriveshafts.com. Opening November 20.</p>
  </div>
{story_link('Turn on post notifications', True)}''')

cal_days = ''.join('<span></span>' for _ in range(0)) + ''.join(
    f'<span class="on">{d}</span>' if d == 20 else f'<span>{d}</span>' for d in range(1, 31))
story('S02-SaveDate.dc.html', 'Story 02 · Save the date', 'bg-w', f'''  <div class="slash-b" style="left: 380px; top: 1200px; width: 1400px; height: 1000px"></div>
  <div class="mark mark-b" style="font-size: 460px; left: 360px; top: 300px">20</div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 420px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick" style="color: #1246ab">Save the date</span>
    <h1 class="h" style="font-size: 124px; color: #0b1630">Mark your<br><span class="hl-r">calendar.</span></h1>
    <p class="p" style="width: 780px; color: #5a6782">The new universaldriveshafts.com opens Thursday, November 20.</p>
  </div>
  <div class="card" style="position: absolute; left: 64px; top: 900px; width: 952px; padding: 34px 36px 38px">
    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 22px"><span class="h" style="font-size: 54px; color: #0b1630">November 2026</span><span class="mono" style="font-size: 17px; color: #e11d2a">Launch day</span></div>
    <div class="cal" style="margin-bottom: 10px">{''.join(f'<span style="background: none; height: 34px; font-size: 17px; color: #8a96ad">{d}</span>' for d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'])}</div>
    <div class="cal">{cal_days}</div>
  </div>
{story_link('Set a reminder for Nov 20', True)}''')

story('S03-SneakPeek.dc.html', 'Story 03 · Sneak peek', 'bg-e', f'''  <div class="mark" style="font-size: 520px; left: -30px; top: 1380px">PEEK</div>
  <div class="slash" style="left: 660px; top: -200px; width: 220px; height: 2400px"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 420px; width: 560px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">Sneak peek</span>
    <h1 class="h" style="font-size: 96px">Product pages<br>that answer<br><span class="hl">everything.</span></h1>
    <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 10px">
      {chip('ruler', 'Full specs and series')}
      {chip('search', 'Cross-reference numbers')}
      {chip('bolt', 'Live stock count')}
    </div>
  </div>
  <img src="{u('pdp')}" alt="A product page on the new site" style="position: absolute; left: 540px; top: 700px; width: 560px; height: 1054px">''')

story('S04-Coupon.dc.html', 'Story 04 · Coupon', 'bg-d', f'''  <div style="position: absolute; inset: 0; background: linear-gradient(160deg, #e11d2a 0%, #b5121d 60%, #7d0c14 100%)"></div>
  <div class="mark" style="font-size: 760px; left: -60px; top: 380px; color: rgba(255,255,255,.08)">5%</div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 420px; width: 952px; display: flex; flex-direction: column; gap: 18px">
    <span class="kick" style="--x: 0">Thank-you deal</span>
    <div style="display: flex; align-items: flex-end; gap: 20px"><span class="big-n" style="font-size: 330px; line-height: .8">5%</span><span class="h" style="font-size: 140px; line-height: .9; padding-bottom: 8px">Off</span></div>
    <p class="p" style="width: 760px; color: rgba(255,255,255,.95)">Every part, one order. Our way of saying thanks before the new site opens.</p>
  </div>
  <img class="prod" src="{u('flange')}" alt="Flange yoke" style="position: absolute; left: 560px; top: 820px; width: 470px; height: 492px; transform: rotate(-10deg)">
  <img class="prod" src="{u('ujoint')}" alt="U-joint" style="position: absolute; left: 80px; top: 900px; width: 420px; height: 339px; transform: rotate(10deg)">
  <div class="ticket" style="left: 120px; top: 1300px; width: 840px; height: 180px; --notch: #a8101a; align-items: center; justify-content: space-between; padding: 0 56px">
    <div style="display: flex; flex-direction: column; gap: 4px"><span class="mono" style="font-size: 17px; color: #5a6782">Use code</span><span class="big-n" style="font-size: 104px; color: #e11d2a; letter-spacing: .05em">UDS5</span></div>
    <span class="dash" style="height: 120px"></span>
    <span class="mono" style="font-size: 18px; color: #0b1630; line-height: 1.45">One use per<br>customer · ends<br>Nov 20</span>
  </div>
{story_link('Shop universaldriveshafts.com')}''')

story('S05-Finder.dc.html', 'Story 05 · Finder', 'bg-d', f'''  <div class="slash-b" style="left: -300px; top: 1100px; width: 1800px; height: 300px; transform: skewY(-8deg)"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 420px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">New: vehicle finder</span>
    <h1 class="h" style="font-size: 120px">Your driveshaft<br>in <span class="hl">3 taps.</span></h1>
  </div>
  <img src="{u('finder')}" alt="The vehicle finder on a laptop" style="position: absolute; left: 20px; top: 760px; width: 1040px; height: 708px">
  <div style="position: absolute; left: 64px; top: 1390px; width: 952px; display: flex; justify-content: center; gap: 14px">
    <span class="chip"><i>1</i>Make</span><span class="chip"><i>2</i>Year</span><span class="chip"><i style="background: #e11d2a">3</i>Model</span>
  </div>
{story_link()}''')

story('S06-Quiz.dc.html', 'Story 06 · Poll', 'bg-i', f'''  <img class="prod" src="{u('pair')}" alt="Two universal joints" style="position: absolute; left: 610px; top: 300px; width: 430px; height: 426px; transform: rotate(12deg)">
{story_top()}
  <div style="position: absolute; left: 64px; top: 460px; width: 520px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick" style="color: #1246ab">Quick poll</span>
    <h1 class="h" style="font-size: 110px; color: #0b1630">Which one<br>are you<br><span class="hl-b">trying first?</span></h1>
  </div>
  <div style="position: absolute; left: 64px; top: 960px; width: 952px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px">
    <div class="card" style="padding: 30px; display: flex; flex-direction: column; gap: 16px"><span class="ic">{I['search']}</span><span style="font-size: 28px; font-weight: 700">Vehicle finder</span></div>
    <div class="card" style="padding: 30px; display: flex; flex-direction: column; gap: 16px"><span class="ic">{I['ruler']}</span><span style="font-size: 28px; font-weight: 700">Online builder</span></div>
    <div class="card" style="padding: 30px; display: flex; flex-direction: column; gap: 16px"><span class="ic">{I['truck']}</span><span style="font-size: 28px; font-weight: 700">Live tracking</span></div>
    <div class="card" style="padding: 30px; display: flex; flex-direction: column; gap: 16px"><span class="ic ic-r">{I['chat']}</span><span style="font-size: 28px; font-weight: 700">UDS Assistant</span></div>
  </div>
  <div class="ghost" style="position: absolute; left: 64px; top: 1430px; width: 952px; height: 120px; border: 3px dashed #9fb3dc; border-radius: 26px; display: grid; place-items: center; color: #5a6782; font-size: 22px; font-weight: 600">Place the Instagram poll sticker here</div>''')

story('S07-Shop.dc.html', 'Story 07 · Behind the scenes', 'bg-d', f'''  <img class="photo" src="{u('under')}" alt="A driveshaft installed under a truck">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.75) 0%, rgba(7,20,49,.1) 30%, rgba(7,20,49,.15) 50%, rgba(7,20,49,.95) 78%)"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 1080px; width: 952px; display: flex; flex-direction: column; gap: 24px">
    <span class="kick">Behind the scenes</span>
    <h1 class="h" style="font-size: 116px">No shaft leaves<br>until it's <span class="hl">balanced.</span></h1>
    <p class="p" style="width: 800px; color: rgba(255,255,255,.9)">Every custom driveshaft is checked in our Florida shop before it ships.</p>
  </div>''')

story('S08-Stock.dc.html', 'Story 08 · Search', 'bg-e', f'''  <div class="mark" style="font-size: 430px; left: -20px; top: 1350px">6,821</div>
  <img class="prod" src="{u('end')}" alt="End yoke" style="position: absolute; left: 60px; top: 980px; width: 330px; height: 301px; transform: rotate(-12deg)">
  <img class="prod" src="{u('companion')}" alt="Companion flange" style="position: absolute; left: 400px; top: 1020px; width: 260px; height: 305px">
  <img class="prod" src="{u('slip')}" alt="Slip yoke" style="position: absolute; left: 720px; top: 940px; width: 220px; height: 384px; transform: rotate(14deg)">
{story_top()}
  <div style="position: absolute; left: 64px; top: 420px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">Find it fast</span>
    <h1 class="h" style="font-size: 126px">6,821 parts.<br><span class="hl">One search bar.</span></h1>
  </div>
  <div style="position: absolute; left: 64px; top: 760px; width: 952px; height: 110px; border-radius: 999px; background: #fff; display: flex; align-items: center; gap: 22px; padding: 0 16px 0 38px; color: #0b1630; box-shadow: 0 30px 50px -26px rgba(2,10,35,.7)">
    <span style="color: #1246ab; display: grid">{I['search'].replace('<svg', '<svg style="width:38px;height:38px"')}</span>
    <span style="flex: 1; font-size: 32px; font-weight: 500">1350 slip yoke<span style="display: inline-block; width: 3px; height: 36px; margin-left: 4px; vertical-align: middle; background: #1246ab"></span></span>
    <span style="display: grid; place-items: center; width: 80px; height: 80px; border-radius: 50%; background: #e11d2a; color: #fff">{I['arrow'].replace('<svg', '<svg style="width:34px;height:34px"')}</span>
  </div>
{story_link()}''')

story('S09-AskUDS.dc.html', 'Story 09 · Ask UDS', 'bg-d', f'''  <img class="photo" src="{u('duo')}" alt="Two mechanics working under a vehicle" style="object-position: 62% center">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.55) 0%, rgba(7,20,49,.2) 28%, rgba(7,20,49,.92) 60%)"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 980px; width: 952px; display: flex; flex-direction: column; gap: 18px">
    <span class="kick">UDS Assistant</span>
    <h1 class="h" style="font-size: 104px">Stuck on a part?<br><span class="hl">Just ask.</span></h1>
    <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 18px">
      <div class="bub bub-me">Do you have a 1410 u-joint in stock?</div>
      <div class="bub bub-bot" style="display: flex; align-items: center; gap: 16px"><span style="display: grid; place-items: center; width: 50px; height: 50px; border-radius: 50%; background: #1246ab; color: #fff; flex: none">{I['mic'].replace('<svg', '<svg style="width:24px;height:24px"')}</span>{wave(20, '#1246ab')}<span class="mono" style="font-size: 16px; color: #5a6782">0:07</span></div>
    </div>
  </div>
{story_link('Sign in to chat')}''')

story('S10-LastCall.dc.html', 'Story 10 · Last call', 'bg-d', f'''  <div class="slash" style="left: 560px; top: -200px; width: 120px; height: 2400px; background: #e11d2a"></div>
  <div class="slash-o" style="left: 760px; top: -200px; width: 60px; height: 2400px"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 440px; width: 952px; display: flex; flex-direction: column; gap: 20px">
    <span class="kick">Last call</span>
    <span class="big-n" style="font-size: 420px; line-height: .82">48</span>
    <h1 class="h" style="font-size: 120px">Hours left<br>for <span class="hl-r">5% off.</span></h1>
  </div>
  <div class="ticket" style="left: 64px; top: 1260px; width: 952px; height: 170px; --notch: #0a2462; align-items: center; justify-content: space-between; padding: 0 56px">
    <div style="display: flex; flex-direction: column; gap: 4px"><span class="mono" style="font-size: 17px; color: #5a6782">Code</span><span class="big-n" style="font-size: 100px; color: #1246ab; letter-spacing: .05em">UDS5</span></div>
    <span class="mono" style="font-size: 20px; color: #e11d2a; line-height: 1.4; text-align: right">Ends Nov 20<br>11:59 pm</span>
  </div>
{story_link('Shop before it ends')}''')

story('S11-Tomorrow.dc.html', 'Story 11 · Tomorrow', 'bg-d', f'''  <img class="photo" src="{u('semi')}" alt="A red semi truck at sunset" style="object-position: 62% center">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.6) 0%, rgba(7,20,49,0) 30%, rgba(7,20,49,.1) 52%, rgba(7,20,49,.93) 76%)"></div>
{story_top()}
  <div style="position: absolute; left: 64px; top: 1150px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">See you tomorrow</span>
    <h1 class="h" style="font-size: 170px; line-height: .9">Tomorrow.</h1>
    <p class="p" style="width: 820px; color: rgba(255,255,255,.92)">Tomorrow morning the new site goes live. Set an alarm, be first through the door.</p>
  </div>''')

story('S12-Live.dc.html', 'Story 12 · Live', 'bg-e', f'''  <div class="mark" style="font-size: 640px; left: -40px; top: 1320px">LIVE</div>
  <div class="slash" style="left: 690px; top: -200px; width: 230px; height: 2400px"></div>
  <img src="{u('phone')}" alt="The new site on a phone" style="position: absolute; left: 480px; top: 620px; width: 600px; height: 1129px">
{story_top()}
  <div style="position: absolute; left: 64px; top: 420px; width: 560px; display: flex; flex-direction: column; gap: 22px">
    <span style="display: inline-flex; align-items: center; gap: 14px; align-self: flex-start; height: 52px; padding: 0 22px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1.5px solid rgba(255,255,255,.35); font-family: 'IBM Plex Mono', monospace; font-weight: 600; font-size: 19px; letter-spacing: .14em; text-transform: uppercase"><span class="dot"></span>Now live</span>
    <h1 class="h" style="font-size: 150px; line-height: .9">We're<br><span class="hl">live!</span></h1>
    <p class="p-s" style="width: 380px; color: rgba(255,255,255,.92)">Come see the new universaldriveshafts.com.</p>
  </div>
{story_link('Shop now', False, 'left: 64px; top: 980px;')}''')

print('ok')
