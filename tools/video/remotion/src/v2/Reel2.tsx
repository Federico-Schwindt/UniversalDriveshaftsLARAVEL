import React from 'react';
import { AbsoluteFill, Audio, Img, OffthreadVideo, Sequence, staticFile, useCurrentFrame, interpolate, Easing } from 'remotion';
import { useFonts, FONT } from '../launch/theme';
import t42621 from '../../public/v2/track-42621.json';
import t42630 from '../../public/v2/track-42630.json';
import t52076 from '../../public/v2/track-52076C.json';

// ---------------------------------------------------------------- timing (30 fps, music 124 BPM, first beat at 0.29 s)
export const FPS = 30;
const B = (n: number) => Math.round(9 + 14.516 * n);
export const CUT = { hook2: B(4), intro: B(8), search: B(16), finder: B(22), designer: B(34), assistant: B(45), pay: B(57), payUi: B(60), deliver: B(64), account: B(67), road: B(72), end: B(78) };
export const TOTAL2 = B(88) + 6;
const XF = 8; // transition overlap

const C = { navy: '#071226', blue: '#1347c9', blueHi: '#2f6bff', red: '#e5343b', white: '#ffffff' };
const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
const E = { out: Easing.bezier(0.16, 1, 0.3, 1), inOut: Easing.bezier(0.65, 0, 0.35, 1), back: Easing.bezier(0.34, 1.45, 0.64, 1), in: Easing.bezier(0.55, 0, 0.75, 0.2) };
const ease = (f: number, a: number, b: number, x: number, y: number, e = E.inOut) => interpolate(f, [a, b], [x, y], { ...clamp, easing: e });
const S = (f: string) => staticFile('v2/shots/' + f + '.png');
const V = (f: string) => staticFile('v2/clips/' + f + '.mp4');
const X = (f: string) => staticFile('v2/sfx/' + f + '.mp3');

// ---------------------------------------------------------------- homography: element (w×h, origin 0 0) -> quad TL,TR,BR,BL
const matrix3d = (w: number, h: number, q: number[]) => {
  const [x0, y0, x1, y1, x2, y2, x3, y3] = q;
  const dx1 = x1 - x2, dx2 = x3 - x2, dx3 = x0 - x1 + x2 - x3, dy1 = y1 - y2, dy2 = y3 - y2, dy3 = y0 - y1 + y2 - y3;
  const den = dx1 * dy2 - dx2 * dy1;
  const g = (dx3 * dy2 - dx2 * dy3) / den, hh = (dx1 * dy3 - dx3 * dy1) / den;
  const a = x1 - x0 + g * x1, b = x3 - x0 + hh * x3, c = x0, d = y1 - y0 + g * y1, e = y3 - y0 + hh * y3, f = y0;
  return `matrix3d(${a / w},${d / w},0,${g / w},${b / h},${e / h},0,${hh / h},0,0,1,0,${c},${f},0,1)`;
};
const grow = (q: number[], k: number) => { const cx = (q[0] + q[2] + q[4] + q[6]) / 4, cy = (q[1] + q[3] + q[5] + q[7]) / 4; return q.map((v, i) => (i % 2 ? cy + (v - cy) * k : cx + (v - cx) * k)); };

// green-screen key: alpha = 1.3 - 5·(G - (R+B)/2); anything green goes transparent and the screen content underneath shows through
const KeyFilter: React.FC = () => (
  <svg width="0" height="0" style={{ position: 'absolute' }}>
    <filter id="gkey" colorInterpolationFilters="sRGB">
      <feColorMatrix type="matrix" values="1 0 0 0 0  0.25 0.5 0.25 0 0  0 0 1 0 0  2.5 -5 2.5 0 1.3" />
    </filter>
  </svg>
);

// ---------------------------------------------------------------- screen content: a sequence of real captures with taps and an optional scroll
type Step = { src: string; at: number; tap?: { x: number; y: number } ; scroll?: [number, number, number, number] }; // scroll: from,to frames + y0,y1 (css px)
const Screen: React.FC<{ steps: Step[]; w: number; h: number; dpr: number; imgH?: number }> = ({ steps, w, h, dpr }) => {
  const f = useCurrentFrame();
  let idx = 0; steps.forEach((s, i) => { if (f >= s.at) idx = i; });
  const cur = steps[idx], next = steps[idx + 1];
  const fade = next ? interpolate(f, [next.at - 3, next.at], [0, 1], clamp) : 0;
  const y = (s: Step) => (s.scroll ? ease(f, s.scroll[0], s.scroll[1], s.scroll[2], s.scroll[3], E.inOut) : 0) * dpr;
  const tapStep = next && next.tap ? next : null; // tap on the current screen just before the next capture appears
  const tp = tapStep ? interpolate(f, [tapStep.at - 9, tapStep.at + 3], [0, 1], clamp) : 0;
  return (
    <div style={{ width: w, height: h, overflow: 'hidden', position: 'relative', background: '#fff' }}>
      <Img src={S(cur.src)} style={{ position: 'absolute', left: 0, top: -y(cur), width: w }} />
      {next && fade > 0 && <Img src={S(next.src)} style={{ position: 'absolute', left: 0, top: -y(next), width: w, opacity: fade }} />}
      {tapStep && tp > 0 && tp < 1 && (
        <div style={{ position: 'absolute', left: tapStep.tap!.x * dpr, top: tapStep.tap!.y * dpr, width: 0, height: 0 }}>
          <div style={{ position: 'absolute', width: 64 * dpr * 0.5, height: 64 * dpr * 0.5, left: -16 * dpr, top: -16 * dpr, borderRadius: '50%', background: 'rgba(20,40,90,.28)', transform: `scale(${0.6 + tp * 0.6})`, opacity: tp < 0.75 ? 1 : (1 - tp) * 4 }} />
          <div style={{ position: 'absolute', width: 64 * dpr * 0.5, height: 64 * dpr * 0.5, left: -16 * dpr, top: -16 * dpr, borderRadius: '50%', border: `${2 * dpr}px solid rgba(255,255,255,.9)`, transform: `scale(${0.8 + tp * 1.4})`, opacity: 1 - tp }} />
        </div>
      )}
    </div>
  );
};

// ---------------------------------------------------------------- real footage with a tracked screen: base video, warped site, keyed video on top
const Tracked: React.FC<{ clip: string; track: number[][]; from?: number; content: React.ReactNode; cw: number; ch: number; vw?: number; vh?: number; grow?: number }> = ({ clip, track, from = 0, content, cw, ch, vw = 1080, vh = 1920, grow: gk = 1.05 }) => {
  const f = useCurrentFrame();
  const q = grow(track[Math.min(track.length - 1, f + from)], gk);
  return (
    <div style={{ position: 'absolute', left: 0, top: 0, width: vw, height: vh }}>
      <OffthreadVideo src={V(clip)} startFrom={from} muted style={{ position: 'absolute', width: vw, height: vh }} />
      <div style={{ position: 'absolute', left: 0, top: 0, width: cw, height: ch, transformOrigin: '0 0', transform: matrix3d(cw, ch, q) }}>{content}</div>
      <OffthreadVideo src={V(clip)} startFrom={from} muted style={{ position: 'absolute', width: vw, height: vh, filter: 'url(#gkey)' }} />
    </div>
  );
};

// ---------------------------------------------------------------- floating CSS phone (for screens without footage)
const Phone: React.FC<{ children: React.ReactNode; scale?: number; ry?: number; rx?: number; y?: number }> = ({ children, scale = 1, ry = 0, rx = 0, y = 0 }) => {
  const W = 600, H = 1304;
  return (
    <div style={{ position: 'absolute', left: 540 - W / 2, top: 960 - H / 2 + y, width: W, height: H, transform: `perspective(2400px) rotateY(${ry}deg) rotateX(${rx}deg) scale(${scale})`, transformStyle: 'preserve-3d' }}>
      <div style={{ position: 'absolute', inset: 0, borderRadius: 92, background: 'linear-gradient(135deg,#3a3f48,#0c0e12 40%,#2a2e35)', boxShadow: '0 80px 140px rgba(0,0,0,.55), 0 30px 50px rgba(0,0,0,.35), inset 0 0 0 2px rgba(255,255,255,.12)' }} />
      <div style={{ position: 'absolute', inset: 16, borderRadius: 78, overflow: 'hidden', background: '#000' }}>
        <div style={{ width: 1206, height: 2622 + 150, transform: `scale(${(W - 32) / (1206)})`, transformOrigin: '0 0', background: '#fff' }}>
          <div style={{ height: 150, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '24px 96px 0', fontFamily: FONT.b, fontWeight: 700, fontSize: 50, color: '#0b1730' }}>
            <span>9:41</span><span style={{ letterSpacing: 6 }}>▮▮▮ ◔</span>
          </div>
          {children}
        </div>
        <div style={{ position: 'absolute', top: 22, left: '50%', width: 170, height: 48, marginLeft: -85, borderRadius: 30, background: '#000' }} />
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(115deg,rgba(255,255,255,.16) 0%,rgba(255,255,255,0) 35%)', pointerEvents: 'none' }} />
      </div>
    </div>
  );
};

// soft-focus footage + brand tint behind floating devices
const Ambient: React.FC<{ clip: string; from?: number; tint?: number }> = ({ clip, from = 0, tint = 0.72 }) => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <OffthreadVideo src={V(clip)} startFrom={from} muted style={{ width: 1080, height: 1920, filter: 'blur(26px) saturate(1.2)', transform: `scale(${1.15 + f * 0.0008})` }} />
      <AbsoluteFill style={{ background: `radial-gradient(120% 70% at 50% 45%, rgba(19,71,201,${tint * 0.55}) 0%, rgba(7,18,38,${tint}) 70%)` }} />
    </AbsoluteFill>
  );
};

// ---------------------------------------------------------------- kinetic type
const Title: React.FC<{ lines: { t: string; accent?: string[] }[]; at?: number; y?: number; size?: number; out?: number; sub?: string; dark?: boolean }> = ({ lines, at = 0, y = 250, size = 104, out, sub, dark }) => {
  const f = useCurrentFrame();
  const o = out !== undefined ? 1 - interpolate(f, [out, out + 8], [0, 1], { ...clamp, easing: E.in }) : 1;
  let k = 0;
  return (
    <div style={{ position: 'absolute', left: 70, right: 70, top: y, opacity: o, transform: `translateY(${(1 - o) * -30}px)` }}>
      {lines.map((ln, li) => (
        <div key={li} style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: `0 ${size * 0.24}px`, marginBottom: size * 0.02 }}>
          {ln.t.split(' ').map((w, wi) => {
            const d = at + (k++) * 3; const p = interpolate(f, [d, d + 16], [0, 1], { ...clamp, easing: E.back });
            const acc = ln.accent?.includes(w.replace(/[.,!?]/g, ''));
            return (
              <span key={wi} style={{ display: 'inline-block', overflow: 'hidden', paddingBottom: size * 0.08 }}>
                <span style={{ display: 'inline-block', transform: `translateY(${(1 - p) * 110}%)`, opacity: Math.min(1, p * 2), fontFamily: FONT.h, fontWeight: 800, fontSize: size, lineHeight: 1.02, letterSpacing: -size * 0.025, color: acc ? C.red : dark ? '#0b1730' : C.white, textShadow: dark ? 'none' : acc ? '0 0 40px rgba(229,52,59,.45), 0 6px 30px rgba(0,0,0,.45)' : '0 6px 30px rgba(0,0,0,.5)' }}>{w}</span>
              </span>
            );
          })}
        </div>
      ))}
      {sub && (
        <div style={{ textAlign: 'center', marginTop: 18, fontFamily: FONT.m, fontWeight: 600, fontSize: 30, letterSpacing: 5, textTransform: 'uppercase', color: dark ? C.blue : '#cfe0ff', opacity: interpolate(f, [at + k * 3 + 6, at + k * 3 + 18], [0, 1], clamp), textShadow: dark ? 'none' : '0 3px 16px rgba(0,0,0,.6)' }}>{sub}</div>
      )}
    </div>
  );
};

const TopShade: React.FC<{ h?: number; a?: number }> = ({ h = 900, a = 0.7 }) => <div style={{ position: 'absolute', left: 0, right: 0, top: 0, height: h, background: `linear-gradient(180deg, rgba(4,10,24,${a}) 0%, rgba(4,10,24,${a * 0.6}) 45%, rgba(4,10,24,0) 100%)` }} />;
const Grain: React.FC = () => { const f = useCurrentFrame(); return <AbsoluteFill style={{ opacity: 0.06, mixBlendMode: 'overlay', backgroundImage: 'url("data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><filter id=%22n%22><feTurbulence baseFrequency=%220.9%22 numOctaves=%222%22 stitchTiles=%22stitch%22/></filter><rect width=%22200%22 height=%22200%22 filter=%22url(%23n)%22/></svg>")', backgroundPosition: `${(f * 37) % 200}px ${(f * 53) % 200}px` }} />; };

// ---------------------------------------------------------------- shot wrapper: zoom-blur in and out so every cut carries motion
const Shot: React.FC<{ from: number; to: number; children: React.ReactNode; inFx?: 'zoom' | 'whip' | 'none'; z?: number }> = ({ from, to, children, inFx = 'zoom' }) => {
  const dur = to - from + XF;
  return (
    <Sequence from={from} durationInFrames={dur}>
      <ShotFx dur={dur} inFx={inFx}>{children}</ShotFx>
    </Sequence>
  );
};
const ShotFx: React.FC<{ dur: number; inFx: string; children: React.ReactNode }> = ({ dur, inFx, children }) => {
  const f = useCurrentFrame();
  const i = inFx === 'none' ? 1 : interpolate(f, [0, XF], [0, 1], { ...clamp, easing: E.out });
  const o = interpolate(f, [dur - XF, dur], [0, 1], { ...clamp, easing: E.in });
  const sc = (inFx === 'zoom' ? 1 + (1 - i) * 0.18 : 1) * (1 + o * 0.12);
  const tx = inFx === 'whip' ? (1 - i) * 900 : 0;
  const blur = (1 - i) * 22 + o * 18;
  return <AbsoluteFill style={{ transform: `translateX(${tx}px) scale(${sc})`, filter: blur > 0.3 ? `blur(${blur}px)` : undefined, opacity: inFx === 'none' ? 1 - o * 0 : Math.min(1, i * 1.6) }}>{children}</AbsoluteFill>;
};
const Flash: React.FC<{ at: number }> = ({ at }) => { const f = useCurrentFrame(); const a = interpolate(f, [at - 2, at, at + 5], [0, 0.55, 0], clamp); return a > 0 ? <AbsoluteFill style={{ background: '#fff', opacity: a }} /> : null; };

// ---------------------------------------------------------------- scenes
const Hook: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <OffthreadVideo src={V('4716')} muted style={{ width: 1080, height: 1920, transform: `scale(${1.08 + f * 0.0012})` }} />
      <TopShade a={0.75} />
      <Title at={6} y={300} size={124} lines={[{ t: 'Truck down?', accent: ['down'] }]} />
    </AbsoluteFill>
  );
};
const Hook2: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <OffthreadVideo src={V('41937')} startFrom={30} muted style={{ width: 1080, height: 1920, transform: `scale(${1.05 + f * 0.0015})` }} />
      <TopShade a={0.75} />
      <Title at={2} y={280} size={104} lines={[{ t: 'Every hour' }, { t: 'costs you.', accent: ['costs'] }]} />
    </AbsoluteFill>
  );
};

const Intro: React.FC = () => {
  const f = useCurrentFrame(); const d = CUT.search - CUT.intro;
  const z = ease(f, 0, d, 1.0, 1.14);
  return (
    <AbsoluteFill style={{ background: '#000' }}>
      <div style={{ position: 'absolute', inset: 0, transform: `scale(${z})`, transformOrigin: '62% 30%' }}>
        <Tracked clip="42621" track={t42621} from={20} cw={1206} ch={2622} content={<Screen w={1206} h={2622} dpr={3} steps={[{ src: 'home-scroll', at: 0, scroll: [20, d, 0, 1650] }]} />} />
      </div>
      <TopShade h={760} a={0.55} />
      <Title at={8} y={1330} size={70} lines={[{ t: 'Meet the new' }]} />
      <Title at={16} y={1420} size={64} lines={[{ t: 'universaldriveshafts.com', accent: ['universaldriveshafts.com'] }]} />
    </AbsoluteFill>
  );
};

const Search: React.FC = () => {
  const f = useCurrentFrame();
  const ry = ease(f, 0, 70, 24, -6), rx = ease(f, 0, 70, 10, 4);
  return (
    <AbsoluteFill>
      <Ambient clip="41556" />
      <Phone ry={ry} rx={rx} scale={ease(f, 0, 30, 0.86, 0.92, E.out)} y={170 + Math.sin(f / 18) * 6}>
        <Screen w={1206} h={2622} dpr={3} steps={[
          { src: 'home-0', at: 0 }, { src: 'search-1', at: 14, tap: { x: 258, y: 80 } }, { src: 'search-t1', at: 26 }, { src: 'search-t2', at: 31 }, { src: 'search-t3', at: 36 }, { src: 'search-t4', at: 41 },
        ]} />
      </Phone>
      <Title at={4} y={150} size={96} lines={[{ t: 'Search any part.', accent: ['any'] }]} sub="6,800+ parts · live prices" />
    </AbsoluteFill>
  );
};

const Finder: React.FC = () => {
  const f = useCurrentFrame(); const d = CUT.designer - CUT.finder;
  const z = ease(f, 0, 60, 1.18, 1.0) * ease(f, 120, d, 1, 1.1);
  const t = (n: number) => 14 + n * 21;
  const steps: Step[] = [
    { src: 'finder-0', at: 0 }, { src: 'finder-1b', at: t(1), tap: { x: 295, y: 497 } }, { src: 'finder-2b', at: t(2), tap: { x: 107, y: 497 } }, { src: 'finder-3b', at: t(3), tap: { x: 295, y: 497 } },
    { src: 'finder-x0b', at: t(4), tap: { x: 107, y: 608 } }, { src: 'finder-x1b', at: t(5), tap: { x: 107, y: 427 } }, { src: 'finder-res', at: t(6) + 4 }, { src: 'finder-added', at: t(7) + 6, tap: { x: 201, y: 397 } },
  ];
  return (
    <AbsoluteFill style={{ background: '#000' }}>
      <div style={{ position: 'absolute', inset: 0, transform: `scale(${z})`, transformOrigin: '55% 35%' }}>
        <Tracked clip="42630" track={t42630} from={10} cw={1206} ch={2622} content={<Screen w={1206} h={2622} dpr={3} steps={steps} />} />
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, height: 900, background: 'linear-gradient(0deg, rgba(4,10,24,.8), rgba(4,10,24,0))' }} />
      <Title at={6} y={1240} size={92} lines={[{ t: 'Find yours' }, { t: 'by vehicle.', accent: ['vehicle'] }]} sub="Ford · 2015 · F150 · 4WD" />
    </AbsoluteFill>
  );
};

const Designer: React.FC = () => {
  const f = useCurrentFrame(); const d = CUT.assistant - CUT.designer;
  // laptop footage is 2560×1440; scale to fill the 1920 height, then push in on the screen
  const s0 = 1920 / 1440, z = ease(f, 0, d, 1.0, 1.28, E.inOut);
  const cx = 1290 - 790, cy = 800; // screen centre in footage px
  const steps: Step[] = [{ src: 'd-m0', at: 0 }]; for (let i = 1; i <= 15; i++) steps.push({ src: 'd-m' + i, at: 22 + i * 7 });
  return (
    <AbsoluteFill style={{ background: '#000', overflow: 'hidden' }}>
      <div style={{ position: 'absolute', left: 540, top: 1120, width: 0, height: 0, transform: `scale(${s0 * z})` }}>
        <div style={{ position: 'absolute', left: -cx, top: -cy }}>
          <Tracked clip="52076C" track={t52076} from={30} vw={1000} vh={1440} cw={2880} ch={1800} grow={1.02} content={<Screen w={2880} h={1800} dpr={2} steps={steps} />} />
        </div>
      </div>
      <TopShade h={760} a={0.95} />
      <Title at={6} y={170} size={100} lines={[{ t: 'Draw it.' }, { t: 'Dimension it.', accent: ['Dimension'] }]} sub="Free driveshaft designer" />
    </AbsoluteFill>
  );
};

const Assistant: React.FC = () => {
  const f = useCurrentFrame();
  const ty = ['01', '05', '09', '13', '17', '21', '25', '29', '33', '37', '41', '45', '49', '53', '57', '99'];
  const steps: Step[] = [{ src: 'a-0', at: 0 }, { src: 'a-1', at: 16, tap: { x: 296, y: 835 } }, ...ty.map((n, i) => ({ src: 'a-t' + n, at: 26 + i * 2 })), { src: 'a-2', at: 64 }, { src: 'a-3', at: 72 }, { src: 'a-4', at: 92 }, { src: 'a-5', at: 140 }];
  const ry = ease(f, 0, 90, -22, 6), rx = ease(f, 0, 90, 8, 2);
  return (
    <AbsoluteFill>
      <Ambient clip="42630" from={60} tint={0.8} />
      <Phone ry={ry} rx={rx} scale={ease(f, 0, 30, 0.84, 0.92, E.out)} y={180 + Math.sin(f / 20) * 6}>
        <Screen w={1206} h={2622} dpr={3} steps={steps} />
      </Phone>
      <Title at={4} y={140} size={100} lines={[{ t: 'Just ask.', accent: ['ask'] }]} sub="UDS Assistant · real stock, real prices" />
    </AbsoluteFill>
  );
};

const Pay: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <OffthreadVideo src={V('18264')} startFrom={20} muted style={{ width: 1080, height: 1920, transform: `scale(${1.04 + f * 0.0016})` }} />
      <TopShade a={0.7} />
      <Title at={4} y={300} size={100} lines={[{ t: 'Checkout' }, { t: 'in a minute.', accent: ['minute'] }]} />
    </AbsoluteFill>
  );
};
const PayUi: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <Ambient clip="18264" from={60} />
      <Phone ry={ease(f, 0, 60, 18, -4)} rx={6} scale={ease(f, 0, 24, 0.86, 0.92, E.out)} y={160}>
        <Screen w={1206} h={2622} dpr={3} steps={[{ src: 'cart', at: 0 }, { src: 'checkout', at: 40, tap: { x: 201, y: 830 } }]} />
      </Phone>
      <Title at={2} y={150} size={76} lines={[{ t: 'UPS · FedEx · LTL freight' }]} sub="Ships same day before 2pm ET" />
    </AbsoluteFill>
  );
};
const Deliver: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <OffthreadVideo src={V('31297')} startFrom={30} muted style={{ width: 1080, height: 1920, transform: `scale(${1.06 + f * 0.0014})` }} />
      <TopShade a={0.6} />
      <Title at={4} y={300} size={110} lines={[{ t: 'Delivered.', accent: ['Delivered'] }]} />
    </AbsoluteFill>
  );
};
const Account: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <Ambient clip="31297" from={90} />
      <Phone ry={ease(f, 0, 70, -18, 4)} rx={6} scale={ease(f, 0, 24, 0.86, 0.92, E.out)} y={170}>
        <Screen w={1206} h={2622} dpr={3} steps={[{ src: 'account', at: 0 }]} />
      </Phone>
      <Title at={2} y={150} size={84} lines={[{ t: 'Track it all in' }, { t: 'My Account.', accent: ['My', 'Account'] }]} />
    </AbsoluteFill>
  );
};
const Road: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <OffthreadVideo src={V('41558')} startFrom={15} muted style={{ width: 1080, height: 1920, transform: `scale(${1.04 + f * 0.0012})` }} />
      <TopShade a={0.65} />
      <Title at={4} y={300} size={112} lines={[{ t: 'Back on' }, { t: 'the road.', accent: ['road'] }]} />
    </AbsoluteFill>
  );
};

const End: React.FC = () => {
  const f = useCurrentFrame();
  const p = interpolate(f, [4, 26], [0, 1], { ...clamp, easing: E.back });
  const wipe = ease(f, 14, 40, 0, 100, E.out);
  const shine = ease(f, 40, 70, -30, 130);
  return (
    <AbsoluteFill style={{ background: `radial-gradient(90% 60% at 50% 45%, #163a8f 0%, ${C.navy} 70%)` }}>
      <AbsoluteFill style={{ backgroundImage: 'linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px)', backgroundSize: '60px 60px', transform: `scale(${1.1 - f * 0.0006})` }} />
      <div style={{ position: 'absolute', left: 90, right: 90, top: 720, height: 210, transform: `scale(${0.8 + p * 0.2})`, opacity: Math.min(1, p * 1.5) }}>
        <div style={{ position: 'absolute', inset: -34, borderRadius: 34, background: '#fff', boxShadow: '0 40px 120px rgba(0,0,0,.45)' }} />
        <div style={{ position: 'absolute', inset: 0, clipPath: `inset(0 ${100 - wipe}% 0 0)` }}>
          <Img src={staticFile('uds-logo.svg')} style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
        </div>
        <div style={{ position: 'absolute', inset: -34, borderRadius: 34, overflow: 'hidden', pointerEvents: 'none' }}>
          <div style={{ position: 'absolute', top: 0, bottom: 0, width: 120, left: `${shine}%`, background: 'linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.7),rgba(255,255,255,0))', transform: 'skewX(-20deg)' }} />
        </div>
      </div>
      <Title at={30} y={1060} size={58} lines={[{ t: 'The driveshaft parts you need,' }, { t: 'on the shelf today.', accent: ['today'] }]} />
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1300, display: 'flex', justifyContent: 'center', opacity: interpolate(f, [52, 64], [0, 1], clamp), transform: `translateY(${ease(f, 52, 70, 30, 0, E.out)}px)` }}>
        <div style={{ padding: '26px 48px', borderRadius: 999, background: C.red, color: '#fff', fontFamily: FONT.h, fontWeight: 700, fontSize: 44, boxShadow: '0 20px 60px rgba(229,52,59,.45)' }}>Now live · universaldriveshafts.com</div>
      </div>
    </AbsoluteFill>
  );
};

// ---------------------------------------------------------------- sound
const Sfx: React.FC = () => {
  const at = (frame: number, src: string, vol: number, dur = 60, start = 0) => <Sequence key={src + frame} from={Math.max(0, frame)} durationInFrames={dur}><Audio src={X(src)} volume={vol} startFrom={start} /></Sequence>;
  const whooshes = [CUT.hook2, CUT.intro, CUT.search, CUT.finder, CUT.designer, CUT.assistant, CUT.pay, CUT.payUi, CUT.deliver, CUT.account, CUT.road];
  const tapsFinder = [1, 2, 3, 4, 5, 7].map((n) => CUT.finder + 14 + n * 21 - 6);
  return (
    <>
      <Audio src={X('m51')} volume={(fr) => interpolate(fr, [0, 6, TOTAL2 - 50, TOTAL2], [0, 0.5, 0.5, 0], clamp)} />
      {whooshes.map((w, i) => at(w - 5, i % 2 ? 's1490' : 's1492', 0.42, 40))}
      {at(CUT.search + 14 - 6, 's2568', 0.7, 15)}
      {at(CUT.search + 24, 's1391', 0.55, 22)}
      {tapsFinder.map((t) => at(t, 's2568', 0.7, 15))}
      {at(CUT.finder + 14 + 7 * 21 + 6, 's2867', 0.45, 45)}
      {at(CUT.designer + 26, 's2531', 0.6, 110, 30)}
      {at(CUT.assistant + 16 - 6, 's2568', 0.7, 15)}
      {at(CUT.assistant + 26, 's1391', 0.5, 36)}
      {[64, 92, 140].map((t) => at(CUT.assistant + t, 's2358', 0.5, 20))}
      {at(CUT.payUi + 40 - 6, 's2568', 0.7, 15)}
      {at(CUT.deliver + 4, 's2867', 0.4, 45)}
      {at(CUT.end - 4, 's2150', 0.7, 30)}
      {at(CUT.end + 14, 's1492', 0.35, 40)}
    </>
  );
};

// ---------------------------------------------------------------- reel
export const Reel2: React.FC = () => {
  useFonts();
  return (
    <AbsoluteFill style={{ background: C.navy, overflow: 'hidden' }}>
      <KeyFilter />
      <Shot from={0} to={CUT.hook2} inFx="none"><Hook /></Shot>
      <Shot from={CUT.hook2} to={CUT.intro}><Hook2 /></Shot>
      <Shot from={CUT.intro} to={CUT.search}><Intro /></Shot>
      <Shot from={CUT.search} to={CUT.finder} inFx="whip"><Search /></Shot>
      <Shot from={CUT.finder} to={CUT.designer}><Finder /></Shot>
      <Shot from={CUT.designer} to={CUT.assistant} inFx="whip"><Designer /></Shot>
      <Shot from={CUT.assistant} to={CUT.pay}><Assistant /></Shot>
      <Shot from={CUT.pay} to={CUT.payUi} inFx="whip"><Pay /></Shot>
      <Shot from={CUT.payUi} to={CUT.deliver}><PayUi /></Shot>
      <Shot from={CUT.deliver} to={CUT.account} inFx="whip"><Deliver /></Shot>
      <Shot from={CUT.account} to={CUT.road}><Account /></Shot>
      <Shot from={CUT.road} to={CUT.end}><Road /></Shot>
      <Sequence from={CUT.end}><End /></Sequence>
      <Flash at={CUT.intro} /><Flash at={CUT.end} />
      <Grain />
      <Sfx />
    </AbsoluteFill>
  );
};
