#!/usr/bin/env python3
# Sales-driven posts (M01–M04), carousel C04, and the Anatomy redesign. Reuses gen2/gen3 helpers.
import importlib.util, os
HERE = os.path.dirname(os.path.abspath(__file__))
def load(n):
    sp = importlib.util.spec_from_file_location(n, os.path.join(HERE, n + '.py')); m = importlib.util.module_from_spec(sp); sp.loader.exec_module(m); return m
g = load('gen2'); g3 = load('gen3')
u, I, cta, chip, post, STAR = g.u, g.I, g.cta, g.chip, g.post, g.STAR
guide_slide, cover, slide_head = g3.guide_slide, g3.cover, g3.slide_head

def tag(x, y, title, sub, side='r'):
    # label pill with a dot anchored on the photo; 'l' puts the text to the left of the dot
    dot = '<span style="width: 18px; height: 18px; border-radius: 50%; background: #e11d2a; box-shadow: 0 0 0 7px rgba(225,29,42,.28); flex: none"></span>'
    txt = f'<span style="display: flex; flex-direction: column; gap: 2px; padding: 10px 18px; border-radius: 16px; background: rgba(255,255,255,.96); color: #0b1630; box-shadow: 0 14px 30px -14px rgba(2,10,35,.6)"><span class="h" style="font-size: 28px; font-weight: 700">{title}</span><span style="font-size: 15px; color: #5a6782; line-height: 1.25">{sub}</span></span>'
    line = '<span style="width: 44px; height: 3px; background: #e11d2a; flex: none"></span>'
    inner = dot + line + txt if side == 'r' else txt + line + dot
    pos = f'left: {x}px' if side == 'r' else f'right: {1080 - x}px'
    return f'<div style="position: absolute; {pos}; top: {y}px; transform: translateY(-50%); display: flex; align-items: center">{inner}</div>'

# ── Anatomy redesign: real disassembled kit on navy, tagged parts ──
post('N02-Anatomy.dc.html', 'Post · Anatomy of a driveshaft', 'bg-d', f'''  <div class="slash-o" style="left: 820px; top: -200px; width: 70px; height: 1800px"></div>
  <div style="position: absolute; left: 64px; top: 72px; width: 952px; display: flex; flex-direction: column; gap: 14px">
    <span class="kick">Know your driveline</span>
    <h1 class="h" style="font-size: 92px">What's inside<br>a driveshaft.</h1>
  </div>
  <img src="{u('n_kit')}" alt="A disassembled driveshaft kit: tube with welded yoke, u-joint, weld yoke and slip yoke" style="position: absolute; left: 20px; top: 330px; width: 1040px; height: 859px; object-fit: contain; filter: drop-shadow(0 40px 40px rgba(0,5,20,.6))">
{tag(392, 400, 'U-joint', 'Sized by series: cap + width', 'r')}
{tag(560, 700, 'Tube', 'DOM steel, cut to your length', 'l')}
{tag(905, 712, 'Weld yoke', 'Lives on the tube end', 'l')}
{tag(880, 1040, 'Slip yoke', 'Rides the transmission spline', 'l')}
{tag(100, 965, 'Tube yoke', 'Welded, straightened, balanced', 'r')}
  <div style="position: absolute; left: 64px; right: 64px; bottom: 60px; display: flex; align-items: center; justify-content: space-between">
    <span style="display: inline-flex; align-items: center; height: 60px; padding: 0 22px; border-radius: 999px; background: #fff"><img src="{u('logo')}" alt="Universal Driveshafts" style="height: 32px; display: block"></span>
    <span class="mono" style="font-size: 17px; color: rgba(255,255,255,.8)">Every part sold separately · universaldriveshafts.com</span>
  </div>''', foot=False)

# ── M01 · Cross-reference: bring any part number ──
post('M01-Xref.dc.html', 'Post · Bring any part number', 'bg-e', f'''  <div class="mark" style="font-size: 520px; left: -30px; top: 900px">5-153X</div>
  <div class="slash" style="left: 700px; top: -200px; width: 180px; height: 1800px; opacity: .95"></div>
  <div style="position: absolute; left: 64px; top: 92px; width: 760px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">Cross-reference search</span>
    <h1 class="h" style="font-size: 112px">Bring any<br>part number.<br><span class="hl">We'll match it.</span></h1>
    <p class="p-s" style="width: 600px; color: rgba(255,255,255,.92)">Type the number off the box you already run. The new site finds the UDS part that fits it.</p>
  </div>
  <div style="position: absolute; left: 64px; top: 640px; width: 952px; display: flex; flex-direction: column; gap: 14px">
    <div style="height: 104px; border-radius: 999px; background: #fff; display: flex; align-items: center; gap: 20px; padding: 0 16px 0 36px; color: #0b1630; box-shadow: 0 30px 50px -26px rgba(2,10,35,.7)">
      <span style="color: #1246ab; display: grid">{I['search'].replace('<svg', '<svg style="width:36px;height:36px"')}</span>
      <span style="flex: 1; font-size: 34px; font-weight: 600; font-family: 'IBM Plex Mono', monospace">5-153X<span style="display: inline-block; width: 3px; height: 36px; margin-left: 6px; vertical-align: middle; background: #1246ab"></span></span>
      <span style="display: grid; place-items: center; width: 76px; height: 76px; border-radius: 50%; background: #e11d2a; color: #fff">{I['arrow'].replace('<svg', '<svg style="width:32px;height:32px"')}</span>
    </div>
    <div class="card" style="padding: 22px 28px; display: flex; align-items: center; gap: 22px">
      <img class="prod" src="{u('ujoint')}" alt="" style="width: 110px; height: 89px">
      <span style="display: flex; flex-direction: column; gap: 4px; flex: 1"><span class="mono" style="font-size: 14px; color: #e11d2a">Match · 1310 series</span><span style="font-size: 26px; font-weight: 700; color: #0b1630">Universal joint, 1310, greaseable</span><span style="font-size: 18px; color: #5a6782">1.062 in caps · 3.219 in across · in stock</span></span>
      <span style="display: inline-flex; align-items: center; height: 56px; padding: 0 24px; border-radius: 999px; background: #1246ab; color: #fff; font-weight: 700; font-size: 20px">Add to cart</span>
    </div>
  </div>
  <div style="position: absolute; left: 64px; top: 1000px; display: flex; gap: 14px">{chip('check', 'Spicer, Neapco, OE numbers')}{chip('check', 'Series and dimensions shown')}</div>''')

# ── M02 · Finder: your truck shouldn't wait on a part ──
post('M02-TruckDown.dc.html', 'Post · Your truck shouldn’t wait on a part', 'bg-d', f'''  <img class="photo" src="{u('n_truck')}" alt="A lifted black pickup" style="object-position: 60% center">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.1) 0%, rgba(7,20,49,.15) 40%, rgba(7,20,49,.95) 72%)"></div>
  <div style="position: absolute; left: 64px; top: 72px"><span class="kick">Complete driveshafts · by vehicle</span></div>
  <div style="position: absolute; left: 64px; top: 640px; width: 952px; display: flex; flex-direction: column; gap: 20px; color: #fff">
    <h1 class="h" style="font-size: 108px">Your truck<br>shouldn't wait<br><span class="hl-r">on a part.</span></h1>
    <p class="p-s" style="width: 760px; color: rgba(255,255,255,.92)">Year, make, model. The finder shows the complete shaft that fits, with price and stock, in three taps.</p>
    <div style="display: flex; align-items: center; gap: 18px; margin-top: 4px">{cta('Find my driveshaft')}<span style="display: flex; align-items: baseline; gap: 10px"><span class="big-n" style="font-size: 54px">4,433</span><span class="mono" style="font-size: 15px; color: rgba(255,255,255,.8)">shafts listed</span></span></div>
  </div>''')

# ── M03 · Builder: see the price before you commit ──
post('M03-Builder.dc.html', 'Post · See the price before you commit', 'bg-w', f'''  <div class="slash-b" style="left: -200px; top: 760px; width: 1600px; height: 900px; transform: skewY(-6deg)"></div>
  <div style="position: absolute; left: 64px; top: 72px; width: 952px; display: flex; flex-direction: column; gap: 18px">
    <span class="kick" style="color: #1246ab">Custom driveshafts · online builder</span>
    <h1 class="h" style="font-size: 100px; color: #0b1630">See the price<br><span class="hl-b">before you commit.</span></h1>
    <p class="p-s" style="width: 700px; color: #5a6782">Pick duty, series and yokes. Enter your measurements. The exact assembly is priced on screen — no quote request, no waiting.</p>
  </div>
  <img src="{u('dev_builder')}" alt="The online builder on a laptop" style="position: absolute; left: 60px; top: 560px; width: 960px; height: 657px">
  <div style="position: absolute; left: 64px; top: 1120px; width: 952px; display: flex; justify-content: center; gap: 12px">
    <span class="chip"><i>1</i>Duty</span><span class="chip"><i>2</i>Series</span><span class="chip"><i>3</i>Yokes</span><span class="chip"><i style="background: #e11d2a">4</i>Measurements</span>
  </div>''', foot=False)

# ── M04 · Engagement: comment your part number ──
post('M04-Comment.dc.html', 'Post · Comment your part number', 'bg-d', f'''  <div style="position: absolute; inset: 0; background: linear-gradient(160deg, #e11d2a 0%, #b5121d 60%, #7d0c14 100%)"></div>
  <div class="mark" style="font-size: 440px; left: -20px; top: 940px; color: rgba(255,255,255,.08)">ASK US</div>
  <img class="prod" src="{u('pair')}" alt="Two u-joints" style="position: absolute; left: 600px; top: 80px; width: 430px; height: 413px; transform: rotate(-14deg)">
  <div style="position: absolute; left: 64px; top: 92px; width: 540px; display: flex; flex-direction: column; gap: 22px">
    <span class="kick">Free fitment check</span>
    <h1 class="h" style="font-size: 100px">Not sure<br>what fits?</h1>
  </div>
  <div style="position: absolute; left: 64px; top: 560px; width: 952px; display: flex; flex-direction: column; gap: 18px">
    <div class="card" style="padding: 30px 34px; display: flex; flex-direction: column; gap: 10px"><span class="mono" style="font-size: 15px; color: #e11d2a">How it works</span><span class="h" style="font-size: 56px; color: #0b1630">Comment your year, make and model — or the number on the old part.</span><span style="font-size: 22px; color: #5a6782; line-height: 1.35">A driveline tech replies with the series, the part and whether it is on the shelf. No DM, no form.</span></div>
    <div style="display: flex; gap: 14px; flex-wrap: wrap">{chip('chat', 'Reply within one business day', 'chip chip-d')}{chip('check', 'Measured, not guessed', 'chip chip-d')}</div>
  </div>''')

# ── C04 · 5 ways to kill a driveshaft (6 slides) ──
N = 6
cover('C04-1.dc.html', 'Carousel 04 · Kill a driveshaft · 1', 'n_balancer', 'Driveline guide · failures',
      '5 ways to kill<br>a driveshaft', 'All five are avoidable. All five walk into our shop every week.', N, 'center')

def kill(i, title, body, fix):
    return f'''  <div style="position: absolute; left: 64px; top: 400px; width: 952px; display: flex; flex-direction: column; gap: 18px">
    <div class="card" style="padding: 34px 36px; display: flex; flex-direction: column; gap: 12px"><span style="display: flex; align-items: center; gap: 16px"><span class="big-n" style="font-size: 84px; color: #e11d2a">{i}</span><span class="h" style="font-size: 60px; color: #0b1630">{title}</span></span><span style="font-size: 24px; line-height: 1.4; color: #0b1630">{body}</span></div>
    <div style="padding: 24px 30px; border-radius: 22px; background: #0b1630; color: #fff; font-size: 22px; line-height: 1.4; display: flex; gap: 16px; align-items: center"><span class="ic" style="flex: none; width: 52px; height: 52px; border-radius: 14px">{I['check']}</span><span><b>Fix:</b> {fix}</span></div>
  </div>'''

guide_slide('C04-2.dc.html', 'Carousel 04 · 2', 2, N, slide_head('Angles', 'Zero angle.<br>Or too much.') + kill(1, 'Zero or excess angle', 'At zero degrees the needles never rotate and brinell the cross. Past about 3° at highway speed they hammer. Both end the same way.', 'Set 1–3° of working angle at ride height, equal and opposite at both joints.'))
guide_slide('C04-3.dc.html', 'Carousel 04 · 3', 3, N, slide_head('Phasing', 'Yokes out<br>of phase.') + kill(2, 'Out of phase', 'If the ears at each end of the tube are not in line, the second joint cannot cancel the first. You get a vibration no balance weight can fix.', 'Look down the shaft from the end. Ears line up, or the shaft comes apart and goes back together right.'))
guide_slide('C04-4.dc.html', 'Carousel 04 · 4', 4, N, slide_head('Series', 'The joint that<br>almost fits.') + kill(3, 'Wrong series pair', '1310 and 1330 share a 1.062 in cap. A 1330 cross in a 1310 yoke runs loose on the width — until the caps walk out.', 'Measure cap and width. Mixed yokes take a conversion joint, not a near miss.'))
guide_slide('C04-5.dc.html', 'Carousel 04 · 5', 5, N, slide_head('Welding', 'Welded in<br>the driveway.') + kill(4, 'Welded without a fixture', 'A weld yoke welded by eye runs out. Even 0.010 in of runout is a shake at 70 mph, and the heat alone can pull a tube out of true.', 'Weld in a fixture, straighten to under 0.010 in, then balance at speed. Or let the builder price the finished shaft.'))
guide_slide('C04-6.dc.html', 'Carousel 04 · 6', 6, N, f'''  <img class="photo" src="{u('n_balancer')}" alt="" style="object-position: 30% center">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.5), rgba(7,20,49,.95) 58%)"></div>
  <div style="position: absolute; left: 64px; top: 92px"><span class="kick">Number five</span></div>
  <div style="position: absolute; left: 64px; top: 520px; width: 952px; display: flex; flex-direction: column; gap: 22px; color: #fff">
    <div style="display: flex; align-items: center; gap: 16px"><span class="big-n" style="font-size: 84px; color: #ff4b55">5</span><span class="h" style="font-size: 64px">Ignoring the clunk.</span></div>
    <p class="p" style="width: 860px; color: rgba(255,255,255,.92)">A joint with play is cheap on Monday and a tube through the floor on Friday. Rust dust around the caps or a clunk on gear changes is the whole warning you get.</p>
    {cta('Shop u-joints by series')}
  </div>''', 'bg-d', True)
print('ok gen5')
