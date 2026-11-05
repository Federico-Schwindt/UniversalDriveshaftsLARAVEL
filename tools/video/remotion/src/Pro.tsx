import React from 'react';
import {
  AbsoluteFill, Img, Sequence, staticFile, useCurrentFrame, useVideoConfig, interpolate, spring, Easing,
  continueRender, delayRender,
} from 'remotion';
import { ThreeCanvas } from '@remotion/three';
import { Driveshaft } from './Driveshaft3D';
import { StudioEnv, Lights } from './Studio3D';

// ---------- brand ----------
const NAVY = '#0b1a33', BLUE = '#1246ab', BLUE_L = '#6f9cf0', RED = '#e11b22';
const H = 'UDSPoppins, Poppins, Arial, sans-serif';
const B = 'UDSHanken, "Hanken Grotesk", Arial, sans-serif';
const M = 'UDSMono, "IBM Plex Mono", monospace';
const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
const easeOut = Easing.bezier(0.16, 1, 0.3, 1);
const easeInOut = Easing.bezier(0.65, 0, 0.35, 1);
const lerp = (f: number, a: number, b: number, from: number, to: number, e = easeOut) => interpolate(f, [a, b], [from, to], { ...clamp, easing: e });

const useBrandFonts = () => {
  const [h] = React.useState(() => delayRender('fonts', { timeoutInMilliseconds: 60000 }));
  React.useEffect(() => {
    let done = false; const fin = () => { if (!done) { done = true; continueRender(h); } };
    const cap = setTimeout(fin, 15000);
    Promise.all([
      new FontFace('UDSPoppins', `url(${staticFile('poppins-800.woff2')})`, { weight: '800' }).load(),
      new FontFace('UDSPoppins', `url(${staticFile('poppins-700.woff2')})`, { weight: '700' }).load(),
      new FontFace('UDSHanken', `url(${staticFile('hankengrotesk-var.woff2')})`, { weight: '100 900' }).load(),
      new FontFace('UDSMono', `url(${staticFile('ibmplexmono-600.woff2')})`, { weight: '600' }).load(),
    ]).then((fs) => fs.forEach((x) => document.fonts.add(x))).catch(() => {}).finally(() => { clearTimeout(cap); fin(); });
  }, [h]);
};

// ---------- timeline (30fps) ----------
const T = {
  intro: [0, 135], home: [125, 130], finder: [245, 180], designer: [415, 145], builder: [550, 160],
  assistant: [700, 190], account: [880, 110], outro: [980, 160],
} as const;
export const PRO_TOTAL = 1140;

// ================= background =================
const Background: React.FC = () => {
  const f = useCurrentFrame();
  const drift = (f * 2.2) % 120;
  return (
    <AbsoluteFill style={{ background: 'radial-gradient(110% 65% at 50% 38%, #16305f 0%, #0b1a33 55%, #040a15 100%)' }}>
      {/* perspective blueprint floor */}
      <div style={{ position: 'absolute', left: -1200, right: -1200, top: 1050, height: 1600, transformOrigin: '50% 0%', transform: 'perspective(900px) rotateX(72deg)', opacity: 0.55,
        backgroundImage: 'linear-gradient(rgba(111,156,240,.35) 2px, transparent 2px), linear-gradient(90deg, rgba(111,156,240,.35) 2px, transparent 2px)',
        backgroundSize: '120px 120px', backgroundPosition: `0 ${drift}px`,
        maskImage: 'linear-gradient(to bottom, transparent, black 30%, black 70%, transparent)', WebkitMaskImage: 'linear-gradient(to bottom, transparent, black 25%, black 75%, transparent)' }} />
      {/* fine grid in the sky */}
      <AbsoluteFill style={{ opacity: 0.25, backgroundImage: 'linear-gradient(rgba(111,156,240,.18) 1px, transparent 1px), linear-gradient(90deg, rgba(111,156,240,.18) 1px, transparent 1px)', backgroundSize: '40px 40px',
        maskImage: 'linear-gradient(to bottom, black, transparent 60%)', WebkitMaskImage: 'linear-gradient(to bottom, black, transparent 60%)' }} />
      {/* drifting light leaks */}
      <div style={{ position: 'absolute', width: 900, height: 900, borderRadius: '50%', left: -300 + Math.sin(f / 60) * 120, top: 200 + Math.cos(f / 70) * 90, background: 'radial-gradient(circle, rgba(18,70,171,.55), transparent 65%)', filter: 'blur(40px)' }} />
      <div style={{ position: 'absolute', width: 700, height: 700, borderRadius: '50%', right: -260 + Math.cos(f / 55) * 100, top: 1100 + Math.sin(f / 65) * 80, background: 'radial-gradient(circle, rgba(225,27,34,.28), transparent 65%)', filter: 'blur(50px)' }} />
      {/* particles */}
      {Array.from({ length: 26 }).map((_, i) => {
        const x = (i * 211) % 1080, sp = 0.6 + (i % 5) * 0.25, y = (1920 - ((f * sp * 3 + i * 157) % 2100));
        return <div key={i} style={{ position: 'absolute', left: x, top: y, width: 4 + (i % 3) * 2, height: 4 + (i % 3) * 2, borderRadius: 4, background: i % 4 ? 'rgba(160,190,255,.55)' : 'rgba(255,90,95,.6)', boxShadow: '0 0 12px rgba(111,156,240,.8)' }} />;
      })}
    </AbsoluteFill>
  );
};

// ================= transitions =================
// each scene zooms/blurs in and out; a light sweep + flash crosses the cut
const SceneShell: React.FC<{ len: number; children: React.ReactNode; inStyle?: 'zoom' | 'slide' | 'rise' }> = ({ len, children, inStyle = 'zoom' }) => {
  const f = useCurrentFrame();
  const a = lerp(f, 0, 16, 0, 1), z = lerp(f, len - 14, len, 0, 1, Easing.in(Easing.cubic));
  const scale = inStyle === 'zoom' ? interpolate(a, [0, 1], [1.18, 1]) * interpolate(z, [0, 1], [1, 0.86]) : interpolate(z, [0, 1], [1, 0.9]);
  const tx = inStyle === 'slide' ? interpolate(a, [0, 1], [420, 0]) + interpolate(z, [0, 1], [0, -420]) : 0;
  const ty = inStyle === 'rise' ? interpolate(a, [0, 1], [300, 0]) : 0;
  const blur = interpolate(a, [0, 1], [18, 0]) + interpolate(z, [0, 1], [0, 22]);
  return <AbsoluteFill style={{ opacity: Math.min(a, 1 - z), transform: `translate(${tx}px, ${ty}px) scale(${scale})`, filter: `blur(${blur}px)` }}>{children}</AbsoluteFill>;
};

const Sweep: React.FC<{ at: number }> = ({ at }) => {
  const f = useCurrentFrame();
  const p = interpolate(f, [at - 8, at + 10], [0, 1], clamp);
  if (p <= 0 || p >= 1) return null;
  const flash = interpolate(f, [at - 3, at, at + 6], [0, 0.55, 0], clamp);
  return (
    <AbsoluteFill style={{ pointerEvents: 'none' }}>
      <div style={{ position: 'absolute', top: -400, bottom: -400, width: 420, left: interpolate(p, [0, 1], [-700, 1500]), transform: 'rotate(18deg)',
        background: 'linear-gradient(90deg, transparent, rgba(160,195,255,.0) 10%, rgba(200,220,255,.55) 50%, rgba(160,195,255,0) 90%, transparent)', filter: 'blur(14px)', mixBlendMode: 'screen' }} />
      <AbsoluteFill style={{ background: '#cfe0ff', opacity: flash, mixBlendMode: 'screen' }} />
    </AbsoluteFill>
  );
};

// ================= typography =================
const Kinetic: React.FC<{ lines: { t: string; c?: string; size?: number }[]; at?: number; top: number; left?: number; align?: 'left' | 'center' }> =
  ({ lines, at = 0, top, left = 80, align = 'left' }) => {
    const f = useCurrentFrame();
    let wi = 0;
    return (
      <div style={{ position: 'absolute', top, left: align === 'center' ? 0 : left, right: align === 'center' ? 0 : 60, textAlign: align }}>
        {lines.map((ln, li) => (
          <div key={li} style={{ overflow: 'hidden', lineHeight: 1.02, paddingBottom: 6 }}>
            {ln.t.split(' ').map((w, k) => {
              const d = at + (wi++) * 3;
              const p = lerp(f, d, d + 16, 0, 1);
              return <span key={k} style={{ display: 'inline-block', marginRight: '0.24em', fontFamily: H, fontWeight: 800, fontSize: ln.size ?? 104, letterSpacing: -3, color: ln.c ?? '#fff',
                transform: `translateY(${(1 - p) * 120}%) rotate(${(1 - p) * 6}deg)`, opacity: p, textShadow: '0 12px 40px rgba(0,0,0,.45)' }}>{w}</span>;
            })}
          </div>
        ))}
      </div>
    );
  };

const Tag: React.FC<{ n: string; text: string; at?: number; top: number; left?: number }> = ({ n, text, at = 0, top, left = 80 }) => {
  const f = useCurrentFrame(); const p = lerp(f, at, at + 14, 0, 1);
  return (
    <div style={{ position: 'absolute', top, left, display: 'flex', alignItems: 'center', gap: 16, opacity: p, transform: `translateX(${(1 - p) * -60}px)` }}>
      <span style={{ fontFamily: M, fontWeight: 600, fontSize: 30, color: '#fff', background: RED, padding: '6px 14px', borderRadius: 8, boxShadow: '0 0 30px rgba(225,27,34,.6)' }}>{n}</span>
      <span style={{ fontFamily: M, fontWeight: 600, fontSize: 30, letterSpacing: 7, color: BLUE_L }}>{text}</span>
    </div>
  );
};

const Sub: React.FC<{ text: string; at?: number; top: number; left?: number; w?: number }> = ({ text, at = 0, top, left = 80, w = 900 }) => {
  const f = useCurrentFrame(); const p = lerp(f, at, at + 16, 0, 1);
  return <div style={{ position: 'absolute', top, left, width: w, fontFamily: B, fontWeight: 500, fontSize: 38, lineHeight: 1.35, color: '#c9d5ee', opacity: p, transform: `translateY(${(1 - p) * 24}px)` }}>{text}</div>;
};

const Chip: React.FC<{ text: string; at: number; x: number; y: number; dot?: string }> = ({ text, at, x, y, dot = '#22c55e' }) => {
  const f = useCurrentFrame(); const { fps } = useVideoConfig();
  const s = spring({ frame: f - at, fps, config: { damping: 12, stiffness: 140 } });
  return (
    <div style={{ position: 'absolute', left: x, top: y, transform: `scale(${s}) translateY(${Math.sin((f - at) / 14) * 6}px)`, transformOrigin: 'left center', opacity: Math.min(1, s),
      display: 'flex', alignItems: 'center', gap: 14, padding: '18px 30px', borderRadius: 999, background: 'rgba(255,255,255,.97)',
      boxShadow: '0 24px 60px -18px rgba(0,0,0,.7), 0 0 0 6px rgba(255,255,255,.08)', fontFamily: H, fontWeight: 700, fontSize: 32, color: NAVY, whiteSpace: 'nowrap', zIndex: 5 }}>
      <span style={{ width: 16, height: 16, borderRadius: 8, background: dot, boxShadow: `0 0 14px ${dot}` }} />{text}
    </div>
  );
};

// ================= devices =================
const SW = 520, SH = Math.round(520 * 874 / 402); // phone screen in px (mobile viewport aspect)
const PX = SW / 804;                                  // screenshot px → screen px (screenshots are 2x of 402)

const Phone3D: React.FC<{ children: React.ReactNode; x?: number; y?: number; rx?: number; ry?: number; rz?: number; scale?: number }> =
  ({ children, x = 540, y = 1250, rx = 0, ry = 0, rz = 0, scale = 1 }) => {
    const f = useCurrentFrame();
    const bez = 18;
    return (
      <div style={{ position: 'absolute', left: x, top: y, perspective: 2600, width: 0, height: 0 }}>
        <div style={{ position: 'absolute', left: -(SW / 2 + bez), top: -(SH / 2 + bez), width: SW + bez * 2, height: SH + bez * 2, borderRadius: 78,
          transform: `scale(${scale}) rotateX(${rx}deg) rotateY(${ry}deg) rotateZ(${rz}deg)`, transformStyle: 'preserve-3d',
          background: 'linear-gradient(145deg, #2a3242, #07090f 40%, #1a202b)', padding: bez,
          boxShadow: '0 80px 140px -30px rgba(0,0,0,.85), 0 0 0 2px rgba(150,180,255,.25), inset 0 0 0 3px rgba(255,255,255,.08), 0 0 120px rgba(18,70,171,.35)' }}>
          <div style={{ width: SW, height: SH, borderRadius: 60, overflow: 'hidden', position: 'relative', background: '#fff' }}>
            {children}
            {/* moving glass glare */}
            <div style={{ position: 'absolute', inset: -200, background: 'linear-gradient(115deg, transparent 40%, rgba(255,255,255,.16) 48%, transparent 56%)', transform: `translateX(${((f * 6) % 1600) - 800}px)` }} />
          </div>
          <div style={{ position: 'absolute', top: bez + 16, left: '50%', width: 150, height: 40, marginLeft: -75, borderRadius: 22, background: '#05070c' }} />
        </div>
      </div>
    );
  };

const Browser3D: React.FC<{ children: React.ReactNode; url: string; x?: number; y?: number; w: number; h: number; rx?: number; ry?: number; scale?: number }> =
  ({ children, url, x = 540, y = 1150, w, h, rx = 0, ry = 0, scale = 1 }) => (
    <div style={{ position: 'absolute', left: x, top: y, perspective: 2400 }}>
      <div style={{ position: 'absolute', left: -w / 2, top: -h / 2 - 30, width: w, height: h + 60, borderRadius: 26, overflow: 'hidden', background: '#fff',
        transform: `scale(${scale}) rotateX(${rx}deg) rotateY(${ry}deg)`, boxShadow: '0 90px 160px -40px rgba(0,0,0,.85), 0 0 0 2px rgba(150,180,255,.3), 0 0 140px rgba(18,70,171,.4)' }}>
        <div style={{ height: 60, background: '#eef2f8', display: 'flex', alignItems: 'center', gap: 12, padding: '0 22px', borderBottom: '1px solid #d6dfee' }}>
          {['#ff5f57', '#febc2e', '#28c840'].map((c) => <span key={c} style={{ width: 16, height: 16, borderRadius: 8, background: c }} />)}
          <span style={{ marginLeft: 18, flex: 1, height: 36, borderRadius: 18, background: '#fff', border: '1px solid #d6dfee', display: 'flex', alignItems: 'center', padding: '0 18px', fontFamily: M, fontWeight: 600, fontSize: 20, color: '#5c6879' }}>🔒 {url}</span>
        </div>
        <div style={{ position: 'relative', width: w, height: h, overflow: 'hidden' }}>{children}</div>
      </div>
    </div>
  );

const StatusBar: React.FC = () => (
  <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 56, background: '#05070c', color: '#fff', zIndex: 3, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 42px', fontFamily: H, fontWeight: 700, fontSize: 22 }}>
    <span>9:41</span><span style={{ width: 24, height: 12, border: '2px solid #fff', borderRadius: 3 }} />
  </div>
);

// a tall screenshot scrolled smoothly inside the phone
const ScrollShot: React.FC<{ src: string; from: number; to: number; a: number; b: number }> = ({ src, from, to, a, b }) => {
  const f = useCurrentFrame();
  const y = interpolate(f, [a, b], [from, to], { ...clamp, easing: easeInOut });
  return (<><StatusBar /><Img src={staticFile(src)} style={{ position: 'absolute', top: 56 - y * PX * 2, left: 0, width: SW }} /></>);
};

// step-by-step screen states with taps
type Step = { src: string; at: number; pan?: number; tap?: { x: number; y: number; at: number } };
const Steps: React.FC<{ steps: Step[]; status?: boolean }> = ({ steps, status = true }) => {
  const f = useCurrentFrame();
  return (
    <>
      {status && <StatusBar />}
      {steps.map((s, i) => {
        const next = steps[i + 1];
        const op = interpolate(f, [s.at, s.at + 8], [i === 0 ? 1 : 0, 1], clamp) * (next ? interpolate(f, [next.at, next.at + 8], [1, 0], clamp) : 1);
        if (op <= 0) return null;
        const panPx = (s.pan ?? 0) * PX * 2;
        const slide = i === 0 ? 0 : interpolate(f, [s.at, s.at + 12], [60, 0], { ...clamp, easing: easeOut });
        return <Img key={i} src={staticFile(s.src)} style={{ position: 'absolute', top: (status ? 56 : 0) - panPx, left: slide, width: SW, opacity: op }} />;
      })}
      {steps.map((s, i) => s.tap && <Tap key={'t' + i} x={s.tap.x * PX * 2} y={(status ? 56 : 0) + (s.tap.y - (s.pan ?? 0)) * PX * 2} at={s.tap.at} />)}
    </>
  );
};

const Tap: React.FC<{ x: number; y: number; at: number }> = ({ x, y, at }) => {
  const f = useCurrentFrame();
  const p = interpolate(f, [at, at + 18], [0, 1], clamp);
  if (f < at - 10 || p >= 1) return null;
  const finger = interpolate(f, [at - 10, at], [0, 1], clamp);
  return (
    <>
      <div style={{ position: 'absolute', left: x - 34, top: y - 34, width: 68, height: 68, borderRadius: 34, background: 'rgba(18,70,171,.35)', border: '3px solid rgba(255,255,255,.9)', opacity: finger * (1 - p), transform: `scale(${1 - p * 0.2})`, zIndex: 4 }} />
      <div style={{ position: 'absolute', left: x - 70, top: y - 70, width: 140, height: 140, borderRadius: 70, border: '4px solid rgba(18,70,171,.8)', opacity: 1 - p, transform: `scale(${0.4 + p})`, zIndex: 4 }} />
    </>
  );
};

// ================= 3D =================
const ShaftScene: React.FC<{ mode: 'intro' | 'outro' }> = ({ mode }) => {
  const f = useCurrentFrame(); const { width, height } = useVideoConfig();
  const fly = mode === 'intro' ? lerp(f, 0, 70, 0, 1) : 1;
  const z = mode === 'intro' ? interpolate(fly, [0, 1], [-260, 0]) : 0;
  // camera z=118, fov 32 → ~28px per unit: the intro shaft sits between the title (top) and the logo (y≈1500),
  // the outro shaft lies across the frame behind the logo
  const yRot = mode === 'intro' ? interpolate(f, [0, 135], [-1.8, 0.5], { ...clamp, easing: easeOut }) : -0.18 + Math.sin(f / 40) * 0.12;
  const tilt = mode === 'intro' ? interpolate(f, [0, 135], [0.35, 1.0]) : 0.06;
  const pos: [number, number, number] = mode === 'intro' ? [0, -2.2, z] : [0, -21.5, -6];
  return (
    <ThreeCanvas width={width} height={height} camera={{ position: [0, 0, 118], fov: 32 }}>
      <StudioEnv />
      <Lights />
      <group position={pos} rotation={[0.3, yRot, tilt]} scale={mode === 'outro' ? 0.62 : 0.66}>
        <Driveshaft spin={f * 0.16} />
      </group>
    </ThreeCanvas>
  );
};

// ================= scenes =================
const Intro: React.FC = () => {
  const f = useCurrentFrame(); const { fps } = useVideoConfig();
  const logo = spring({ frame: f - 82, fps, config: { damping: 11, stiffness: 160 } });
  const streak = interpolate(f, [0, 14], [0, 1], clamp);
  const red = interpolate(f, [82, 86, 100], [0, 0.5, 0], clamp);
  return (
    <AbsoluteFill>
      {/* opening light streak */}
      <div style={{ position: 'absolute', top: 955, left: 0, height: 6, width: 1080 * streak, background: 'linear-gradient(90deg, transparent, #fff, #6f9cf0)', boxShadow: '0 0 40px #6f9cf0', opacity: interpolate(f, [12, 26], [1, 0], clamp) }} />
      <AbsoluteFill style={{ opacity: lerp(f, 6, 30, 0, 1) }}><ShaftScene mode="intro" /></AbsoluteFill>
      <Kinetic top={170} at={30} lines={[{ t: 'THE NEW', c: BLUE_L, size: 90 }, { t: 'UNIVERSAL', size: 128 }, { t: 'DRIVESHAFTS', size: 128 }]} />
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1500, textAlign: 'center', transform: `scale(${0.6 + logo * 0.4})`, opacity: Math.min(1, logo) }}>
        <div style={{ display: 'inline-block', background: '#fff', padding: '26px 46px', borderRadius: 30, boxShadow: '0 30px 80px -20px rgba(0,0,0,.8), 0 0 80px rgba(111,156,240,.5)' }}>
          <Img src={staticFile('logo.webp')} style={{ width: 560, display: 'block' }} />
        </div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1690, textAlign: 'center', fontFamily: H, fontWeight: 800, fontSize: 64, color: '#fff', letterSpacing: 6, opacity: lerp(f, 96, 110, 0, 1) }}>
        IS <span style={{ color: RED }}>LIVE.</span>
      </div>
      <AbsoluteFill style={{ background: RED, opacity: red, mixBlendMode: 'screen' }} />
    </AbsoluteFill>
  );
};

const Counter: React.FC<{ to: number; at: number; suffix?: string; label: string; x: number; y: number; prefix?: string; text?: string }> = ({ to, at, suffix = '', label, x, y, prefix = '', text }) => {
  const f = useCurrentFrame();
  const p = lerp(f, at, at + 30, 0, 1);
  const v = Math.round(to * p).toLocaleString('en-US');
  return (
    <div style={{ position: 'absolute', left: x, top: y, opacity: Math.min(1, p * 3), transform: `translateY(${(1 - p) * 30}px)` }}>
      <div style={{ fontFamily: H, fontWeight: 800, fontSize: 66, color: '#fff', letterSpacing: -2 }}>{text ?? `${prefix}${v}${suffix}`}</div>
      <div style={{ fontFamily: M, fontWeight: 600, fontSize: 22, letterSpacing: 4, color: BLUE_L, marginTop: 4 }}>{label}</div>
    </div>
  );
};

const Home: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <SceneShell len={T.home[1]} inStyle="zoom">
      <Tag n="01" text="THE NEW SITE" top={150} />
      <Kinetic top={215} at={4} lines={[{ t: 'Every part.' }, { t: 'On the shelf', }, { t: 'today.', c: RED }]} />
      <Counter to={2600} suffix="+" label="PARTS IN STOCK" x={80} y={600} at={26} />
      <Counter to={0} text="1310–1810" label="SERIES" x={80} y={720} at={34} />
      <Counter to={0} text="SAME DAY" label="SHIPPING BEFORE 2PM ET" x={80} y={840} at={42} />
      <Phone3D x={720} y={1290} ry={interpolate(f, [0, 130], [-30, -16])} rx={8} rz={3} scale={0.92}>
        <ScrollShot src="home-tall.png" from={0} to={1250} a={18} b={120} />
      </Phone3D>
    </SceneShell>
  );
};

const Finder: React.FC = () => {
  const f = useCurrentFrame();
  const steps: Step[] = [
    { src: 'find-1.png', at: 0, pan: 600, tap: { x: 295, y: 1114, at: 40 } },
    { src: 'find-2.png', at: 52, pan: 560, tap: { x: 107, y: 1014, at: 74 } },
    { src: 'find-3.png', at: 86, pan: 490, tap: { x: 295, y: 944, at: 108 } },
    { src: 'find-4.png', at: 120, pan: 380, tap: { x: 107, y: 608, at: 140 } },
    { src: 'find-4.png', at: 150, pan: 820 },
  ];
  const crumbs = ['FORD', '2015', 'F-150', '4WD'];
  return (
    <SceneShell len={T.finder[1]} inStyle="slide">
      <Tag n="02" text="COMPLETE DRIVESHAFTS" top={150} />
      <Kinetic top={215} at={4} lines={[{ t: 'Find yours' }, { t: 'by vehicle.', c: RED }]} />
      {/* the picked vehicle builds up as chips */}
      <div style={{ position: 'absolute', top: 470, left: 80, display: 'flex', gap: 14 }}>
        {crumbs.map((c, i) => {
          const at = [44, 78, 112, 144][i]; const p = lerp(f, at, at + 12, 0, 1);
          return <span key={c} style={{ opacity: p, transform: `scale(${0.6 + p * 0.4})`, fontFamily: M, fontWeight: 600, fontSize: 30, color: '#fff', padding: '12px 22px', borderRadius: 12, background: i === 3 ? RED : 'rgba(255,255,255,.12)', border: '1px solid rgba(255,255,255,.25)' }}>{c}</span>;
        })}
      </div>
      <Phone3D x={540} y={1260} ry={interpolate(f, [0, 180], [10, -6])} rx={6} scale={0.95}>
        <Steps steps={steps} />
      </Phone3D>
      <Chip text="Exact fit · $456.28" at={152} x={520} y={1440} dot="#22c55e" />
    </SceneShell>
  );
};

const Designer: React.FC = () => {
  const f = useCurrentFrame();
  const W = 960, Hh = Math.round(960 * 1307 / 1740);
  const states = [['design-1.png', 0], ['design-2.png', 40], ['design-3.png', 70], ['design-4.png', 100]] as const;
  const typed = f < 40 ? '52.25' : f < 70 ? '48.5' : f < 100 ? '3.5' : '';
  const k = f < 40 ? 'C1' : f < 70 ? 'W1' : 'T1';
  const pill = lerp(f, 16, 26, 0, 1) * (1 - lerp(f, 104, 112, 0, 1));
  const typedNow = typed.slice(0, Math.max(0, Math.floor(((f - (f < 40 ? 18 : f < 70 ? 46 : 76)) / 4))));
  return (
    <SceneShell len={T.designer[1]} inStyle="zoom">
      <Tag n="03" text="DRIVESHAFT DESIGNER" top={150} />
      <Kinetic top={215} at={4} lines={[{ t: 'Draw it.' }, { t: 'Dimension it.', c: RED }]} />
      <Sub top={470} at={14} text="Tap a dimension, type it in inches, and send the sheet straight to our shop." />
      <Browser3D url="universaldriveshafts.com/driveshaft-designer" x={540} y={1170} w={W} h={Hh} rx={interpolate(f, [0, 145], [16, 8])} ry={interpolate(f, [0, 145], [-14, 4])} scale={0.98}>
        {states.map(([src, at], i) => {
          const nx = states[i + 1];
          const op = interpolate(f, [at, at + 6], [i === 0 ? 1 : 0, 1], clamp) * (nx ? interpolate(f, [nx[1], nx[1] + 6], [1, 0], clamp) : 1);
          return op > 0 ? <Img key={src} src={staticFile(src)} style={{ position: 'absolute', inset: 0, width: W, opacity: op }} /> : null;
        })}
      </Browser3D>
      {/* typing pill */}
      <div style={{ position: 'absolute', left: 300, top: 1620, opacity: pill, transform: `translateY(${(1 - pill) * 30}px)`, display: 'flex', alignItems: 'center', gap: 18, padding: '20px 34px', borderRadius: 20, background: NAVY, border: `2px solid ${BLUE_L}`, boxShadow: '0 20px 60px -10px rgba(0,0,0,.8), 0 0 40px rgba(111,156,240,.4)' }}>
        <span style={{ fontFamily: M, fontWeight: 600, fontSize: 34, color: BLUE_L }}>{k}</span>
        <span style={{ fontFamily: M, fontWeight: 600, fontSize: 44, color: '#fff', minWidth: 150 }}>{typedNow}<span style={{ opacity: Math.floor(f / 8) % 2 ? 1 : 0 }}>|</span></span>
        <span style={{ fontFamily: M, fontWeight: 600, fontSize: 26, color: '#8a98b5' }}>in ↵</span>
      </div>
    </SceneShell>
  );
};

const Builder: React.FC = () => {
  const f = useCurrentFrame();
  const W = 980;
  const states = [['build-1.png', 0, 1010], ['build-2.png', 34, 1079], ['build-3.png', 66, 1359], ['build-4.png', 96, 1359], ['build-5.png', 126, 2261]] as const;
  const price = Math.round(interpolate(f, [96, 120], [175, 310], clamp));
  return (
    <SceneShell len={T.builder[1]} inStyle="rise">
      <Tag n="04" text="ONLINE BUILDER" top={150} />
      <Kinetic top={215} at={4} lines={[{ t: 'Build it online.' , size: 96 }, { t: 'See the price.', c: RED, size: 96 }]} />
      <Browser3D url="universaldriveshafts.com/online-builder" x={540} y={1130} w={W} h={760} rx={interpolate(f, [0, 160], [10, 4])} ry={interpolate(f, [0, 160], [12, -8])} scale={0.98}>
        {states.map(([src, at, h], i) => {
          const nx = states[i + 1];
          const op = interpolate(f, [at, at + 8], [i === 0 ? 1 : 0, 1], clamp) * (nx ? interpolate(f, [nx[1], nx[1] + 8], [1, 0], clamp) : 1);
          const drift = i === 4 ? interpolate(f, [at, 160], [0, 260], clamp) : 0;
          return op > 0 ? <Img key={src} src={staticFile(src)} style={{ position: 'absolute', top: -drift, left: 0, width: W, opacity: op }} /> : null;
        })}
      </Browser3D>
      <div style={{ position: 'absolute', left: 80, top: 1580, display: 'flex', alignItems: 'baseline', gap: 18, opacity: lerp(f, 88, 100, 0, 1) }}>
        <span style={{ fontFamily: M, fontWeight: 600, fontSize: 28, letterSpacing: 4, color: BLUE_L }}>LIVE PRICE</span>
        <span style={{ fontFamily: H, fontWeight: 800, fontSize: 96, color: '#fff', letterSpacing: -2 }}>${price}.00</span>
      </div>
    </SceneShell>
  );
};

const Assistant: React.FC = () => {
  const f = useCurrentFrame();
  const q = 'I need a 1350 u-joint for my lifted Jeep.';
  const typed = q.slice(0, Math.max(0, Math.floor((f - 26) * 1.4)));
  const steps: Step[] = [
    { src: 'chat-0.png', at: 0 }, { src: 'chat-1.png', at: 64 }, { src: 'chat-2.png', at: 92 }, { src: 'chat-3.png', at: 128 }, { src: 'chat-4.png', at: 150 },
  ];
  const typing = f > 22 && f < 64;
  return (
    <SceneShell len={T.assistant[1]} inStyle="zoom">
      <Tag n="05" text="UDS ASSISTANT · AI" top={150} />
      <Kinetic top={215} at={4} lines={[{ t: 'Ask UDS.', c: RED }, { t: 'The right part,' }, { t: 'in seconds.' }]} />
      {/* AI aura */}
      <div style={{ position: 'absolute', left: 540 - 520, top: 1280 - 520, width: 1040, height: 1040, borderRadius: '50%', background: 'conic-gradient(from ' + f * 3 + 'deg, rgba(18,70,171,.0), rgba(111,156,240,.55), rgba(225,27,34,.35), rgba(18,70,171,.0))', filter: 'blur(70px)', opacity: 0.8 }} />
      <Phone3D x={540} y={1300} ry={interpolate(f, [0, 190], [-12, 8])} rx={5} scale={0.95}>
        <Steps steps={steps} status />
        {typing && (
          <div style={{ position: 'absolute', left: 64 * PX * 2 / 2, right: 64 * PX * 2 / 2, bottom: 66 * PX * 2 / 2 + 2, height: 64, display: 'flex', alignItems: 'center', padding: '0 58px', background: '#fff', fontFamily: B, fontSize: 22, color: '#141b28', zIndex: 3 }}>
            {typed}<span style={{ opacity: Math.floor(f / 7) % 2 ? 1 : 0 }}>|</span>
          </div>
        )}
      </Phone3D>
      <Chip text="Real parts, live stock" at={96} x={60} y={1500} dot="#22c55e" />
      <Chip text="Adds to your cart" at={154} x={560} y={1600} dot="#6f9cf0" />
    </SceneShell>
  );
};

const Account: React.FC = () => {
  const f = useCurrentFrame();
  return (
    <SceneShell len={T.account[1]} inStyle="slide">
      <Tag n="06" text="MY ACCOUNT" top={150} />
      <Kinetic top={215} at={4} lines={[{ t: 'Your orders.' }, { t: 'Tracked.', c: RED }]} />
      <Sub top={470} at={12} text="Live tracking, one-tap reorder, saved addresses and easy returns." />
      <Phone3D x={540} y={1290} ry={interpolate(f, [0, 110], [14, -4])} rx={6} scale={0.95}>
        <ScrollShot src="account-tall.png" from={0} to={520} a={14} b={100} />
      </Phone3D>
    </SceneShell>
  );
};

const Outro: React.FC = () => {
  const f = useCurrentFrame(); const { fps } = useVideoConfig();
  const s = spring({ frame: f - 10, fps, config: { damping: 13, stiffness: 120 } });
  const u = spring({ frame: f - 34, fps, config: { damping: 16, stiffness: 120 } });
  return (
    <AbsoluteFill style={{ opacity: lerp(f, 0, 14, 0, 1) }}>
      <AbsoluteFill style={{ opacity: 0.9 }}><ShaftScene mode="outro" /></AbsoluteFill>
      <AbsoluteFill style={{ background: 'radial-gradient(60% 40% at 50% 55%, rgba(4,10,21,.0), rgba(4,10,21,.65))' }} />
      <div style={{ position: 'absolute', left: 0, right: 0, top: 760, textAlign: 'center', transform: `scale(${0.7 + s * 0.3})`, opacity: Math.min(1, s) }}>
        <div style={{ display: 'inline-block', background: '#fff', padding: '34px 58px', borderRadius: 34, boxShadow: '0 40px 100px -30px rgba(0,0,0,.9), 0 0 120px rgba(111,156,240,.55)' }}>
          <Img src={staticFile('logo.webp')} style={{ width: 720, display: 'block' }} />
        </div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1060, textAlign: 'center', fontFamily: M, fontWeight: 600, fontSize: 30, letterSpacing: 8, color: BLUE_L, opacity: lerp(f, 26, 40, 0, 1) }}>BUILT & BALANCED IN THE USA</div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1130, textAlign: 'center', fontFamily: H, fontWeight: 800, fontSize: 76, letterSpacing: -1.5, color: '#fff', opacity: Math.min(1, u), transform: `translateY(${(1 - u) * 40}px)` }}>universaldriveshafts.com</div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1270, textAlign: 'center', opacity: lerp(f, 52, 66, 0, 1) }}>
        <span style={{ display: 'inline-block', fontFamily: H, fontWeight: 700, fontSize: 44, color: '#fff', background: RED, padding: '26px 64px', borderRadius: 999, boxShadow: `0 0 ${30 + Math.sin(f / 6) * 15}px rgba(225,27,34,.9)` }}>LINK IN BIO →</span>
      </div>
    </AbsoluteFill>
  );
};

export const ProReel: React.FC = () => {
  useBrandFonts();
  const seq = (k: keyof typeof T, C: React.FC) => <Sequence key={k} from={T[k][0]} durationInFrames={T[k][1]}><C /></Sequence>;
  return (
    <AbsoluteFill style={{ background: NAVY, overflow: 'hidden' }}>
      <Background />
      {seq('intro', Intro)}{seq('home', Home)}{seq('finder', Finder)}{seq('designer', Designer)}
      {seq('builder', Builder)}{seq('assistant', Assistant)}{seq('account', Account)}{seq('outro', Outro)}
      {[130, 250, 420, 555, 705, 885, 985].map((at) => <Sweep key={at} at={at} />)}
    </AbsoluteFill>
  );
};
