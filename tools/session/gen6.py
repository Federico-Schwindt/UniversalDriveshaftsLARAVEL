#!/usr/bin/env python3
# Ten product promo posts (R01–R10) in the owner's Instagram style: big logo, giant part number, tagline, product, price.
import importlib.util, os, json
HERE = os.path.dirname(os.path.abspath(__file__))
sp = importlib.util.spec_from_file_location('gen2', os.path.join(HERE, 'gen2.py')); g = importlib.util.module_from_spec(sp); sp.loader.exec_module(g)
u, I, cta, chip, post = g.u, g.I, g.cta, g.chip, g.post

# sku, image key, category, tagline, spec chips, price, in stock
P = [
  ('5-1310X',   'pair',    'Universal joint · 1310 series',    'The half-ton workhorse.<br>Lube for life.',            ['1.062 in caps', '3.219 in across', 'Sealed, non-greaseable'], 11.50, True),
  ('5-178X',    'pr_178x', 'Spicer universal joint · 1350 series', 'Heavy duty.<br>The off-road favorite.',           ['1.188 in caps', '3.622 in across', 'Spicer'], 21.19, True),
  ('2-3-6061KX','pr_slip', 'Slip yoke · 1310 series',          'Heavy-duty slip.<br>1.500 × 16 spline.',              ['1.500 × 16 spline', '6.875 in CL to end', 'Heavy duty'], 58.04, True),
  ('3-4-1501-1','pr_end',  'End yoke · 1350 series',           'Strap style.<br>Bolts on, stays on.',                 ['1.500 × 10 spline', '2.250 in hub', 'Strap style'], 58.00, True),
  ('3502-256',  'pr_flange','Flange yoke · 1350 series',       'Four bolts.<br>Zero guesswork.',                      ['4.250 in bolt circle', '2.559 in pilot', '1350 series'], 36.56, True),
  ('3-28-427',  'pr_weld', 'Tube weld yoke · 1350 series',     'Built for 3.5 in tube.<br>Welded by us or by you.',   ['3.500 × 0.083 tube', '3.344 in butt', '1.840 in CL to weld'], 29.95, True),
  ('2780-90',   'pr_csb',  'Center support bearing',           'The hum you hear<br>at 50 mph? This.',                ['1.378 in ID', 'BMW / Land Rover', 'Rubber cushion'], 80.65, True),
  ('3101-282',  'pr_comp', 'Companion flange',                 '28 splines.<br>One perfect fit.',                     ['1.218 in spline dia', '28 splines', '1.812 in hub'], 68.95, True),
  ('ATU4583',   'pr_tube', 'Aluminum driveshaft tubing',       'Half the weight.<br>Full 72 inches.',                 ['4.500 in OD', '0.083 in wall', '72 in length'], 376.89, True),
  ('212024X',   'pr_cv',   'CV flange yoke · 1350 series',     'Double cardan.<br>Lifted-truck smooth.',              ['0.500 in ball stud', '4.25 in bolt circle', '1350 series'], 135.95, True),
]
ACC = [('#1246ab', '#e11d2a'), ('#e11d2a', '#1246ab'), ('#0a2462', '#e11d2a')]

def sq(x, y, s, c, o=1):
    return f'<div style="position: absolute; left: {x}px; top: {y}px; width: {s}px; height: {s}px; background: {c}; opacity: {o}"></div>'

for i, (sku, img, cat, tag, specs, price, stock) in enumerate(P):
    a, b = ACC[i % 3]
    right = i % 2 == 0
    imgx = 500 if right else 20
    txtx = 64
    dollars, cents = f'{price:,.2f}'.split('.')
    stock_pill = f'<span style="position: absolute; right: 64px; top: 80px; display: inline-flex; align-items: center; gap: 10px; height: 48px; padding: 0 20px; border-radius: 999px; background: #0b1630; color: #fff; font-family: \'IBM Plex Mono\', monospace; font-weight: 600; font-size: 16px; letter-spacing: .12em; text-transform: uppercase"><span class="dot" style="background: #2ee36b; box-shadow: 0 0 0 5px rgba(46,227,107,.25)"></span>In stock</span>' if stock else ''
    chips = ''.join(f'<span class="chip" style="height: 54px; font-size: 20px; padding: 0 20px; box-shadow: none; border: 2px solid #e3eaf8"><i style="width: 10px; height: 10px; border-radius: 50%; background: {b}; min-width: 0"></i>{s}</span>' for s in specs)
    body = f'''  {sq(0, 1010, 150, a)}{sq(150, 1160, 90, a, .55)}{sq(990, 300, 90, b)}{sq(930, 240, 60, b, .5)}
  <img src="{u('logo')}" alt="Universal Driveshafts" style="position: absolute; left: 64px; top: 72px; height: 72px; width: auto">
  {stock_pill}
  <div style="position: absolute; left: {txtx}px; top: 230px; width: 952px; display: flex; flex-direction: column; gap: 10px">
    <span class="kick" style="color: {a}">{cat}</span>
    <span class="big-n" style="font-size: {150 if len(sku) <= 8 else 118}px; color: {a}; letter-spacing: -.01em; line-height: .9">{sku}</span>
    <span style="width: 340px; height: 14px; background: {a}; margin-top: 6px"></span>
    <h1 class="h" style="font-size: 72px; color: #0b1630; margin-top: 18px">{tag}</h1>
  </div>
  <div style="position: absolute; left: 64px; top: 700px; width: 952px; display: flex; gap: 12px; flex-wrap: wrap">{chips}</div>
  <img class="prod" src="{u(img)}" alt="{sku}" style="position: absolute; left: {imgx}px; top: 760px; width: 560px; height: 480px; object-fit: contain; object-position: {'right' if right else 'left'} bottom">
  <div class="card" style="position: absolute; {'left: 64px' if right else 'right: 64px'}; top: 980px; width: 380px; padding: 24px 28px; display: flex; flex-direction: column; gap: 10px">
    <span class="mono" style="font-size: 14px; color: #5a6782">Your price</span>
    <span style="display: flex; align-items: baseline; gap: 4px; color: #0b1630"><span class="big-n" style="font-size: 76px">${dollars}</span><span class="big-n" style="font-size: 36px">.{cents}</span></span>
    <span style="font-size: 17px; color: #5a6782">Order by 2 pm ET, ships today</span>
    <span style="display: inline-flex; align-items: center; justify-content: center; gap: 12px; height: 58px; border-radius: 999px; background: {b}; color: #fff; font-weight: 700; font-size: 21px; margin-top: 6px">Shop now {I['arrow'].replace('<svg', '<svg style="width:22px;height:22px"')}</span>
  </div>
  <span class="mono" style="position: absolute; left: 64px; bottom: 40px; font-size: 15px; color: #5a6782">universaldriveshafts.com · (813) 524-6491</span>'''
    post(f'R{i+1:02d}-{sku.replace("/", "-")}.dc.html', f'Product · {sku}', 'bg-i', body, foot=False)
print('ok gen6')
