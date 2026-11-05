import React from 'react';
import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame } from 'remotion';
import { FONT } from '../launch/theme';
import { W, H, T2, len, SEC } from './timeline';
import { OPEN, HOME, SEARCH, CHAT, VEH, BLD, DSG, CHK, ACC, END } from './cues';
import { E, P, kf, drift, useF, S, C, clamp, Kinetic, Impact, Callout, Cursor, CurKey, shake, Window, Shots, Crop, Phone, PriceUp, Laptop, Chroma, Grid } from './fx';
import { Shaft3D } from './Shaft3D';
import meta from '../../public/v3/shots/meta.json';

const URL = 'universaldriveshafts.com';
const sp = (f: number, at: number, cfg = { damping: 12, stiffness: 160, mass: 0.8 }) => spring({ frame: f - at, fps: 60, config: cfg });

// a status chip (canvas coords): pops with a spring, exits fast
const Chip: React.FC<{ f: number; at: number; out: number; x: number; y: number; text: string; tone?: 'ok' | 'blue' }> = ({ f, at, out, x, y, text, tone = 'ok' }) => {
  if (f < at || f > out + 12) return null; const p = sp(f, at, { damping: 10, stiffness: 200, mass: 0.7 }), o = 1 - P(f, out, out + 12, E.in);
  const col = tone === 'ok' ? ['#e7f5ec', '#1a7f4b', 'rgba(26,127,75,.35)'] : ['#eef3fc', C.blue, 'rgba(59,123,255,.4)'];
  return <div style={{ position: 'absolute', left: x, top: y, transform: `translateY(${(1 - p) * 24}px) scale(${0.7 + p * 0.3})`, transformOrigin: '0 50%', opacity: Math.min(1, p * 2) * o, display: 'flex', alignItems: 'center', gap: 12, padding: '12px 22px', borderRadius: 999, background: col[0], color: col[1], fontFamily: FONT.h, fontWeight: 700, fontSize: 22, boxShadow: `0 16px 40px rgba(0,0,0,.35), 0 0 30px ${col[2]}`, whiteSpace: 'nowrap', zIndex: 20 }}><span style={{ width: 26, height: 26, borderRadius: '50%', background: col[1], color: '#fff', display: 'grid', placeItems: 'center', fontSize: 16 }}>✓</span>{text}</div>;
};

// a section title card: scrim + kinetic words + impact, over whatever the scene already shows
const TitleCard: React.FC<{ f: number; lines: string[]; sub?: string; size?: number; out?: number }> = ({ f, lines, sub, size = 118, out = 56 }) => {
  if (f < 0 || f > out + 14) return null; const o = 1 - P(f, out, out + 12, E.in);
  return (
    <AbsoluteFill style={{ pointerEvents: 'none' }}>
      <AbsoluteFill style={{ background: 'radial-gradient(70% 70% at 50% 50%, rgba(7,18,38,.55), rgba(7,18,38,.9))', opacity: o }} />
      <Impact at={2} />
      <Kinetic lines={lines} at={4} out={out} size={size} sub={sub} />
    </AbsoluteFill>
  );
};
// the window pops in behind the title card: scale up + de-blur
const cardIn = (f: number, from = 40) => ({ opacity: P(f, from, from + 20, E.out), filter: `blur(${(1 - P(f, from, from + 24, E.out)) * 12}px)` });

// ================================================================= 1 · open: light line → laptop orbit → logo wipe → dive
export const Open: React.FC<{ base?: number }> = ({ base = 0 }) => {
  const f = useCurrentFrame() + base;
  const line = P(f, OPEN.line, OPEN.line + 36, E.out), glow = P(f, OPEN.glow, OPEN.glow + 44, E.out);
  const lap = P(f, OPEN.laptop, OPEN.laptop + 150, E.out), ry = -25 + 25 * lap, rx = 12 - 8 * lap, sweep = f < OPEN.sweep2 ? P(f, OPEN.laptop + 30, OPEN.laptop + 150, E.io) : P(f, OPEN.sweep2, OPEN.sweep2 + 70, E.io);
  const scroll = kf(f, [[OPEN.scrollStart, [0]], [OPEN.dive, [1400]], [OPEN.dive + 110, [120]]], E.io)[0];
  const dive = P(f, OPEN.dive, OPEN.dive + 110, E.in), scale = 1 + dive * 13;
  const logo = P(f, OPEN.logo, OPEN.logo + 70, E.io), logoOut = P(f, OPEN.scrollStart + 40, OPEN.scrollStart + 80, E.in);
  const chroma = f >= OPEN.flash ? Math.sin(Math.min(1, (f - OPEN.flash) / 42) * Math.PI) : 0;
  const content = (
    <AbsoluteFill>
      {/* the light line that becomes the screen glow */}
      {f < OPEN.laptop + 60 && (
        <>
          <div style={{ position: 'absolute', left: W / 2 - (line * 620), top: H / 2 + 40, width: line * 1240, height: 2 + glow * 740, marginTop: -(1 + glow * 370), background: `linear-gradient(90deg, rgba(120,170,255,0), rgba(220,235,255,${0.95 - glow * 0.6}) 20%, #fff 50%, rgba(220,235,255,${0.95 - glow * 0.6}) 80%, rgba(120,170,255,0))`, opacity: 1 - lap * 1.2, filter: `blur(${glow * 24}px)`, boxShadow: '0 0 60px rgba(120,170,255,.9)' }} />
          <div style={{ position: 'absolute', left: W / 2 - 400, top: H / 2 - 360, width: 800, height: 800, borderRadius: '50%', background: 'radial-gradient(circle, rgba(80,140,255,.55), rgba(0,0,0,0) 65%)', opacity: glow * (1 - lap), transform: `scale(${1 + glow * 1.2})` }} />
        </>
      )}
      <div style={{ position: 'absolute', inset: 0, opacity: Math.min(1, lap * 2.2), transformOrigin: `${W / 2}px ${H / 2 + 40}px`, transform: `scale(${scale})` }}>
        <Laptop ry={ry} rx={rx} sweep={sweep} glow={1 - dive} scale={0.86 + lap * 0.14}>
          <Img src={S('home-top')} style={{ position: 'absolute', left: 0, top: -scroll, width: 1440, display: 'block' }} />
          {/* the wordmark builds on screen with a masked wipe, then gives way to the page */}
          <div style={{ position: 'absolute', inset: 0, background: 'rgba(255,255,255,.96)', opacity: Math.min(1, logo * 3) * (1 - logoOut) }}>
            <Img src={staticFile('v3/logo.svg')} style={{ position: 'absolute', left: 220, top: 350, width: 1000, clipPath: `inset(0 ${(1 - logo) * 100}% 0 0)` }} />
            <div style={{ position: 'absolute', left: 220, top: 530, width: 1000 * logo, height: 4, background: C.red }} />
          </div>
        </Laptop>
      </div>
      {/* reflection of the screen glow on the desk plane */}
      <div style={{ position: 'absolute', left: W / 2 - 700, top: H / 2 + 300, width: 1400, height: 400, background: 'radial-gradient(ellipse at 50% 0%, rgba(80,140,255,.35), rgba(0,0,0,0) 60%)', opacity: lap * (1 - dive), pointerEvents: 'none' }} />
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: 64, textAlign: 'center', fontFamily: FONT.m, fontWeight: 600, fontSize: 22, letterSpacing: '.34em', textTransform: 'uppercase', color: C.ice, opacity: P(f, OPEN.title, OPEN.title + 30, E.out) * (1 - P(f, OPEN.dive, OPEN.dive + 40, E.in)) }}>
        {f < OPEN.sweep2 + 20 ? 'universaldriveshafts.com · now live' : '6,800+ parts · live prices · ships from Florida'}
      </div>
    </AbsoluteFill>
  );
  return <Chroma amount={chroma}>{content}</Chroma>;
};

// ================================================================= 2 · home: land scrolling, float out in 3D, callouts on nav + hero, go to search
export const Home: React.FC = () => {
  const f = useF();
  const cam = kf(f, [[-T2, [720, 450, 1.46, 0, 0]], [HOME.float, [720, 450, 0.88, -16, 6]], [HOME.nav, [720, 150, 1.25, -5, 2]], [HOME.hero, [600, 400, 1.08, 4, 1]], [HOME.toSearch, [1000, 120, 1.55, 0, 0]], [len('home') + T2, [1000, 120, 1.7, 0, 0]]], E.io);
  const scroll = kf(f, [[-T2, [120]], [40, [0]], [HOME.hero, [0]], [HOME.toSearch, [36]]], E.out)[0];
  const keys: CurKey[] = [{ f: 60, x: 1100, y: 520 }, { f: HOME.icons, x: 1060, y: 120 }, { f: HOME.vehicle, x: 700, y: 720 }, { f: HOME.toSearch, x: 980, y: 300 }, { f: HOME.click, x: 1026, y: 78, click: true }, { f: 600, x: 1026, y: 78 }];
  const sh = shake(keys, f);
  const flash = f < 30 ? 1 - P(f, -T2, 30, E.out) : 0;
  return (
    <AbsoluteFill>
      <Window url={URL} cam={[cam[0], cam[1] + drift(f, 3, 210), cam[2] * (1 + Math.max(0, f) * 0.00005), cam[3], cam[4]]} shake={sh}>
        <Img src={S('home-top')} style={{ position: 'absolute', left: 0, top: -scroll, width: 1440, display: 'block' }} />
        <Callout at={HOME.nav} out={HOME.hero - 10} x={400} y={64 - scroll} w={385} h={30} dx={60} dy={-120 + 90} text="New navigation" sub="catalog · driveshafts · services · about" z={cam[2]} />
        <Callout at={HOME.icons} out={HOME.hero - 6} x={960} y={60 - scroll} w={180} h={38} dx={-80} dy={90} text="Account · wishlist · cart" sub="always one click away" z={cam[2]} />
        <Callout at={HOME.vehicle} out={HOME.toSearch + 10} x={180} y={655 - scroll} w={1080} h={135} dx={260} dy={-50} text="Find your driveshaft by vehicle" sub="make · year · model" z={cam[2]} />
        <Callout at={HOME.hero} out={HOME.vehicle - 6} x={110} y={230 - scroll} w={640} h={300} dx={120} dy={-60} text="Live stock and live prices" sub="6,800+ parts on the shelf" z={cam[2]} />
        <Callout at={HOME.toSearch + 20} out={HOME.click + 30} x={1006} y={60 - scroll} w={40} h={38} dx={-60} dy={70} text="Search any part number" z={cam[2]} />
        <Cursor keys={keys} show={[50, 700]} />
      </Window>
      <Kinetic lines={['The new *universaldriveshafts.com']} at={HOME.float + 4} out={HOME.nav - 4} size={64} y={110} />
      <AbsoluteFill style={{ background: '#fff', opacity: flash * 0.9, pointerEvents: 'none' }} />
    </AbsoluteFill>
  );
};

// ================================================================= 3 · search: type 1310, real results, zoom to the first match
export const Search: React.FC = () => {
  const f = useF();
  const cam = kf(f, [[0, [720, 300, 1.15, 10, 4]], [60, [700, 60, 2.0, 0, 0]], [SEARCH.results, [700, 70, 2.0, 0, 0]], [SEARCH.zoom + 30, [720, 250, 1.35, 0, 0]], [SEARCH.hover, [720, 220, 1.5, -3, 1]], [len('search') + T2, [700, 230, 1.55, 0, 0]]], E.io);
  const steps: [number, string][] = [[-99, 'search-0'], ...SEARCH.typed.map((t, i) => [t, 'search-' + (i + 1)] as [number, string])];
  const keys: CurKey[] = [{ f: 100, x: 900, y: 120 }, { f: SEARCH.hover, x: 700, y: 204 }, { f: SEARCH.add, x: 1153, y: 204, click: true }, { f: 600, x: 1153, y: 204 }];
  return (
    <AbsoluteFill>
      <Window url={URL + '/search?q=1310'} cam={[cam[0], cam[1] + drift(f, 2, 200), cam[2], cam[3], cam[4]]} shake={shake(keys, f)} {...cardIn(f)}>
        <Shots steps={steps} />
        {/* caret in the search box while typing */}
        {f > 40 && f < SEARCH.results && <div style={{ position: 'absolute', left: 318 + Math.min(4, SEARCH.typed.filter((t) => f >= t).length) * 13.2, top: 36, width: 2, height: 24, background: C.blue, opacity: Math.floor(f / 18) % 2 ? 0 : 1 }} />}
        <Callout at={SEARCH.callout} out={SEARCH.add + 40} x={252} y={172} w={936} h={60} dx={160} dy={90} text="Every match across the catalog" sub="flange yokes · 70 results · live price" z={cam[2]} />
        <Callout at={SEARCH.add + 6} out={SEARCH.add + 110} x={1133} y={184} w={40} h={40} dx={-80} dy={70} text="One click to the cart" z={cam[2]} />
        <Cursor keys={keys} show={[90, 600]} />
      </Window>
      <TitleCard f={f} lines={['Search any *part number']} sub="type it · get every match" size={104} />
    </AbsoluteFill>
  );
};

// ================================================================= 4 · chatbot: open, type the question, the answer reveals line by line, cards pop one by one
export const Chat: React.FC = () => {
  const f = useF();
  const cam = kf(f, [[0, [900, 470, 0.95, 10, 4]], [CHAT.open, [1200, 600, 1.1, 2, 1]], [CHAT.zoom, [1218, 817, 2.6, 0, 0]], [CHAT.send - 20, [1250, 817, 2.6, 0, 0]], [CHAT.send + 30, [1220, 520, 1.45, -3, 1]], [CHAT.cards[0], [1230, 560, 1.5, 0, 0]], [len('chat') + T2, [1230, 600, 1.58, 2, 0]]], E.io);
  const steps: [number, string][] = [[-99, 'ai-0'], [CHAT.open + 4, 'ai-1']];
  for (let i = 0; i < CHAT.nType; i++) steps.push([CHAT.typeStart + i * CHAT.typeStep, 'ai-t' + String(i).padStart(2, '0')]);
  steps.push([CHAT.send + 4, 'ai-2'], [CHAT.dots, 'ai-3']);
  const keys: CurKey[] = [{ f: 20, x: 900, y: 600 }, { f: CHAT.open, x: 1324, y: 853, click: true }, { f: CHAT.zoom + 14, x: 1120, y: 817, click: true }, { f: CHAT.send, x: 1381, y: 816, click: true }, { f: CHAT.answer, x: 1300, y: 720 }, { f: 800, x: 1260, y: 760 }];
  const lines = [[413, 30], [443, 30], [473, 28]]; // answer bubble lines (screen css px)
  const cards = [[1037, 520, 190, 196], [1237, 520, 181, 196]];
  return (
    <AbsoluteFill>
      <Window url={URL + '/shop'} cam={[cam[0], cam[1] + drift(f, 2, 200), cam[2], cam[3], cam[4]]} shake={shake(keys, f)} {...cardIn(f)}>
        <Shots steps={steps} />
        {f >= CHAT.answer && (
          <>
            {/* the typing dots give way to the real answer: each line is revealed left→right, never cut */}
            <Crop src="ai-3" x={1437} y={405} w={160} h={100} style={{ opacity: 1 - P(f, CHAT.answer, CHAT.answer + 10, E.in) }} />
            {lines.map(([y, h], i) => { const a = CHAT.reveal + i * CHAT.lineDur, w = 330 * P(f, a, a + CHAT.lineDur, E.io); return <Crop key={i} src="ai-4" x={1037} y={y} w={w} h={h} />; })}
            {f >= CHAT.reveal && f < CHAT.reveal + 3 * CHAT.lineDur + 10 && <div style={{ position: 'absolute', left: 1037 + 330 * P(f, CHAT.reveal + Math.min(2, Math.floor((f - CHAT.reveal) / CHAT.lineDur)) * CHAT.lineDur, CHAT.reveal + (Math.min(2, Math.floor((f - CHAT.reveal) / CHAT.lineDur)) + 1) * CHAT.lineDur, E.io), top: 416 + Math.min(2, Math.floor((f - CHAT.reveal) / CHAT.lineDur)) * 30, width: 2, height: 22, background: C.blue }} />}
            {cards.map(([x, y, w, h], i) => { const p = sp(f, CHAT.cards[i], { damping: 9, stiffness: 170, mass: 0.9 }); return <Crop key={i} src="ai-4" x={x} y={y} w={w} h={h} style={{ opacity: Math.min(1, p * 2), transform: `translateY(${(1 - p) * 40}px) scale(${0.7 + p * 0.3})`, transformOrigin: '50% 100%', borderRadius: 12, boxShadow: `0 ${20 * p}px ${40 * p}px rgba(11,26,51,${0.25 * p})` }} />; })}
          </>
        )}
        <Callout at={CHAT.zoom + 30} out={CHAT.send - 30} x={1082} y={800} w={272} h={35} dx={-120} dy={-70} text="Ask in plain English" sub="caps · span · series · vehicle" z={cam[2]} />
        <Callout at={CHAT.callout} out={1e9} x={1037} y={413} w={320} h={90} dx={-150} dy={-40} text="1350-series · in stock" sub="ships today before 2pm ET" z={cam[2]} />
        <Cursor keys={keys} show={[10, 800]} />
      </Window>
      <TitleCard f={f} lines={['Ask the *UDS Assistant']} sub="real stock · real prices · fitment" size={104} />
    </AbsoluteFill>
  );
};

// ================================================================= 5 · complete driveshafts · find by vehicle (real selectors, real results)
const DD: React.FC<{ at: number; pick: number; close: number; x: number; y: number; w: number; items: string[]; sel: number; f: number }> = ({ at, pick, close, x, y, w, items, sel, f }) => {
  if (f < at || f > close) return null; const o = sp(f, at, { damping: 13, stiffness: 220, mass: 0.6 }), hi = f >= pick - 8;
  return (
    <div style={{ position: 'absolute', left: x, top: y, width: w, background: '#fff', border: '1px solid #cfd6e3', borderRadius: 10, boxShadow: '0 24px 60px rgba(11,26,51,.28)', transform: `scaleY(${0.6 + 0.4 * o})`, transformOrigin: '50% 0', opacity: Math.min(1, o * 2), overflow: 'hidden', zIndex: 5 }}>
      {items.map((it, i) => <div key={it} style={{ height: 34, lineHeight: '34px', padding: '0 16px', fontFamily: FONT.b, fontSize: 17, color: i === sel && hi ? '#fff' : '#141b28', background: i === sel && hi ? C.blue : i === sel ? '#eef3fc' : '#fff' }}>{it}</div>)}
    </div>
  );
};
export const Vehicle: React.FC = () => {
  const f = useF(); const M: any = meta; const rows: any[] = M['veh-3'].rows;
  const cam = kf(f, [[VEH.page - 10, [720, 450, 0.95, -10, 4]], [VEH.page + 50, [520, 600, 1.6, 0, 0]], [VEH.make.set, [520, 600, 1.6, 0, 0]], [VEH.year.open - 20, [600, 600, 1.7, 0, 0]], [VEH.model.open - 20, [560, 640, 1.65, 0, 0]], [VEH.results, [720, 420, 1.12, 0, 0]], [VEH.results + 40, [720, 520, 1.1, 0, 0]], [VEH.snap, [560, 420, 1.45, 2, 0]], [VEH.hover, [720, 480, 1.3, -3, 1]], [VEH.product, [720, 420, 1.05, 0, 0]], [VEH.price, [1000, 440, 1.5, 0, 0]], [VEH.ship + 60, [980, 430, 1.55, -2, 1]], [1200, [980, 430, 1.6, 0, 0]]], E.snap);
  const steps: [number, string][] = [[-99, 'veh-0'], [VEH.make.set, 'veh-1'], [VEH.year.set, 'veh-2'], [VEH.results, 'veh-3'], [VEH.product, 'veh-4']];
  const makes = ['Chrysler', 'Custom / Misc.', 'Dodge', 'Ford', 'Genesis', 'Geo', 'GMC', 'Honda'], years = ['2019', '2018', '2017', '2016', '2015', '2014', '2013', '2012'], models = ['Expedition', 'Explorer', 'F53 Motor Home', 'F150', 'F250 Super Duty', 'F350 Super Duty', 'F450 Super Duty', 'Flex'];
  const keys: CurKey[] = [{ f: VEH.page + 20, x: 700, y: 700 }, { f: VEH.make.open, x: 288, y: 528, click: true }, { f: VEH.make.pick, x: 288, y: 551 + 3 * 34 + 17, click: true }, { f: VEH.year.open, x: 561, y: 528, click: true }, { f: VEH.year.pick, x: 561, y: 551 + 4 * 34 + 17, click: true }, { f: VEH.model.open, x: 354, y: 607, click: true }, { f: VEH.model.pick, x: 354, y: 630 + 3 * 34 + 17, click: true }, { f: VEH.results + 30, x: 900, y: 600 }, { f: VEH.hover, x: 700, y: 268 }, { f: VEH.open, x: 342, y: 268, click: true }, { f: VEH.product + 30, x: 800, y: 600 }, { f: VEH.ship, x: 1100, y: 560 }, { f: 1200, x: 1120, y: 580 }];
  const inResults = f >= VEH.results && f < VEH.product;
  return (
    <AbsoluteFill>
      <Shaft3D variant="a" x={210} y={90} opacity={P(f, 0, 20, E.out) * (1 - P(f, VEH.cardOut, VEH.cardOut + 16, E.in))} phase={20} tilt={-6} />
      <Window url={URL + '/shop/complete-driveshafts/by-vehicle'} cam={[cam[0], cam[1] + drift(f, 2, 200), cam[2], cam[3], cam[4]]} shake={shake(keys, f)} {...cardIn(f, VEH.page - 20)}>
        <Shots steps={steps} />
        <DD f={f} at={VEH.make.open + 4} pick={VEH.make.pick} close={VEH.make.pick + 6} x={156} y={551} w={264} items={makes} sel={3} />
        <DD f={f} at={VEH.year.open + 4} pick={VEH.year.pick} close={VEH.year.pick + 6} x={429} y={551} w={264} items={years} sel={4} />
        <DD f={f} at={VEH.model.open + 4} pick={VEH.model.pick} close={VEH.model.pick + 6} x={156} y={630} w={396} items={models} sel={3} />
        {inResults && (
          <>
            <div style={{ position: 'absolute', left: 141, top: 235, width: 1157, height: 665, background: '#fff' }} />
            {rows.map((r, i) => {
              const b = r.box, p = sp(f, VEH.rowsIn + i * 5, { damping: 10, stiffness: 150, mass: 0.9 }), hov = i === 0 ? sp(f, VEH.hover, { damping: 12, stiffness: 180, mass: 0.7 }) : 0;
              return <Crop key={i} src="veh-3" x={b.x} y={b.y} w={b.w} h={b.h} style={{ opacity: Math.min(1, p * 2), transform: `perspective(1600px) translateX(${(1 - Math.min(1, p)) * 700}px) rotateY(${(1 - Math.min(1, p)) * -40}deg) translateY(${-hov * 5}px) scale(${1 + hov * 0.015 + Math.max(0, p - 1) * 0.3})`, transformOrigin: '100% 50%', boxShadow: hov > 0 ? `0 ${18 * hov}px ${40 * hov}px rgba(11,26,51,${0.22 * hov})` : undefined, background: hov > 0 ? `rgba(238,243,252,${hov})` : undefined, zIndex: i === 0 ? 3 : 1 }} />;
            })}
          </>
        )}
        {f >= VEH.ship && f < VEH.product + 400 && (() => { const p = sp(f, VEH.ship, { damping: 9, stiffness: 200, mass: 0.7 }), pulse = 0.5 + 0.5 * Math.sin((f - VEH.ship) / 7); return (
          <div style={{ position: 'absolute', left: 1052, top: 300, transform: `scale(${0.5 + p * 0.5})`, transformOrigin: '0 50%', opacity: Math.min(1, p * 2), display: 'flex', alignItems: 'center', gap: 10, padding: '10px 18px', borderRadius: 999, background: '#e7f5ec', border: '1px solid #bfe3cc', fontFamily: FONT.h, fontWeight: 700, fontSize: 19, color: '#1a7f4b', boxShadow: `0 0 ${18 + pulse * 22}px rgba(26,127,75,${0.25 + pulse * 0.35})` }}>
            <span style={{ width: 12, height: 12, borderRadius: '50%', background: '#1a7f4b', boxShadow: `0 0 0 ${pulse * 7}px rgba(26,127,75,.25)` }} />In stock · ships today
          </div>); })()}
        <Callout at={VEH.make.set + 6} out={VEH.year.open - 6} x={156} y={505} w={264} h={46} dx={200} dy={-60} text="Make → Ford" sub="46 makes" z={cam[2]} />
        <Callout at={VEH.year.set + 6} out={VEH.model.open - 6} x={429} y={505} w={264} h={46} dx={160} dy={-60} text="Year → 2015" sub="1960 – 2025" z={cam[2]} />
        <Callout at={VEH.model.pick + 8} out={VEH.results + 30} x={156} y={584} w={396} h={46} dx={300} dy={-60} text="Model → F150" sub="21 models" z={cam[2]} />
        <Callout at={VEH.rowsIn + 60} out={VEH.hover + 40} x={141} y={158} w={420} h={26} dx={300} dy={-60} text="29 complete driveshafts" sub="2015 Ford F150 · assembled and balanced" z={cam[2]} />
        <Callout at={VEH.hover + 16} out={VEH.open + 10} x={141} y={235} w={1157} h={67} dx={-80} dy={110} text="Part, price, fitment, weight" z={cam[2]} accent={C.red} />
        <Callout at={VEH.price} out={1e9} x={M['veh-4'].price.x} y={M['veh-4'].price.y} w={M['veh-4'].price.w} h={M['veh-4'].price.h} dx={-140} dy={100} text={M['veh-4'].priceText} sub="new · front · AWD/4WD" z={cam[2]} />
        <Cursor keys={keys} show={[VEH.page + 10, 1200]} />
      </Window>
      <TitleCard f={f} lines={['Complete *Driveshafts']} sub="find by vehicle" size={124} out={VEH.cardOut} />
    </AbsoluteFill>
  );
};

// ================================================================= 6 · online builder: a guided walkthrough, one step at a time, the 3D shaft reflects the build
export const Builder: React.FC = () => {
  const f = useF(); const n = BLD.steps.length;
  const idx = Math.max(0, Math.min(n - 1, Math.floor((f - BLD.page) / BLD.stepDur))), sf = f - BLD.page - idx * BLD.stepDur;
  const keysCam: [number, number[]][] = [[BLD.page - 10, [720, 470, 0.95, -12, 5]]];
  BLD.steps.forEach((s, i) => { const b = BLD.page + i * BLD.stepDur; keysCam.push([b + 30, [s.focus[0], s.focus[1], s.z, i % 2 ? -2 : 2, 1]]); keysCam.push([b + BLD.stepDur - 10, [s.focus[0] + 20, s.focus[1] + 10, s.z + 0.04, i % 2 ? 1 : -1, 0]]); });
  keysCam.push([BLD.ready, [700, 600, 1.1, 0, 0]], [BLD.ready + 60, [700, 600, 0.8, 0, 0]]);
  const cam = kf(f, keysCam, E.io);
  const steps: [number, string][] = [[-99, 'bld-0a']];
  BLD.steps.forEach((s, i) => { const b = BLD.page + i * BLD.stepDur; if (i < n - 1) steps.push([b + 84, BLD.steps[i + 1].shot]); else { steps.push([b + 84, 'bld-6i0'], [b + 120, 'bld-6i1']); } });
  const keys: CurKey[] = [{ f: BLD.page + 10, x: 700, y: 520 }];
  BLD.steps.forEach((s, i) => { const b = BLD.page + i * BLD.stepDur; keys.push({ f: b + 64, x: s.click[0] + 2, y: s.click[1] + 1 }); keys.push({ f: b + 80, x: s.click[0], y: s.click[1], click: true }); });
  keys.push({ f: BLD.ready - 10, x: 1000, y: 700 });
  const clicks = BLD.steps.map((_, i) => BLD.page + i * BLD.stepDur + 80);
  // the shaft: steel tube after step 4 (material), yokes pop on 5/6, length grows with the measurements, then a final hero pose
  const variant = f >= BLD.page + 6 * BLD.stepDur + 120 ? 'c' : f >= BLD.page + 3 * BLD.stepDur + 84 ? 'b' : 'a';
  const yk = 1 + 0.18 * Math.max(0, Math.sin(Math.PI * Math.min(1, Math.max(0, (f - (BLD.page + 4 * BLD.stepDur + 84)) / 22)))) + 0.18 * Math.max(0, Math.sin(Math.PI * Math.min(1, Math.max(0, (f - (BLD.page + 5 * BLD.stepDur + 84)) / 22))));
  const ready = P(f, BLD.ready, BLD.ready + 30, E.in);
  return (
    <AbsoluteFill>
      <Grid show={[BLD.page - 20, 1e9]} pulses={clicks} origin={[W / 2, H / 2]} />
      <Shaft3D variant={variant} x={f >= BLD.ready ? 60 : -420} y={f >= BLD.ready ? 300 : 180} scale={f >= BLD.ready ? 1.35 : 1.05} yokeScale={yk} tilt={-8} opacity={P(f, 0, 24, E.out) * (f >= BLD.ready ? 0.95 : 0.85)} blur={f >= BLD.ready ? 0 : 0.6} />
      <Window url={URL + '/online-builder'} cam={[cam[0], cam[1] + drift(f, 2, 200), cam[2], cam[3], cam[4]]} ox={150} shake={shake(keys, f)} opacity={(1 - ready) * cardIn(f, BLD.page - 20).opacity} filter={ready > 0 ? `blur(${ready * 16}px)` : cardIn(f, BLD.page - 20).filter}>
        <Shots steps={steps} />
        {BLD.steps.map((s, i) => { const b = BLD.page + i * BLD.stepDur; return <Callout key={i} at={b + 40} out={b + 82} x={s.click[0] - 120} y={s.click[1] - 26} w={240} h={52} dx={i % 2 ? -140 : 160} dy={-70} text={s.text} sub={s.sub} z={cam[2]} />; })}
        {/* the price in the build sheet gets a glow every time it changes */}
        {[3, 6].map((i) => { const b = BLD.page + i * BLD.stepDur + 84; const p = f >= b && f < b + 40 ? 1 - P(f, b, b + 40, E.out) : 0; return p > 0 ? <div key={i} style={{ position: 'absolute', left: 960, top: 860, width: 200, height: 60, borderRadius: 10, boxShadow: `0 0 ${30 * p}px rgba(59,123,255,${p})`, border: `2px solid rgba(59,123,255,${p})` }} /> : null; })}
        <Cursor keys={keys} show={[BLD.page + 6, BLD.ready - 4]} />
      </Window>
      {BLD.steps.map((s, i) => { const b = BLD.page + i * BLD.stepDur; return <Chip key={i} f={f} at={b + BLD.chip} out={b + BLD.stepDur + 26} x={90} y={86} text={`Step ${i + 1} of 7 · ${s.text}`} />; })}
      {f >= BLD.ready && (
        <AbsoluteFill>
          <AbsoluteFill style={{ background: 'radial-gradient(60% 60% at 50% 50%, rgba(7,18,38,.2), rgba(7,18,38,.85))', opacity: ready }} />
          <Impact at={BLD.ready + 2} />
          <Kinetic lines={['Configuration *ready']} at={BLD.ready + 4} size={84} y={H * 0.26} />
          <PriceUp at={BLD.ready + 6} value={BLD.price} dur={66} label="your price · built, straightened and balanced" />
          <div style={{ position: 'absolute', left: 0, right: 0, top: H * 0.78, textAlign: 'center', fontFamily: FONT.m, fontWeight: 600, fontSize: 20, letterSpacing: '.3em', textTransform: 'uppercase', color: C.ice, opacity: P(f, BLD.ready + 80, BLD.ready + 110, E.out) }}>1350 series · steel · weld yokes · A1 52.25" · C1 48.5"</div>
        </AbsoluteFill>
      )}
      <TitleCard f={f} lines={['Online Driveshaft *Builder']} sub="pick · measure · price · order" size={110} out={BLD.cardOut} />
    </AbsoluteFill>
  );
};

// ================================================================= 7 · designer: the drawing, each dimension draws itself as it is typed, then lifts off the page
const DIMS = [{ x1: 209, x2: 1235, y: 287, num: [720, 248] }, { x1: 284, x2: 1156, y: 392, num: [720, 353] }, { x1: 313, x2: 313, y: 450, y2: 547, num: [720, 522] }];
export const Designer: React.FC = () => {
  const f = useF();
  const tf = (i: number) => DSG.typeStart + i * DSG.stateDur;
  const rx = 8 + drift(f, 2.5, 420), ry = drift(f, 5, 520, 1);
  const cam = kf(f, [[DSG.page - 10, [720, 450, 0.95, 0, 0]], [DSG.page + 60, [720, 380, 1.3, 0, 0]], [tf(1), [720, 330, 1.45, 0, 0]], [tf(6), [720, 380, 1.4, 0, 0]], [tf(10), [600, 460, 1.5, 0, 0]], [tf(16) + 60, [720, 400, 1.25, 0, 0]], [DSG.rows[0] - 10, [420, 760, 1.6, 0, 0]], [DSG.rows[2] + 20, [440, 790, 1.62, 0, 0]], [DSG.share, [1100, 240, 1.5, 0, 0]], [DSG.lift, [720, 390, 1.2, 0, 0]]], E.io);
  const steps: [number, string][] = [[-99, 'd-m0']]; for (let i = 1; i < DSG.states; i++) steps.push([tf(i), 'd-m' + i]);
  const keys: CurKey[] = [{ f: DSG.page + 20, x: 980, y: 600 }, { f: tf(1) - 4, x: 720, y: 246, click: true }, { f: tf(6) - 4, x: 720, y: 353, click: true }, { f: tf(10) - 4, x: 720, y: 522, click: true }, { f: tf(16) + 40, x: 900, y: 430 }, { f: DSG.rows[0], x: 300, y: 756 }, { f: DSG.rows[1], x: 300, y: 791 }, { f: DSG.rows[2], x: 300, y: 826 }, { f: DSG.share, x: 1200, y: 131 }, { f: DSG.lift, x: 980, y: 640 }];
  const lift = sp(f, DSG.lift, { damping: 11, stiffness: 70, mass: 1.2 });
  const pulses = Array.from({ length: DSG.states - 1 }, (_, i) => tf(i + 1));
  const dim = (i: number) => { const d = DSG.dims[i], g = DIMS[i], draw = P(f, tf(d.from), tf(d.from) + 14, E.out), glow = Math.max(0, 1 - P(f, tf(d.to), tf(d.to) + 50, E.out)) * (f >= tf(d.to) ? 1 : 0); if (f < tf(d.from)) return null; const L = g.y2 ? g.y2 - g.y : g.x2 - g.x1; return (
    <g key={i}>
      <line x1={g.x1} y1={g.y} x2={g.y2 ? g.x1 : g.x2} y2={g.y2 ?? g.y} stroke="#3b7bff" strokeWidth={4} strokeLinecap="round" strokeDasharray={L} strokeDashoffset={L * (1 - draw)} style={{ filter: 'drop-shadow(0 0 6px rgba(59,123,255,.9))' }} />
      {glow > 0 && <rect x={g.num[0] - 110} y={g.num[1] - 36} width={220} height={60} rx={10} fill="none" stroke="#3b7bff" strokeOpacity={glow} strokeWidth={3} style={{ filter: `drop-shadow(0 0 ${12 * glow}px rgba(59,123,255,.9))` }} />}
    </g>); };
  return (
    <AbsoluteFill>
      <Grid show={[DSG.page - 20, 1e9]} pulses={pulses} origin={[W / 2, H * 0.45]} />
      <Shaft3D variant="b" x={700} y={560} scale={0.8} phase={60} tilt={-10} opacity={P(f, 0, 24, E.out) * 0.6} blur={1} />
      <Window url={URL + '/driveshaft-designer'} cam={[cam[0], cam[1] + drift(f, 2, 200), cam[2], ry, rx]} shake={shake(keys, f)} {...cardIn(f, DSG.page - 20)} opacity={cardIn(f, DSG.page - 20).opacity * (1 - lift * 0.55)}>
        <Shots steps={steps} />
        <svg width={1440} height={900} style={{ position: 'absolute', left: 0, top: 0, pointerEvents: 'none' }}>{DSG.dims.map((_, i) => dim(i))}</svg>
        {DSG.dims.map((d, i) => <Callout key={i} at={tf(d.from) + 10} out={i < 2 ? tf(DSG.dims[i + 1].from) - 6 : tf(16) + 80} x={DIMS[i].num[0] - 110} y={DIMS[i].num[1] - 36} w={220} h={60} dx={i === 2 ? 240 : -260} dy={i === 2 ? 0 : -40} text={d.text} sub={`typed · ${d.value}`} z={cam[2]} />)}
        <Callout at={tf(16) + 90} out={DSG.rows[0] - 4} x={160} y={700} w={420} h={150} dx={300} dy={-40} text="Measurements sheet fills itself" sub="every value from the drawing" z={cam[2]} />
        {DSG.rows.map((r, i) => { if (f < r) return null; const p = P(f, r, r + 14, E.out), o = 1 - P(f, DSG.share - 10, DSG.share, E.in); return <div key={i} style={{ position: 'absolute', left: 159, top: [740, 775, 810][i], width: (582 - 159) * p, height: 33, background: 'rgba(59,123,255,.16)', borderLeft: '4px solid #3b7bff', opacity: o }} />; })}
        {DSG.rows.map((r, i) => <Callout key={'r' + i} at={r + 4} out={i < 2 ? DSG.rows[i + 1] : DSG.share - 10} x={159} y={[740, 775, 810][i]} w={423} h={33} dx={340} dy={i === 2 ? 50 : -50} text={['C1 · 52.25" overall', 'W1 · 48.5" weld to weld', 'T1 · 3.5" tube OD'][i]} z={cam[2]} />)}
        <Callout at={DSG.share} out={DSG.lift - 6} x={1100} y={118} w={200} h={26} dx={-120} dy={90} text="Share it or send it to the shop" sub="one link · printable sheet" z={cam[2]} />
        <Cursor keys={keys} show={[DSG.page + 10, DSG.lift + 20]} />
      </Window>
      <Chip f={f} at={DSG.saved} out={1e9} x={W / 2 - 200} y={H * 0.86} text="Saved to your account" tone="blue" />
      {/* the drawing lifts off the page into 3D before the whip out */}
      {lift > 0.01 && (
        <div style={{ position: 'absolute', inset: 0, perspective: 2400 }}>
          <div style={{ position: 'absolute', left: W / 2, top: H * 0.5, width: 1160, height: 560, transform: `translate(-50%,-50%) translateY(${-lift * 60}px) rotateX(${-14 * lift + rx * 0.3}deg) rotateY(${ry * 0.3}deg) scale(${cam[2] * 0.98 * (1 + lift * 0.18)})`, transformOrigin: '50% 50%', borderRadius: 14, overflow: 'hidden', boxShadow: `0 ${60 * lift}px ${120 * lift}px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.2)`, opacity: Math.min(1, lift * 3) }}>
            <Img src={S('d-m15')} style={{ position: 'absolute', left: -140, top: -108, width: 1440, display: 'block' }} />
          </div>
        </div>
      )}
      <TitleCard f={f} lines={['Driveshaft *Designer']} sub="draw it · dimension it · share it" size={118} out={DSG.cardOut} />
    </AbsoluteFill>
  );
};

// ================================================================= 8 · cart + checkout
export const Checkout: React.FC = () => {
  const f = useF();
  const cam = kf(f, [[0, [720, 400, 1.0, 10, 4]], [CHK.click - 20, [1100, 560, 1.35, 0, 0]], [CHK.checkout + 30, [900, 420, 1.15, 0, 0]], [CHK.total, [1080, 560, 1.4, 0, 0]], [CHK.sms, [720, 520, 1.4, 0, 0]], [len('checkout') + T2, [700, 440, 1.28, -4, 1]]], E.io);
  const keys: CurKey[] = [{ f: 60, x: 800, y: 420 }, { f: CHK.click, x: 1125, y: 591, click: true }, { f: CHK.checkout + 40, x: 900, y: 520 }, { f: CHK.total, x: 1150, y: 690 }, { f: CHK.sms, x: 551, y: 548 }, { f: 600, x: 560, y: 560 }];
  return (
    <AbsoluteFill>
      <Window url={URL + '/checkout'} cam={[cam[0], cam[1] + drift(f, 2, 200), cam[2], cam[3], cam[4]]} shake={shake(keys, f)} {...cardIn(f)}>
        <Shots steps={[[-99, 'cart'], [CHK.checkout, 'checkout']]} />
        <Callout at={CHK.click - 60} out={CHK.click + 10} x={1000} y={568} w={250} h={46} dx={-120} dy={-70} text="Straight to checkout" z={cam[2]} />
        <Callout at={CHK.total} out={CHK.sms - 6} x={942} y={668} w={335} h={44} dx={-150} dy={-70} text="Shipping and total before you pay" sub="$456.28 + $12.55 = $468.83" z={cam[2]} />
        <Callout at={CHK.sms} out={1e9} x={525} y={523} w={343} h={68} dx={120} dy={110} text="Text me order updates" sub="shipping and delivery by SMS" z={cam[2]} />
        <Callout at={CHK.callout} out={CHK.total - 6} x={917} y={345} w={374} h={136} dx={-140} dy={-60} text="UPS · FedEx · LTL freight · pickup" sub="live rates at checkout · ships from Florida" z={cam[2]} />
        <Cursor keys={keys} show={[50, 600]} />
      </Window>
      <TitleCard f={f} lines={['Checkout in *a minute']} sub="cart · shipping · pay" size={104} />
    </AbsoluteFill>
  );
};

// ================================================================= 9 · my account: desktop, with the phone version popping in front (3D parallax)
export const Account: React.FC = () => {
  const f = useF();
  const cam = kf(f, [[0, [720, 460, 0.9, 14, 4]], [ACC.phone + 60, [860, 520, 1.05, 4, 2]], [ACC.tracker, [850, 590, 1.12, 2, 1]], [ACC.sidebar, [560, 460, 1.12, -2, 1]], [ACC.table, [850, 760, 1.15, 0, 1]], [len('account') + T2, [860, 760, 1.18, 0, 1]]], E.io);
  const p = sp(f, ACC.phone, { damping: 10, stiffness: 120, mass: 1 }); const dx = drift(f, 10, 260), dy = drift(f, 6, 200, 1);
  const keys: CurKey[] = [{ f: 60, x: 700, y: 400 }, { f: 200, x: 600, y: 520 }, { f: ACC.tracker, x: 745, y: 580 }, { f: ACC.sidebar, x: 260, y: 372 }, { f: ACC.table, x: 600, y: 790 }, { f: 600, x: 640, y: 800 }];
  return (
    <AbsoluteFill>
      <Window url={URL + '/account'} cam={[cam[0] + dx * 0.4, cam[1] + dy * 0.4, cam[2], cam[3], cam[4]]} ox={-160} {...cardIn(f)}>
        <Shots steps={[[-99, 'account']]} />
        <Callout at={ACC.tracker} out={ACC.sidebar - 6} x={425} y={555} w={853} h={60} dx={-160} dy={90} text="Placed · Paid · Shipped · Delivered" sub="live status on every order" z={cam[2]} />
        <Callout at={ACC.sidebar} out={ACC.table - 6} x={149} y={353} w={222} h={137} dx={260} dy={-60} text="Buy again · saved addresses · designs" z={cam[2]} />
        <Callout at={ACC.table} out={1e9} x={405} y={720} w={895} h={180} dx={-120} dy={-60} text="Tracking numbers and invoices" sub="UPS · FedEx · freight" z={cam[2]} />
        <Callout at={ACC.callout} out={ACC.tracker - 6} x={330} y={200} w={760} h={120} dx={-120} dy={-60} text="Every order, tracked" sub="orders · tracking · returns · addresses" z={cam[2]} />
        <Cursor keys={keys} show={[50, 600]} />
      </Window>
      <Phone src="account-m" x={W * 0.76 - dx * 1.6} y={H * 0.6 - dy * 1.4 + (1 - p) * H * 0.7} scale={0.92} ry={-16 + p * 5} rz={(1 - p) * 8 + 3} opacity={Math.min(1, p * 2)} scroll={Math.max(0, (f - ACC.phone - 60)) * 0.9} />
      <Callout at={ACC.callout + 60} out={ACC.tracker - 8} x={W * 0.76 - 120} y={H * 0.6 - 300} w={240} h={400} dx={-260} dy={-80} text="The same account on your phone" />
      <TitleCard f={f} lines={['My *Account']} sub="desktop · phone" size={110} />
    </AbsoluteFill>
  );
};

// ================================================================= 10 · logo + CTA
export const End: React.FC = () => {
  const f = useF();
  const wipe = P(f, END.logo + 10, END.logo + 44, E.out), card = sp(f, END.logo, { damping: 12, stiffness: 120, mass: 1 }), cta = sp(f, END.cta, { damping: 12, stiffness: 150, mass: 0.9 });
  const pulse = f >= END.logo ? Math.max(0, 1 - P(f, END.logo, END.logo + 60, E.out)) : 0;
  return (
    <AbsoluteFill>
      <Shaft3D variant="a" x={210} y={470} scale={1.15} phase={40} tilt={-5} opacity={0.75} />
      <Impact at={END.logo} />
      <div style={{ position: 'absolute', left: W / 2, top: H * 0.34, width: 1100, height: 264, transform: `translate(-50%,-50%) scale(${0.8 + card * 0.2})`, opacity: Math.min(1, card * 2), background: '#fff', borderRadius: 34, boxShadow: `0 50px 120px rgba(0,0,0,.5), 0 0 ${80 * pulse}px rgba(255,255,255,${pulse * 0.8})` }}>
        <Img src={staticFile('v3/logo.svg')} style={{ position: 'absolute', left: '6%', top: '15%', width: '88%', height: '70%', objectFit: 'contain', clipPath: `inset(0 ${(1 - wipe) * 100}% 0 0)` }} />
      </div>
      <Kinetic lines={['The driveshaft parts you need,', '*on *the *shelf *today.']} at={END.tag} size={52} y={H * 0.62} />
      <div style={{ position: 'absolute', left: '50%', top: H * 0.8, transform: `translate(-50%,-50%) translateY(${(1 - cta) * 30}px) scale(${0.9 + cta * 0.1})`, opacity: Math.min(1, cta * 1.6), whiteSpace: 'nowrap', padding: '22px 46px', borderRadius: 999, background: C.red, color: '#fff', fontFamily: FONT.h, fontWeight: 700, fontSize: 34, boxShadow: '0 20px 60px rgba(226,58,56,.5)' }}>Now live · universaldriveshafts.com</div>
    </AbsoluteFill>
  );
};
