#!/usr/bin/env python3
# Guide carousels + technical posts (real data from resources/views/guides). Reuses gen2 helpers.
import importlib.util, os, sys
HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('gen2', os.path.join(HERE, 'gen2.py'))
g = importlib.util.module_from_spec(spec); spec.loader.exec_module(g)
u, I, cta, chip, post, FOOT, STAR = g.u, g.I, g.cta, g.chip, g.post, g.FOOT, g.STAR

def swipe(i, n):
    # slide counter + swipe cue, top-right of every carousel slide
    arrow = '' if i == n else ' <span style="font-size: 26px; line-height: 1">→</span>'
    return f'<span class="mono" style="position: absolute; right: 64px; top: 100px; display: inline-flex; align-items: center; gap: 10px; height: 46px; padding: 0 20px; border-radius: 999px; background: rgba(18,70,171,.1); color: #1246ab; font-size: 18px">{i}/{n}{arrow}</span>'

def gfoot(dark=False):
    c = '#fff' if dark else '#0b1630'
    return f'''  <div style="position: absolute; left: 64px; right: 64px; bottom: 60px; display: flex; align-items: center; justify-content: space-between; color: {c}">
    <span style="display: inline-flex; align-items: center; height: 60px; padding: 0 22px; border-radius: 999px; background: #fff"><img src="{u('logo')}" alt="Universal Driveshafts" style="height: 32px; display: block"></span>
    <span class="mono" style="font-size: 17px; opacity: .8">universaldriveshafts.com/guides</span>
  </div>'''

def guide_slide(fname, title, i, n, body, cls='bg-i', dark=False):
    post(fname, title, cls, body + '\n' + swipe(i, n) + '\n' + gfoot(dark), foot=False)

def cover(fname, title, photo, kick, h1, sub, n, pos='center'):
    guide_slide(fname, title, 1, n, f'''  <img class="photo" src="{u(photo)}" alt="" style="object-position: {pos}">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.35) 0%, rgba(7,20,49,.2) 35%, rgba(7,20,49,.96) 72%)"></div>
  <div style="position: absolute; left: 64px; top: 92px"><span class="kick">{kick}</span></div>
  <div style="position: absolute; left: 64px; top: 640px; width: 952px; display: flex; flex-direction: column; gap: 24px; color: #fff">
    <h1 class="h" style="font-size: 112px">{h1}</h1>
    <p class="p" style="width: 860px; color: rgba(255,255,255,.9)">{sub}</p>
    <span style="display: inline-flex; align-items: center; gap: 14px; align-self: flex-start; height: 64px; padding: 0 28px; border-radius: 999px; background: #fff; color: #1246ab; font-weight: 700; font-size: 24px">Swipe to read <span style="font-size: 30px">→</span></span>
  </div>''', 'bg-d', True)

def row(series, cap, width, note, hot=False):
    bg = '#1246ab' if hot else '#fff'; c = '#fff' if hot else '#0b1630'; m = 'rgba(255,255,255,.75)' if hot else '#5a6782'
    return f'''<div class="card" style="display: grid; grid-template-columns: 200px 190px 190px 1fr; align-items: center; padding: 22px 28px; background: {bg}; color: {c}; box-shadow: none; border: 2px solid #e3eaf8">
      <span class="h" style="font-size: 52px; font-weight: 800">{series}</span>
      <span class="big-n" style="font-size: 44px; font-variant-numeric: tabular-nums">{cap}</span>
      <span class="big-n" style="font-size: 44px; font-variant-numeric: tabular-nums">{width}</span>
      <span style="font-size: 19px; line-height: 1.3; color: {m}">{note}</span>
    </div>'''

def thead():
    return '''<div class="mono" style="display: grid; grid-template-columns: 200px 190px 190px 1fr; padding: 0 28px; font-size: 15px; color: #5a6782"><span>Series</span><span>Cap dia (in)</span><span>Width (in)</span><span>Typical use</span></div>'''

def slide_head(kick, h1, size=84, color='#0b1630', w=952):
    return f'''  <div style="position: absolute; left: 64px; top: 92px; width: {w}px; display: flex; flex-direction: column; gap: 18px">
    <span class="kick" style="color: #1246ab">{kick}</span>
    <h2 class="h" style="font-size: {size}px; color: {color}">{h1}</h2>
  </div>'''

# ───────── C01 · U-joint series identification (6 slides) ─────────
N = 6
cover('C01-1.dc.html', 'Carousel 01 · U-joint series · 1', 'shaftclose', 'Driveline guide · u-joints',
      'Which u-joint<br>series do I have?', 'Two measurements tell you. Here is the chart we use at the counter.', N, '70% center')

guide_slide('C01-2.dc.html', 'Carousel 01 · 2', 2, N, slide_head('Step 1', 'Measure two<br>numbers. Only two.') + f'''
  <img class="prod" src="{u('ujoint')}" alt="A u-joint" style="position: absolute; left: 560px; top: 400px; width: 470px; height: 379px">
  <div style="position: absolute; left: 64px; top: 420px; width: 480px; display: flex; flex-direction: column; gap: 18px">
    <div class="card" style="padding: 26px 28px; display: flex; flex-direction: column; gap: 8px"><span class="mono" style="font-size: 15px; color: #e11d2a">A · Cap diameter</span><span style="font-size: 23px; line-height: 1.3; color: #0b1630">Caliper across one bearing cap.</span></div>
    <div class="card" style="padding: 26px 28px; display: flex; flex-direction: column; gap: 8px"><span class="mono" style="font-size: 15px; color: #e11d2a">B · Width across caps</span><span style="font-size: 23px; line-height: 1.3; color: #0b1630">Snap-ring groove to snap-ring groove, outside edge to outside edge.</span></div>
  </div>
  <div style="position: absolute; left: 64px; top: 860px; width: 952px; padding: 26px 30px; border-radius: 22px; background: #0b1630; color: #fff; display: flex; gap: 20px; align-items: center">
    <span class="ic ic-r" style="flex: none">{I['ruler']}</span><span style="font-size: 23px; line-height: 1.35">The series name (1310, 1350, 7290…) is just shorthand for that pair of dimensions.</span>
  </div>''')

guide_slide('C01-3.dc.html', 'Carousel 01 · 3', 3, N, slide_head('Light duty', 'Same cap.<br>Different width.') + f'''
  <div style="position: absolute; left: 64px; top: 380px; width: 952px; display: flex; flex-direction: column; gap: 12px">
    {thead()}
    {row('1210', '1.062', '2.625', 'Small cars, light SUVs')}
    {row('1310', '1.062', '3.219', 'Jeep, half-ton trucks, most light-duty shafts', True)}
    {row('1330', '1.062', '3.622', 'Half-ton and some ¾-ton Ford and Dodge pickups')}
  </div>
  <div style="position: absolute; left: 64px; top: 900px; width: 952px; padding: 24px 30px; border-radius: 22px; background: #fff; border: 2px solid #e3eaf8; color: #0b1630; font-size: 22px; line-height: 1.35"><b>1310 vs 1330:</b> identical caps. The width is the only tell — 3.219 in against 3.622 in. Always measure both.</div>''')

guide_slide('C01-4.dc.html', 'Carousel 01 · 4', 4, N, slide_head('Heavy duty', 'Bigger caps,<br>bigger torque.') + f'''
  <div style="position: absolute; left: 64px; top: 380px; width: 952px; display: flex; flex-direction: column; gap: 12px">
    {thead()}
    {row('1350', '1.188', '3.622', '¾-ton trucks, performance and off-road builds', True)}
    {row('1410', '1.188', '4.188', 'One-ton trucks, racing, high-torque street cars')}
    {row('1480', '1.375', '4.188', 'One-ton and medium duty, drag race')}
    {row('1550', '1.375', '4.875', 'Medium duty, Class 5–6, heavy off-road')}
  </div>''')

guide_slide('C01-5.dc.html', 'Carousel 01 · 5', 5, N, slide_head('Inside snap ring', 'Chrysler and GM<br>have their own.') + f'''
  <div style="position: absolute; left: 64px; top: 380px; width: 952px; display: flex; flex-direction: column; gap: 12px">
    {thead()}
    {row('7260', '1.078', '2.125', 'Chrysler small: Dart, Duster, A-body, Jeep CJ rear')}
    {row('7290', '1.125', '2.625', 'Chrysler large: B/E-body, Dodge trucks, Ram, Dakota', True)}
    {row('3R / S44', '1.125', '2.556', 'GM Saginaw: Chevelle, Camaro, C10, S10, Blazer')}
  </div>
  <div style="position: absolute; left: 64px; top: 900px; width: 952px; padding: 24px 30px; border-radius: 22px; background: #fff; border: 2px solid #e3eaf8; color: #0b1630; font-size: 22px; line-height: 1.35"><b>3R and S44</b> are the same joint under two names: Spicer calls it 3R, Saginaw calls it S44. Parts listed as 3R/S44 fit either.</div>''')

guide_slide('C01-6.dc.html', 'Carousel 01 · 6', 6, N, f'''  <div class="mark mark-b" style="font-size: 420px; left: -10px; top: 760px">1310</div>
''' + slide_head('Still not sure?', 'Measure it.<br>Or send us a photo.', 96) + f'''
  <div style="position: absolute; left: 64px; top: 460px; width: 952px; display: flex; flex-direction: column; gap: 16px">
    {chip('check', 'Used joints read a few thousandths under')}
    {chip('check', 'New joints read a few thousandths over')}
    {chip('check', 'Mixed series? It is a conversion joint — strength follows the smaller one')}
  </div>
  <div style="position: absolute; left: 64px; top: 780px; display: flex; flex-direction: column; gap: 18px">
    {cta('Shop u-joints by series')}
    <span style="font-size: 20px; color: #5a6782">Every u-joint on the new site is filed under its series.</span>
  </div>''')

# ───────── C02 · How to measure a driveshaft (5 slides) ─────────
N = 5
cover('C02-1.dc.html', 'Carousel 02 · Measure · 1', 'under', 'Driveline guide · measuring',
      'How to measure<br>a driveshaft', 'The same three letters our online builder asks for. Get these right and the shaft bolts up.', N)

def ref(letter, what, frm, to, hot=False):
    bg = '#1246ab' if hot else '#fff'; c = '#fff' if hot else '#0b1630'; m = 'rgba(255,255,255,.8)' if hot else '#5a6782'
    return f'''<div class="card" style="display: grid; grid-template-columns: 110px 1fr; gap: 22px; align-items: center; padding: 24px 28px; background: {bg}; color: {c}; box-shadow: none; border: 2px solid #e3eaf8">
      <span class="big-n" style="font-size: 92px">{letter}</span>
      <span style="display: flex; flex-direction: column; gap: 6px"><span style="font-size: 26px; font-weight: 700">{what}</span><span style="font-size: 19px; line-height: 1.3; color: {m}">{frm} → {to}</span></span>
    </div>'''

guide_slide('C02-2.dc.html', 'Carousel 02 · 2', 2, N, slide_head('One-piece shaft', 'Three letters<br>describe it.') + f'''
  <div style="position: absolute; left: 64px; top: 380px; width: 952px; display: flex; flex-direction: column; gap: 14px">
    {ref('C', 'Shaft length', 'Joint center or flange face', 'joint center or flange face at the other end', True)}
    {ref('W', 'Tube length, weld to weld', 'Front weld seam', 'rear weld seam')}
    {ref('T', 'Tube outside diameter', 'Caliper across the tube', 'away from the weld and any balance weights')}
  </div>''')

guide_slide('C02-3.dc.html', 'Carousel 02 · 3', 3, N, slide_head('The two references', 'Where does<br>the tape start?') + f'''
  <div style="position: absolute; left: 64px; top: 400px; width: 952px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px">
    <div class="card" style="padding: 30px; display: flex; flex-direction: column; gap: 16px; min-height: 420px"><img class="prod" src="{u('ujoint')}" alt="" style="width: 220px; height: 177px; align-self: center"><span style="font-size: 26px; font-weight: 700; color: #0b1630">End has a u-joint?</span><span style="font-size: 21px; line-height: 1.35; color: #5a6782">Measure to the <b>center of the cross</b> — the centerline of the bearing caps.</span></div>
    <div class="card" style="padding: 30px; display: flex; flex-direction: column; gap: 16px; min-height: 420px"><img class="prod" src="{u('companion')}" alt="" style="width: 170px; height: 189px; align-self: center"><span style="font-size: 26px; font-weight: 700; color: #0b1630">End has a flange?</span><span style="font-size: 21px; line-height: 1.35; color: #5a6782">Measure to the <b>face of the flange</b>, the surface that bolts to the vehicle.</span></div>
  </div>''')

guide_slide('C02-4.dc.html', 'Carousel 02 · 4', 4, N, slide_head('Three rules', 'What the builder<br>needs from you.') + f'''
  <div style="position: absolute; left: 64px; top: 400px; width: 952px; display: flex; flex-direction: column; gap: 16px">
    <div class="card" style="padding: 26px 28px; display: flex; gap: 22px; align-items: center"><span class="big-n" style="font-size: 72px; color: #e11d2a; min-width: 120px">⅛ in</span><span style="font-size: 23px; line-height: 1.35; color: #0b1630">Accuracy you need. Slip travel is for the suspension, not for measurement error.</span></div>
    <div class="card" style="padding: 26px 28px; display: flex; gap: 22px; align-items: center"><span class="ic" style="flex: none">{I['ruler']}</span><span style="font-size: 23px; line-height: 1.35; color: #0b1630">Slip yoke shafts: measure with the yoke <b>fully collapsed</b> on the transmission. We set the running engagement when we cut the tube.</span></div>
    <div class="card" style="padding: 26px 28px; display: flex; gap: 22px; align-items: center"><span class="ic" style="flex: none">{I['truck']}</span><span style="font-size: 23px; line-height: 1.35; color: #0b1630">No old shaft? Measure the vehicle at ride height, output seal face to pinion joint center, plus spline count and yoke series.</span></div>
  </div>''')

guide_slide('C02-5.dc.html', 'Carousel 02 · 5', 5, N, f'''  <img src="{u('finder')}" alt="The new site on a laptop" style="position: absolute; left: 150px; top: 560px; width: 900px; height: 622px">
''' + slide_head('Then', 'Type the numbers.<br>See your price.', 96) + f'''
  <div style="position: absolute; left: 64px; top: 420px; display: flex; flex-direction: column; gap: 18px">
    {cta('Open the online builder')}
    <span style="font-size: 20px; color: #5a6782">Same letters, same drawing. Built and balanced in our Florida shop.</span>
  </div>''')

# ───────── C03 · Is it the driveshaft? (5 slides) ─────────
N = 5
cover('C03-1.dc.html', 'Carousel 03 · Vibration · 1', 'undertruck', 'Driveline guide · diagnosis',
      'Is it really<br>the driveshaft?', 'Three tests you can do in a parking lot before you buy a single part.', N)

def test(n, title, body, tail):
    return f'''  <div style="position: absolute; left: 64px; top: 400px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <div class="card" style="padding: 34px 36px; display: flex; flex-direction: column; gap: 14px"><span class="mono" style="font-size: 16px; color: #e11d2a">Test {n}</span><span class="h" style="font-size: 64px; color: #0b1630">{title}</span><span style="font-size: 24px; line-height: 1.4; color: #0b1630">{body}</span></div>
    <div style="padding: 26px 30px; border-radius: 22px; background: #0b1630; color: #fff; font-size: 22px; line-height: 1.4">{tail}</div>
  </div>'''

guide_slide('C03-2.dc.html', 'Carousel 03 · 2', 2, N, slide_head('Rule one', 'A driveshaft tracks<br>road speed, not RPM.') + test(1, 'Speed or RPM?', 'Find the speed where it is worst. Shift to a different gear at the same road speed.', '<b>Stays the same?</b> It is downstream of the transmission: driveshaft, axle or tires. <b>Changes with RPM?</b> Engine, converter or transmission.'))

guide_slide('C03-3.dc.html', 'Carousel 03 · 3', 3, N, slide_head('Rule two', 'Load changes<br>the answer.') + test(2, 'Coast in neutral', 'At the bad speed, shift to neutral and coast.', '<b>Unchanged?</b> Balance or runout. <b>Eases off?</b> U-joint angle — the torque load is gone. <b>Identical?</b> Look at the tires.'))

guide_slide('C03-4.dc.html', 'Carousel 03 · 4', 4, N, slide_head('Rule three', 'Buzz or shake?') + f'''
  <div style="position: absolute; left: 64px; top: 400px; width: 952px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px">
    <div class="card" style="padding: 32px; display: flex; flex-direction: column; gap: 14px; background: #1246ab; color: #fff"><span class="mono" style="font-size: 16px; color: #ffd36b">Driveshaft</span><span class="h" style="font-size: 72px">Buzz</span><span style="font-size: 22px; line-height: 1.4; color: rgba(255,255,255,.9)">Fast, felt in the floor, console and shifter. Often blurs the mirror. The shaft spins 3–4× faster than the wheels.</span></div>
    <div class="card" style="padding: 32px; display: flex; flex-direction: column; gap: 14px"><span class="mono" style="font-size: 16px; color: #e11d2a">Tires</span><span class="h" style="font-size: 72px; color: #0b1630">Shake</span><span style="font-size: 22px; line-height: 1.4; color: #5a6782">Slow, 55–65 mph, felt in the seat and the steering wheel.</span></div>
  </div>
  <div style="position: absolute; left: 64px; top: 920px; width: 952px; padding: 24px 30px; border-radius: 22px; background: #fff; border: 2px solid #e3eaf8; color: #0b1630; font-size: 22px; line-height: 1.35"><b>Clunk on gear changes + highway buzz</b> = a u-joint with play. Quiet at low speed, grows with speed.</div>''')

guide_slide('C03-5.dc.html', 'Carousel 03 · 5', 5, N, f'''  <img class="photo" src="{u('weld')}" alt="" style="object-position: center 40%">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.5), rgba(7,20,49,.95) 60%)"></div>
  <div style="position: absolute; left: 64px; top: 92px"><span class="kick">Confirmed it is the shaft?</span></div>
  <div style="position: absolute; left: 64px; top: 560px; width: 952px; display: flex; flex-direction: column; gap: 22px; color: #fff">
    <h2 class="h" style="font-size: 100px">We check runout,<br>phasing and joints.<br><span class="hl">Then we balance.</span></h2>
    <p class="p-s" style="width: 820px; color: rgba(255,255,255,.9)">Ship it or bring it in. If a tube is bent or a yoke is worn, we tell you before we spend time balancing a shaft that cannot be fixed that way.</p>
    {cta('Book a balance')}
  </div>''', 'bg-d', True)

# ───────── Technical single posts P13–P16 ─────────
post('P13-Angles.dc.html', 'Post 13 · Pinion angle', 'bg-i', f'''  <div class="mark mark-b" style="font-size: 520px; left: -20px; top: 840px">3°</div>
  <div style="position: absolute; left: 64px; top: 92px; width: 560px; display: flex; flex-direction: column; gap: 20px">
    <span class="kick" style="color: #1246ab">Driveline guide · angles</span>
    <h1 class="h" style="font-size: 104px; color: #0b1630">The sweet<br>spot is<br><span class="hl-b">1 to 3°.</span></h1>
    <p class="p-s" style="width: 500px; color: #5a6782">Too much angle hammers the needles. Zero angle never rotates them. Both kill a u-joint early.</p>
  </div>
  <div style="position: absolute; left: 640px; top: 100px; width: 376px; padding: 28px 30px; border-radius: 26px; background: #0a2462; display: flex; flex-direction: column; gap: 14px; color: #fff">
    <span class="mono" style="font-size: 15px; color: rgba(255,255,255,.7)">Max single-joint angle</span>
    <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid rgba(255,255,255,.2); padding-bottom: 10px"><span class="big-n" style="font-size: 60px">3.25°</span><span class="mono" style="font-size: 15px">@ 5,000 rpm</span></div>
    <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid rgba(255,255,255,.2); padding-bottom: 10px"><span class="big-n" style="font-size: 60px">5°</span><span class="mono" style="font-size: 15px">@ 3,500 rpm</span></div>
    <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid rgba(255,255,255,.2); padding-bottom: 10px"><span class="big-n" style="font-size: 60px">8.7°</span><span class="mono" style="font-size: 15px">@ 2,000 rpm</span></div>
  </div>
  <div style="position: absolute; left: 64px; top: 600px; width: 952px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px">
    <div class="card" style="padding: 26px 28px; display: flex; flex-direction: column; gap: 10px"><span class="mono" style="font-size: 15px; color: #e11d2a">Leaf springs</span><span style="font-size: 23px; line-height: 1.3; color: #0b1630">Point the pinion <b>down 1–3°</b> at rest. Axle wrap rotates it up under power.</span></div>
    <div class="card" style="padding: 26px 28px; display: flex; flex-direction: column; gap: 10px"><span class="mono" style="font-size: 15px; color: #e11d2a">Links or coils</span><span style="font-size: 23px; line-height: 1.3; color: #0b1630"><b>0.5–1° down.</b> Little wrap, so little correction.</span></div>
  </div>
  <div class="card" style="position: absolute; left: 64px; top: 860px; width: 952px; padding: 20px 26px; display: flex; align-items: center; gap: 16px; color: #0b1630; font-size: 21px"><span class="ic" style="width: 52px; height: 52px; border-radius: 14px; flex: none">{I['gauge']}</span>Measure at ride height, on a level floor, weight on the suspension. Never on a lift with the axle hanging.</div>''')

post('P14-Splines.dc.html', 'Post 14 · Slip yoke splines', 'bg-e', f'''  <div class="mark" style="font-size: 560px; left: -30px; top: 860px">16</div>
  <img class="prod" src="{u('slip')}" alt="Slip yoke" style="position: absolute; left: 700px; top: 120px; width: 300px; height: 530px; transform: rotate(18deg)">
  <div style="position: absolute; left: 64px; top: 92px; width: 640px; display: flex; flex-direction: column; gap: 20px">
    <span class="kick">Driveline guide · slip yokes</span>
    <h1 class="h" style="font-size: 104px">Count the<br>splines. <span class="hl">Then<br>caliper it.</span></h1>
    <p class="p-s" style="width: 520px; color: rgba(255,255,255,.9)">Common light-truck transmission outputs, so you order the right slip yoke the first time.</p>
  </div>
  <div style="position: absolute; left: 64px; top: 700px; width: 952px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px">
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 4px"><span class="big-n" style="font-size: 54px; color: #1246ab">1.250 × 16</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">TH350, 4L60, many Fords</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 4px"><span class="big-n" style="font-size: 54px; color: #1246ab">1.375 × 16</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">NP205, 4L80, Dodge</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 4px"><span class="big-n" style="font-size: 54px; color: #1246ab">1.500 × 16</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">Ford truck, NV4500</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 4px"><span class="big-n" style="font-size: 54px; color: #e11d2a">27 spline</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">TH350 / 700R4 · 1.125 dia</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 4px"><span class="big-n" style="font-size: 54px; color: #e11d2a">32 spline</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">4L80E, Ford · 1.375 dia</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 10px; background: #0b1630; color: #fff; justify-content: center"><span style="font-size: 19px; line-height: 1.35">Our slip yoke names list the spline <b>and</b> the centerline-to-end length.</span></div>
  </div>''')

post('P15-Greaseable.dc.html', 'Post 15 · Greaseable or sealed', 'bg-i', f'''  <div class="slash" style="left: 500px; top: -100px; width: 1100px; height: 1600px; background: #fff"></div>
  <img class="prod" src="{u('ujoint')}" alt="Sealed u-joint" style="position: absolute; left: 70px; top: 470px; width: 420px; height: 339px">
  <img class="prod" src="{u('pair')}" alt="Greaseable u-joint" style="position: absolute; left: 590px; top: 440px; width: 400px; height: 384px">
  <div style="position: absolute; left: 64px; top: 92px; width: 952px; display: flex; flex-direction: column; gap: 18px">
    <span class="kick" style="color: #1246ab">Driveline guide · u-joints</span>
    <h1 class="h" style="font-size: 108px; color: #0b1630">Greaseable<br>or sealed?</h1>
  </div>
  <div style="position: absolute; left: 64px; top: 840px; width: 952px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px">
    <div class="card" style="padding: 28px; display: flex; flex-direction: column; gap: 10px"><span class="h" style="font-size: 40px; color: #0b1630; font-weight: 700">Sealed</span><span style="font-size: 21px; line-height: 1.35; color: #5a6782">Lubed for life. No zerk, no maintenance, solid cross — the stronger section.</span></div>
    <div class="card" style="padding: 28px; display: flex; flex-direction: column; gap: 10px; background: #1246ab; color: #fff"><span class="h" style="font-size: 40px; font-weight: 700">Greaseable</span><span style="font-size: 21px; line-height: 1.35; color: rgba(255,255,255,.88)">Flush out water and grit — the off-road pick. Same series swaps straight in. Aim the zerk toward the shaft centerline.</span></div>
  </div>''')

post('P16-Conversion.dc.html', 'Post 16 · Conversion u-joints', 'bg-d', f'''  <img class="photo" src="{u('ujointclose')}" alt="" style="object-position: center">
  <div class="ov-b"></div>
  <div class="seal" style="left: 816px; top: 80px; width: 200px; height: 200px; transform: rotate(8deg)">{STAR.format(c='#e11d2a')}<span style="font-size: 28px">Counter<br>tip</span></div>
  <div style="position: absolute; left: 64px; top: 92px"><span class="kick">Driveline guide · u-joints</span></div>
  <div style="position: absolute; left: 64px; top: 560px; width: 952px; display: flex; flex-direction: column; gap: 22px">
    <h1 class="h" style="font-size: 104px">1350 on one end.<br>1310 on the other.<br><span class="hl">No problem.</span></h1>
    <p class="p-s" style="width: 820px; color: rgba(255,255,255,.9)">A conversion u-joint has two caps of one series and two of another, so a transmission yoke of one series runs against an axle yoke of another without changing either.</p>
    <div style="display: flex; gap: 14px; flex-wrap: wrap">{chip('check', 'Strength follows the smaller series', 'chip chip-d')}{chip('check', '1350↔1310 · 1310↔3R/S44 in stock', 'chip chip-d')}</div>
  </div>''')

print('ok gen3')
