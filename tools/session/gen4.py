#!/usr/bin/env python3
# Five art-directed concept posts (N01–N05). Reuses gen2 helpers; photo keys n_* are added to gen2.B after upload.
import importlib.util, os
HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('gen2', os.path.join(HERE, 'gen2.py'))
g = importlib.util.module_from_spec(spec); spec.loader.exec_module(g)
u, I, cta, chip, post, STAR = g.u, g.I, g.cta, g.chip, g.post, g.STAR

# N01 · Ships today — editorial white, photo of hands labelling a box, giant "2 PM"
post('N01-ShipsToday.dc.html', 'Post · Order by 2 pm, ships today', 'bg-w', f'''  <img src="{u('n_label')}" alt="Hands placing a shipping label on a box" style="position: absolute; left: 0; top: 0; width: 1080px; height: 640px; object-fit: cover; object-position: center 40%">
  <div style="position: absolute; left: 0; top: 440px; width: 1080px; height: 200px; background: linear-gradient(180deg, rgba(255,255,255,0), #fff)"></div>
  <div style="position: absolute; left: 64px; top: 72px; display: inline-flex; align-items: center; gap: 12px; height: 54px; padding: 0 22px; border-radius: 999px; background: #0b1630; color: #fff; font-family: 'IBM Plex Mono', monospace; font-weight: 600; font-size: 18px; letter-spacing: .14em; text-transform: uppercase"><span class="dot"></span>In stock · ships same day</div>
  <div style="position: absolute; left: 64px; top: 520px; width: 952px; display: flex; flex-direction: column; gap: 10px; color: #0b1630">
    <div style="display: flex; align-items: flex-end; gap: 22px"><span class="big-n" style="font-size: 250px; line-height: .8; letter-spacing: -.02em">2<span style="font-size: 125px">PM</span></span><span class="h" style="font-size: 54px; line-height: 1; padding-bottom: 14px; color: #5a6782">Eastern.<br>That's the cutoff.</span></div>
    <h1 class="h" style="font-size: 96px; margin-top: 34px">Order before it.<br><span class="hl-r">It ships today.</span></h1>
  </div>
  <div style="position: absolute; left: 64px; top: 1030px; width: 952px; display: flex; gap: 14px; flex-wrap: wrap">
    {chip('truck', 'Tracking by e-mail and text')}
    {chip('check', 'Free U.S. shipping on parts over $1,000')}
  </div>
  <div class="foot" style="bottom: 52px">
    <img src="{u('logo')}" alt="Universal Driveshafts">
    <div class="f-r"><span>{I['phone']}(813) 524-6491</span><span>{I['globe']}universaldriveshafts.com</span></div>
  </div>''', foot=False)

# N02 · Anatomy — vertical exploded view with real cut-outs and numbered callouts
def callout(n, title, body, top, hot=False):
    bg = '#1246ab' if hot else '#fff'; c = '#fff' if hot else '#0b1630'; m = 'rgba(255,255,255,.8)' if hot else '#5a6782'
    return f'''  <div style="position: absolute; left: 540px; top: {top}px; width: 476px; display: flex; gap: 16px; align-items: flex-start">
    <span style="flex: none; width: 60px; height: 3px; margin-top: 30px; background: #1246ab"></span>
    <div class="card" style="flex: 1; padding: 18px 22px; display: flex; flex-direction: column; gap: 4px; background: {bg}; color: {c}; box-shadow: none; border: 2px solid #e3eaf8"><span class="mono" style="font-size: 14px; color: {'#ffd36b' if hot else '#e11d2a'}">{n:02d}</span><span class="h" style="font-size: 36px; font-weight: 700">{title}</span><span style="font-size: 18px; line-height: 1.3; color: {m}">{body}</span></div>
  </div>'''

post('N02-Anatomy.dc.html', 'Post · Anatomy of a driveshaft', 'bg-w', f'''  <div class="mark mark-b" style="font-size: 300px; left: -10px; top: 1000px">ANATOMY</div>
  <div style="position: absolute; left: 64px; top: 72px; width: 952px; display: flex; flex-direction: column; gap: 14px">
    <span class="kick" style="color: #1246ab">Anatomy of a driveshaft</span>
    <h1 class="h" style="font-size: 72px; color: #0b1630">Five parts.<br>One straight line.</h1>
  </div>
  <!-- the shaft, top to bottom -->
  <div style="position: absolute; left: 90px; top: 300px; width: 420px; height: 900px; display: flex; flex-direction: column; align-items: center; justify-content: space-between">
    <img class="prod" src="{u('slip')}" alt="Slip yoke" style="width: 130px; height: 230px; transform: rotate(180deg)">
    <img class="prod" src="{u('ujoint')}" alt="U-joint" style="width: 170px; height: 149px; margin: -14px 0">
    <img class="prod" src="{u('weldyoke')}" alt="Weld yoke" style="width: 145px; height: 157px">
    <div style="width: 100px; height: 170px; margin: -10px 0; border-radius: 10px; background: linear-gradient(90deg, #6b7280 0%, #d1d5db 22%, #f3f4f6 38%, #9ca3af 70%, #4b5563 100%); box-shadow: 0 30px 30px -18px rgba(2,10,35,.45)"></div>
    <img class="prod" src="{u('weldyoke')}" alt="Weld yoke" style="width: 145px; height: 157px; transform: rotate(180deg)">
    <img class="prod" src="{u('end')}" alt="End yoke" style="width: 180px; height: 173px; margin-top: -10px">
  </div>
{callout(1, 'Slip yoke', 'Slides on the transmission output spline. Lets the shaft change length as the suspension moves.', 300)}
{callout(2, 'U-joint', 'Sized by series: cap diameter + width across the caps. Two per shaft, in phase.', 475, True)}
{callout(3, 'Weld yoke', 'The half of the joint that lives on the tube. Welded, then straightened and balanced.', 650)}
{callout(4, 'Tube', 'DOM steel or aluminum, cut to your C-length. Diameter sets critical speed.', 825)}
{callout(5, 'End yoke', 'Nutted onto the pinion or output shaft. Fixed — the slip happens at the other end.', 1000)}
  <div style="position: absolute; left: 64px; right: 64px; bottom: 60px; display: flex; align-items: center; justify-content: space-between; color: #0b1630">
    <span style="display: inline-flex; align-items: center; height: 60px; padding: 0 22px; border-radius: 999px; background: #fff; border: 2px solid #e3eaf8"><img src="{u('logo')}" alt="Universal Driveshafts" style="height: 32px; display: block"></span>
    <span class="mono" style="font-size: 17px; color: #5a6782">universaldriveshafts.com</span>
  </div>''', foot=False)

# N03 · Overland — cinematic sunset, custom builds
post('N03-Overland.dc.html', 'Post · Built for the road that isn’t one', 'bg-d', f'''  <img class="photo" src="{u('n_camper')}" alt="A pickup with an overland camper on red rock" style="object-position: 56% center">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.8) 0%, rgba(7,20,49,.35) 32%, rgba(7,20,49,0) 48%, rgba(7,20,49,.2) 70%, rgba(7,20,49,.92) 100%)"></div>
  <div style="position: absolute; right: 64px; top: 72px; height: 60px; padding: 0 22px; border-radius: 999px; background: #fff; display: flex; align-items: center"><img src="{u('logo')}" alt="Universal Driveshafts" style="height: 32px; display: block"></div>
  <div style="position: absolute; left: 64px; top: 84px; width: 952px; display: flex; flex-direction: column; gap: 22px; color: #fff">
    <span class="kick">Custom driveshafts · off-road</span>
    <h1 class="h" style="font-size: 116px">Built for the road<br>that <span class="hl">isn't one.</span></h1>
  </div>
  <div style="position: absolute; left: 64px; top: 1010px; width: 952px; display: flex; flex-direction: column; gap: 18px; color: #fff">
    <p class="p-s" style="width: 700px; color: rgba(255,255,255,.92)">Measured to your lift. Greaseable joints if you want them. Balanced before it leaves Florida.</p>
    <div style="display: flex; align-items: center; gap: 18px">{cta('Price yours online')}<span style="font-size: 20px; color: rgba(255,255,255,.8)">Six measurements. Instant quote.</span></div>
  </div>''', foot=False)

# N04 · Before / after — worn joint vs new, diagonal split
post('N04-BeforeAfter.dc.html', 'Post · Don’t wait for the clunk', 'bg-w', f'''  <div style="position: absolute; left: 0; top: 0; width: 1080px; height: 1350px; overflow: hidden; clip-path: polygon(0 0, 100% 0, 100% 44%, 0 64%)">
    <img src="{u('n_rust')}" alt="A worn, rusted u-joint under a truck" style="position: absolute; left: 0; top: 0; width: 1080px; height: 870px; object-fit: cover; object-position: 60% 45%; filter: saturate(.8)">
    <div style="position: absolute; inset: 0; background: linear-gradient(90deg, rgba(7,20,49,.7), rgba(7,20,49,.1))"></div>
  </div>
  <div style="position: absolute; left: -40px; top: 580px; width: 1200px; height: 18px; background: #e11d2a; transform: rotate(-10.3deg); transform-origin: left center"></div>
  <span class="mono" style="position: absolute; left: 64px; top: 72px; height: 48px; padding: 0 20px; display: inline-flex; align-items: center; border-radius: 999px; background: rgba(255,255,255,.14); border: 1.5px solid rgba(255,255,255,.4); color: #fff; font-size: 17px">Worn</span>
  <span class="mono" style="position: absolute; right: 64px; top: 760px; height: 48px; padding: 0 20px; display: inline-flex; align-items: center; border-radius: 999px; background: #1246ab; color: #fff; font-size: 17px">New · same series</span>
  <img class="prod" src="{u('pair')}" alt="A new u-joint" style="position: absolute; left: 600px; top: 820px; width: 400px; height: 384px">
  <div style="position: absolute; left: 64px; top: 150px; width: 640px; color: #fff"><h1 class="h" style="font-size: 104px">Rust dust<br>is the warning.</h1></div>
  <div style="position: absolute; left: 64px; top: 880px; width: 520px; display: flex; flex-direction: column; gap: 20px; color: #0b1630">
    <h2 class="h" style="font-size: 84px">The clunk is<br><span class="hl-r">the deadline.</span></h2>
    <p class="p-s" style="color: #5a6782">A joint with play is quiet at low speed and loud at 70. Swap it this week, not after it lets go.</p>
    {cta('Find yours by series', extra=' style="height: 72px; font-size: 25px"')}
  </div>''', foot=False)

# N05 · Fleet — aerial depot, cost of downtime
post('N05-Fleet.dc.html', 'Post · Downtime costs more than parts', 'bg-d', f'''  <img class="photo" src="{u('n_fleet')}" alt="Aerial view of a truck depot" style="object-position: center 30%">
  <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(7,20,49,.15) 0%, rgba(7,20,49,.55) 40%, rgba(7,20,49,.97) 70%)"></div>
  <div style="position: absolute; left: 64px; top: 72px"><span class="kick">Fleets and shops</span></div>
  <div style="position: absolute; left: 64px; top: 520px; width: 952px; display: flex; flex-direction: column; gap: 22px; color: #fff">
    <h1 class="h" style="font-size: 112px">Downtime costs<br>more than <span class="hl">parts.</span></h1>
    <p class="p-s" style="width: 800px; color: rgba(255,255,255,.9)">Open a business account and the discount, the terms and the same-day cutoff are already built into every order.</p>
  </div>
  <div style="position: absolute; left: 64px; top: 880px; width: 952px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px">
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 10px"><span class="big-n" style="font-size: 68px; color: #1246ab">10%</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">Gold tier, applied automatically</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 10px"><span class="big-n" style="font-size: 68px; color: #1246ab">Net 20</span><span style="font-size: 18px; color: #5a6782; line-height: 1.3">Terms for approved accounts</span></div>
    <div class="card" style="padding: 24px 22px; display: flex; flex-direction: column; gap: 10px; background: #e11d2a; color: #fff"><span class="big-n" style="font-size: 68px">2 pm</span><span style="font-size: 18px; color: rgba(255,255,255,.88); line-height: 1.3">Same-day ship cutoff, ET</span></div>
  </div>''')
print('ok gen4')
