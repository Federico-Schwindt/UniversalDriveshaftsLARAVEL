import React, { useLayoutEffect, useRef } from 'react';
import { AbsoluteFill, Easing, Img, interpolate, spring, staticFile, useCurrentFrame, getInputProps } from 'remotion';
import { FONT } from '../launch/theme';
import { W, H, T2 } from './timeline';

// ------------------------------------------------------------------ easing (nothing linear anywhere)
export const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
export const E = {
  out: Easing.bezier(0.16, 1, 0.3, 1), in: Easing.bezier(0.55, 0, 0.75, 0.2), io: Easing.bezier(0.65, 0, 0.35, 1),
  over: Easing.bezier(0.34, 1.56, 0.64, 1), soft: Easing.bezier(0.45, 0, 0.2, 1), snap: Easing.bezier(0.2, 0.9, 0.1, 1),
};
export const P = (f: number, a: number, b: number, e = E.io) => interpolate(f, [a, b], [0, 1], { ...clamp, easing: e });
export const lerp = (a: number, b: number, t: number) => a + (b - a) * t;
export const kf = (f: number, keys: [number, number[]][], e = E.io) => {
  if (f <= keys[0][0]) return keys[0][1];
  for (let i = 0; i < keys.length - 1; i++) { const [f0, v0] = keys[i], [f1, v1] = keys[i + 1]; if (f <= f1) { const t = e(Math.max(0, Math.min(1, (f - f0) / (f1 - f0)))); return v0.map((x, j) => lerp(x, v1[j], t)); } }
  return keys[keys.length - 1][1];
};
export const drift = (f: number, amp = 3, per = 180, ph = 0) => Math.sin((f / per) * Math.PI * 2 + ph) * amp;
export const useF = (offset = T2) => useCurrentFrame() - offset; // scene-local frame: 0 = the beat the scene starts on
export const S = (n: string) => staticFile('v3/shots/' + n + '.png');
export const C = { navy: '#071226', steel: '#0b1a33', blue: '#1246ab', blue6: '#1657cf', blueHi: '#3b7bff', red: '#e23a38', white: '#fff', ice: '#cfe0ff' };
// deterministic pseudo-random
export const rnd = (i: number, k = 0) => { const x = Math.sin(i * 127.1 + k * 311.7) * 43758.5453; return x - Math.floor(x); };

// ------------------------------------------------------------------ background: dark gradient + drifting brand blobs + animated grain
export const Background: React.FC<{ pulses?: number[]; blue?: boolean }> = ({ pulses = [], blue }) => {
  const f = useCurrentFrame(); const ip = getInputProps() as any;
  let pulse = 0; pulses.forEach((p) => { if (f >= p && f < p + 40) pulse = Math.max(pulse, 1 - P(f, p, p + 40, E.out)); });
  const blob = (i: number, col: string, size: number, a: number) => {
    const x = 50 + Math.sin(f / (520 + i * 90) + i) * 28, y = 50 + Math.cos(f / (610 + i * 70) + i * 2) * 24;
    return <div key={i} style={{ position: 'absolute', left: `${x}%`, top: `${y}%`, width: size, height: size, marginLeft: -size / 2, marginTop: -size / 2, borderRadius: '50%', background: `radial-gradient(circle, ${col} 0%, rgba(0,0,0,0) 70%)`, opacity: a + pulse * 0.12, transform: `scale(${1 + pulse * 0.25})` }} />;
  };
  return (
    <AbsoluteFill style={{ background: blue ? 'linear-gradient(160deg,#0c1f44 0%,#07122a 55%,#050b1a 100%)' : 'linear-gradient(160deg,#0b1a33 0%,#071226 55%,#040914 100%)', overflow: 'hidden' }}>
      {blob(0, 'rgba(37,99,235,.9)', 1500, 0.11)}{blob(1, 'rgba(226,58,56,.85)', 1100, 0.08)}{blob(2, 'rgba(59,123,255,.9)', 900, 0.1)}{blob(3, 'rgba(120,170,255,.8)', 700, 0.07)}
      <AbsoluteFill style={{ background: `radial-gradient(120% 90% at 50% 50%, rgba(255,255,255,${pulse * 0.22}) 0%, rgba(0,0,0,0) 55%, rgba(0,0,0,.5) 100%)` }} />
      {!ip.nograin && <Grain f={f} />}
    </AbsoluteFill>
  );
};

// animated film grain: a 256px noise tile re-seeded every frame, tiled across the canvas (far cheaper than feTurbulence)
const Grain: React.FC<{ f: number }> = ({ f }) => {
  const ref = useRef<HTMLCanvasElement>(null);
  useLayoutEffect(() => {
    const c = ref.current!, ctx = c.getContext('2d')!, img = ctx.createImageData(256, 256), d = img.data; let s = (f * 7919 + 13) | 0;
    for (let i = 0; i < d.length; i += 4) { s = (s * 1103515245 + 12345) & 0x7fffffff; const v = 110 + (s >> 16) % 90; d[i] = d[i + 1] = d[i + 2] = v; d[i + 3] = 255; }
    ctx.putImageData(img, 0, 0);
  }, [f]);
  return <><canvas ref={ref} width={256} height={256} style={{ display: 'none' }} /><div style={{ position: 'absolute', inset: 0, opacity: 0.085, mixBlendMode: 'overlay', backgroundImage: `url(${ref.current?.toDataURL?.() ?? ''})`, backgroundPosition: `${(f * 37) % 256}px ${(f * 53) % 256}px` }} /></>;
};

// ------------------------------------------------------------------ volumetric dust: 260 particles, slow drift, the near ones soft and blurred
export const Particles: React.FC<{ n?: number; opacity?: number; speed?: number }> = ({ n = 260, opacity = 1, speed = 1 }) => {
  const f = useCurrentFrame(); const ref = useRef<HTMLCanvasElement>(null);
  useLayoutEffect(() => {
    const c = ref.current!, ctx = c.getContext('2d')!; ctx.clearRect(0, 0, W, H);
    for (let i = 0; i < n; i++) {
      const depth = rnd(i, 1); // 0 far .. 1 near
      const x = ((rnd(i, 2) * W + Math.sin(f / (240 + depth * 200) + i) * (30 + depth * 60) + f * speed * (0.08 + depth * 0.35)) % (W + 80)) - 40;
      const y = ((rnd(i, 3) * H + Math.cos(f / (300 + depth * 160) + i * 1.7) * (20 + depth * 40) - f * speed * (0.04 + depth * 0.12) + 4000) % (H + 80)) - 40;
      const r = 0.8 + depth * 3.6, a = (0.12 + depth * 0.5) * (0.6 + 0.4 * Math.sin(f / 37 + i)) * opacity;
      if (depth > 0.72) { const g = ctx.createRadialGradient(x, y, 0, x, y, r * 3); g.addColorStop(0, `rgba(220,232,255,${a})`); g.addColorStop(1, 'rgba(220,232,255,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, r * 3, 0, Math.PI * 2); ctx.fill(); }
      else { ctx.fillStyle = `rgba(220,232,255,${a})`; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill(); }
    }
  }, [f, n, opacity, speed]);
  return <canvas ref={ref} width={W} height={H} style={{ position: 'absolute', inset: 0, pointerEvents: 'none' }} />;
};

// ------------------------------------------------------------------ blueprint grid: 1px lines, 10% white, pulses (rings + brighter lines) at given frames
export const Grid: React.FC<{ show: [number, number]; pulses?: number[]; origin?: [number, number] }> = ({ show, pulses = [], origin = [W / 2, H / 2] }) => {
  const f = useCurrentFrame(); const o = P(f, show[0], show[0] + 40, E.out) * (1 - P(f, show[1] - 20, show[1], E.in)); if (o <= 0) return null;
  let p = 0, ring = 0; pulses.forEach((q) => { if (f >= q && f < q + 36) { p = Math.max(p, 1 - P(f, q, q + 36, E.out)); ring = Math.max(ring, P(f, q, q + 36, E.out)); } });
  const lines: React.ReactNode[] = []; const step = 64;
  for (let x = (W / 2) % step; x < W; x += step) lines.push(<line key={'v' + x} x1={x} y1={0} x2={x} y2={H} />);
  for (let y = (H / 2) % step; y < H; y += step) lines.push(<line key={'h' + y} x1={0} y1={y} x2={W} y2={y} />);
  return (
    <svg width={W} height={H} style={{ position: 'absolute', inset: 0, opacity: o, transform: `translate(${drift(f, 6, 400)}px,${drift(f, 4, 330, 1)}px)` }}>
      <g stroke="#fff" strokeOpacity={0.1 + p * 0.22} strokeWidth={1}>{lines}</g>
      <g stroke="#9cc0ff" strokeOpacity={0.045} strokeWidth={1}>{[...Array(8)].map((_, i) => <line key={i} x1={0} y1={H / 2 + (i - 4) * 16} x2={W} y2={H / 2 + (i - 4) * 16} />)}</g>
      {ring > 0 && <circle cx={origin[0]} cy={origin[1]} r={60 + ring * 900} fill="none" stroke="#9cc0ff" strokeOpacity={(1 - ring) * 0.5} strokeWidth={2} />}
    </svg>
  );
};

// ------------------------------------------------------------------ kinetic title: words slam in (scale 1.3→1, blur→0), 2-frame stagger
export const Kinetic: React.FC<{ lines: string[]; at: number; out?: number; size?: number; y?: number; sub?: string; align?: 'center' | 'left'; x?: number }> = ({ lines, at, out = 1e9, size = 112, y = H / 2, sub, align = 'center', x = W / 2 }) => {
  const f = useCurrentFrame(); if (f < at - 2 || f > out + 14) return null;
  const o = 1 - P(f, out, out + 12, E.in); let k = 0;
  return (
    <div style={{ position: 'absolute', left: align === 'center' ? 0 : x, right: align === 'center' ? 0 : undefined, top: y, transform: `translateY(-50%) translateY(${(1 - o) * -40}px) scale(${1 + (1 - o) * 0.08})`, opacity: o, textAlign: align, fontFamily: FONT.h, fontWeight: 800, fontSize: size, lineHeight: 1.02, letterSpacing: -size * 0.03, color: '#fff' }}>
      {lines.map((ln, li) => (
        <div key={li}>
          {ln.split(' ').map((w, wi) => {
            const acc = w.startsWith('*'); const txt = acc ? w.slice(1) : w; const d = at + k++ * 2;
            const p = P(f, d, d + 22, E.snap);
            return <span key={wi} style={{ display: 'inline-block', marginRight: '.24em', transform: `scale(${1.3 - 0.3 * p}) translateY(${(1 - p) * 20}px)`, opacity: Math.min(1, p * 1.6), filter: `blur(${(1 - p) * 14}px)`, color: acc ? C.red : '#fff', textShadow: acc ? '0 0 50px rgba(226,58,56,.5), 0 10px 40px rgba(0,0,0,.5)' : '0 10px 40px rgba(0,0,0,.55)' }}>{txt}</span>;
          })}
        </div>
      ))}
      {sub && <div style={{ marginTop: 18, fontFamily: FONT.m, fontWeight: 600, fontSize: size * 0.2, letterSpacing: '.3em', textTransform: 'uppercase', color: C.ice, opacity: P(f, at + k * 2 + 8, at + k * 2 + 30, E.out), transform: `translateY(${(1 - P(f, at + k * 2 + 8, at + k * 2 + 30, E.out)) * 16}px)` }}>{sub}</div>}
    </div>
  );
};

// impact flash + shockwave ring for title cards / big moments (canvas coords)
export const Impact: React.FC<{ at: number; x?: number; y?: number; strength?: number }> = ({ at, x = W / 2, y = H / 2, strength = 1 }) => {
  const f = useCurrentFrame(); if (f < at || f > at + 50) return null; const p = P(f, at, at + 50, E.out);
  return (
    <AbsoluteFill style={{ pointerEvents: 'none' }}>
      <div style={{ position: 'absolute', inset: 0, background: '#fff', opacity: (1 - p) * 0.28 * strength }} />
      <div style={{ position: 'absolute', left: x, top: y, width: 10 + p * 2600, height: 10 + p * 2600, transform: 'translate(-50%,-50%)', borderRadius: '50%', border: `${2 + (1 - p) * 4}px solid rgba(255,255,255,${(1 - p) * 0.7})`, boxShadow: `0 0 ${40 * (1 - p)}px rgba(160,200,255,${(1 - p) * 0.6})` }} />
    </AbsoluteFill>
  );
};

// ------------------------------------------------------------------ light streaks: horizontal lines racing across during a whip / cube transition
export const Streaks: React.FC<{ at: number; dir?: 1 | -1; n?: number; dur?: number }> = ({ at, dir = -1, n = 14, dur = 18 }) => {
  const f = useCurrentFrame(); if (f < at - 2 || f > at + dur + 6) return null;
  return (
    <AbsoluteFill style={{ pointerEvents: 'none', overflow: 'hidden' }}>
      {[...Array(n)].map((_, i) => {
        const y = rnd(i, 7) * H, w = 300 + rnd(i, 8) * 700, d = rnd(i, 9) * 6; const p = P(f, at + d, at + d + dur, E.io);
        const x = dir < 0 ? W + 200 - p * (W + w + 400) : -w - 200 + p * (W + w + 400);
        return <div key={i} style={{ position: 'absolute', left: x, top: y, width: w, height: 1 + rnd(i, 10) * 3, background: `linear-gradient(90deg, rgba(255,255,255,0), rgba(190,215,255,${0.5 + rnd(i, 11) * 0.5}), rgba(255,255,255,0))`, opacity: Math.sin(p * Math.PI) }} />;
      })}
    </AbsoluteFill>
  );
};

// ------------------------------------------------------------------ chromatic aberration flash (three channel copies pulled apart)
export const Chroma: React.FC<{ amount: number; children: React.ReactNode }> = ({ amount, children }) => {
  if (amount <= 0.001) return <>{children}</>;
  const s = amount * 26;
  return (
    <AbsoluteFill>
      <svg width="0" height="0" style={{ position: 'absolute' }}>
        <filter id="chR"><feColorMatrix type="matrix" values="1 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 1 0" /></filter>
        <filter id="chG"><feColorMatrix type="matrix" values="0 0 0 0 0  0 1 0 0 0  0 0 0 0 0  0 0 0 1 0" /></filter>
        <filter id="chB"><feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 1 0 0  0 0 0 1 0" /></filter>
      </svg>
      <AbsoluteFill style={{ filter: 'url(#chR)', transform: `translate(${-s}px,0) scale(${1 + amount * 0.01})`, mixBlendMode: 'screen' }}>{children}</AbsoluteFill>
      <AbsoluteFill style={{ filter: 'url(#chG)', mixBlendMode: 'screen' }}>{children}</AbsoluteFill>
      <AbsoluteFill style={{ filter: 'url(#chB)', transform: `translate(${s}px,0) scale(${1 - amount * 0.01})`, mixBlendMode: 'screen' }}>{children}</AbsoluteFill>
      <AbsoluteFill style={{ background: '#fff', opacity: amount * 0.5 }} />
    </AbsoluteFill>
  );
};

// directional motion-blur filters (SVG gaussian with x-only deviation), used by whips and cube spins
export const BlurDefs: React.FC = () => (
  <svg width="0" height="0" style={{ position: 'absolute' }}>
    {[6, 12, 20, 32, 48].map((d) => <filter key={d} id={'mbx' + d} x="-20%" y="0" width="140%" height="100%"><feGaussianBlur stdDeviation={`${d} 0`} /></filter>)}
    {[6, 12, 20].map((d) => <filter key={d} id={'mby' + d} x="0" y="-20%" width="100%" height="140%"><feGaussianBlur stdDeviation={`0 ${d}`} /></filter>)}
  </svg>
);
export const mbx = (speed: number) => (speed < 3 ? 'none' : `url(#mbx${speed < 9 ? 6 : speed < 16 ? 12 : speed < 26 ? 20 : speed < 40 ? 32 : 48})`);

// ------------------------------------------------------------------ cursor with 6-ghost trail, glow, click ripple; shake() gives the 2–3 px screen shake
export type CurKey = { f: number; x: number; y: number; click?: boolean; hold?: number };
const cursorAt = (keys: CurKey[], f: number) => {
  if (f <= keys[0].f) return { x: keys[0].x, y: keys[0].y };
  for (let i = 0; i < keys.length - 1; i++) {
    const a = keys[i], b = keys[i + 1];
    if (f <= b.f) {
      const dur = b.f - a.f, mv = Math.min(dur, Math.max(22, Math.min(44, Math.hypot(b.x - a.x, b.y - a.y) / 10))); const s0 = b.f - mv; // leave late, arrive on time
      if (f < s0) return { x: a.x + drift(f, 0.6, 50), y: a.y + drift(f, 0.5, 70, 1) };
      const t = E.io(Math.max(0, Math.min(1, (f - s0) / mv))), dx = b.x - a.x, dy = b.y - a.y, d = Math.hypot(dx, dy) || 1, side = i % 2 ? 1 : -1;
      const cx = a.x + dx / 2 - (dy / d) * d * 0.14 * side, cy = a.y + dy / 2 + (dx / d) * d * 0.14 * side, q = 1 - t;
      const st = Math.max(0, Math.min(1, (f - b.f + 6) / 6)), ov = Math.sin(st * Math.PI) * 0.012;
      return { x: q * q * a.x + 2 * q * t * cx + t * t * b.x + dx * ov, y: q * q * a.y + 2 * q * t * cy + t * t * b.y + dy * ov };
    }
  }
  const l = keys[keys.length - 1]; return { x: l.x + drift(f, 0.6, 50), y: l.y + drift(f, 0.5, 70, 1) };
};
export const shake = (keys: CurKey[], f: number, amp = 3) => { let sx = 0, sy = 0; keys.forEach((k) => { if (k.click && f >= k.f && f < k.f + 7) { const d = 1 - (f - k.f) / 7; sx += Math.sin((f - k.f) * 2.6) * amp * d; sy += Math.cos((f - k.f) * 3.1) * amp * 0.7 * d; } }); return { sx, sy }; };
const ARROW = (op: number) => (
  <svg viewBox="0 0 30 30" width={30} height={30} style={{ position: 'absolute', left: -3, top: -3, opacity: op }}><path d="M4 2 L4 24 L9.5 18.6 L13.4 27 L17 25.4 L13.2 17.2 L21 17.2 Z" fill="#fff" stroke="#0b1a33" strokeWidth={1.8} strokeLinejoin="round" /></svg>
);
export const Cursor: React.FC<{ keys: CurKey[]; show: [number, number]; scale?: number; f?: number }> = ({ keys, show, scale = 1.05, f: ff }) => {
  const cf = useF(); const f = ff ?? cf; if (f < show[0] || f > show[1]) return null;
  const { x, y } = cursorAt(keys, f); let press = 1; const ripples: React.ReactNode[] = [];
  keys.forEach((k, i) => { if (!k.click) return; if (f >= k.f && f <= k.f + 8) press = 1 - 0.1 * Math.sin(((f - k.f) / 8) * Math.PI); if (f >= k.f && f < k.f + 22) { const r = P(f, k.f, k.f + 22, E.out); ripples.push(<div key={i} style={{ position: 'absolute', left: k.x, top: k.y, width: 70, height: 70, margin: -35, borderRadius: '50%', border: `${3 * (1 - r) + 1}px solid rgba(59,123,255,${(1 - r) * 0.95})`, transform: `scale(${0.25 + r * 1.3})`, boxShadow: `0 0 ${20 * (1 - r)}px rgba(59,123,255,.6)` }} />); } });
  return (
    <>
      {ripples}
      {[6, 5, 4, 3, 2, 1].map((i) => { const g = cursorAt(keys, f - i * 1.4); return <div key={i} style={{ position: 'absolute', left: g.x, top: g.y, width: 0, height: 0, transform: `scale(${scale * (1 - i * 0.06)})`, opacity: 0.18 - i * 0.025, filter: 'blur(1px)' }}>{ARROW(1)}</div>; })}
      <div style={{ position: 'absolute', left: x, top: y, width: 0, height: 0, transform: `scale(${scale * press})`, filter: 'drop-shadow(0 0 10px rgba(120,170,255,.9)) drop-shadow(0 3px 5px rgba(0,0,0,.45))' }}>{ARROW(1)}</div>
    </>
  );
};

// ------------------------------------------------------------------ callout: a bracket on a UI element + a line that draws itself + a label that pops (spring)
export const Callout: React.FC<{ at: number; out?: number; x: number; y: number; w?: number; h?: number; dx: number; dy: number; text: string; sub?: string; z?: number; f?: number; accent?: string }> = ({ at, out = 1e9, x, y, w = 0, h = 0, dx, dy, text, sub, z = 1, f: ff, accent = C.blueHi }) => {
  const cf = useF(); const f = ff ?? cf; if (f < at || f > out + 12) return null;
  const draw = P(f, at, at + 12, E.io), pop = spring({ frame: f - at - 8, fps: 60, config: { damping: 11, stiffness: 190, mass: 0.7 } }), o = 1 - P(f, out, out + 12, E.in);
  const k = 1 / z; // keep stroke + type the same size on screen whatever the camera zoom
  const ex = x + w / 2 + dx, ey = y + (dy < 0 ? 0 : h) + dy, sx = x + w / 2, sy = dy < 0 ? y : y + h;
  const L = Math.hypot(ex - sx, ey - sy);
  return (
    <div style={{ position: 'absolute', left: 0, top: 0, opacity: o, pointerEvents: 'none' }}>
      {w > 0 && <div style={{ position: 'absolute', left: x - 6 * k, top: y - 6 * k, width: w + 12 * k, height: h + 12 * k, border: `${2 * k}px solid ${accent}`, borderRadius: 8 * k, opacity: draw, boxShadow: `0 0 ${18 * k}px rgba(59,123,255,.55)`, transform: `scale(${0.9 + draw * 0.1})` }} />}
      <svg width={W * 2} height={H * 2} style={{ position: 'absolute', left: -W / 2, top: -H / 2, overflow: 'visible' }}>
        <line x1={sx + W / 2} y1={sy + H / 2} x2={ex + W / 2} y2={ey + H / 2} stroke={accent} strokeWidth={2 * k} strokeDasharray={L} strokeDashoffset={L * (1 - draw)} strokeLinecap="round" />
        <circle cx={sx + W / 2} cy={sy + H / 2} r={4 * k} fill={accent} opacity={draw} />
      </svg>
      <div style={{ position: 'absolute', left: ex, top: ey, transform: `translate(${dx < 0 ? '-100%' : '0'}, -50%) scale(${k * (0.6 + pop * 0.4)})`, transformOrigin: dx < 0 ? '100% 50%' : '0 50%', opacity: Math.min(1, pop * 1.5), background: 'rgba(7,18,38,.9)', border: `1px solid rgba(255,255,255,.18)`, borderLeft: dx >= 0 ? `4px solid ${accent}` : undefined, borderRight: dx < 0 ? `4px solid ${accent}` : undefined, borderRadius: 10, padding: '12px 18px', boxShadow: '0 20px 50px rgba(0,0,0,.5)', whiteSpace: 'nowrap', backdropFilter: 'blur(8px)' }}>
        <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 24, color: '#fff', letterSpacing: -0.3 }}>{text}</div>
        {sub && <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 14, letterSpacing: '.12em', textTransform: 'uppercase', color: C.ice, marginTop: 4 }}>{sub}</div>}
      </div>
    </div>
  );
};

// ------------------------------------------------------------------ browser window (no real chrome ever shown; our own frame with the public domain) + camera
export type Cam = [number, number, number, number, number]; // focus x, focus y (screen css px), zoom, rotateY, rotateX
export const SCR_W = 1440, SCR_H = 900, CHROME = 46;
export const Window: React.FC<{ url: string; cam: number[]; ox?: number; oy?: number; shake?: { sx: number; sy: number }; children: React.ReactNode; over?: React.ReactNode; filter?: string; opacity?: number; anchorY?: number }> = ({ url, cam, ox = 0, oy = 0, shake: sh, children, over, filter, opacity = 1, anchorY = 0.565 }) => {
  const [fx, fy, z, ry, rx] = cam; const ax = W / 2 + ox + (sh?.sx || 0), ay = H * anchorY + oy + (sh?.sy || 0);
  return (
    <div style={{ position: 'absolute', inset: 0, perspective: 2600, opacity }}>
      <div style={{ position: 'absolute', left: 0, top: 0, width: SCR_W, height: SCR_H + CHROME, transformOrigin: '0 0', transform: `translate(${ax}px,${ay}px) rotateY(${ry}deg) rotateX(${rx}deg) scale(${z}) translate(${-fx}px,${-(fy + CHROME)}px)`, borderRadius: 18, overflow: 'hidden', background: '#fff', boxShadow: '0 70px 140px rgba(2,8,22,.6), 0 24px 48px rgba(2,8,22,.4), 0 0 0 1px rgba(255,255,255,.1)', filter }}>
        <div style={{ position: 'absolute', left: 0, top: 0, right: 0, height: CHROME, background: 'linear-gradient(#f3f5f9,#e9edf3)', borderBottom: '1px solid #d9dee8' }}>
          {['#ff5f57', '#febc2e', '#28c840'].map((c, i) => <i key={c} style={{ position: 'absolute', top: 17, left: 18 + i * 22, width: 13, height: 13, borderRadius: '50%', background: c }} />)}
          <div style={{ position: 'absolute', left: '50%', top: 9, transform: 'translateX(-50%)', width: 560, height: 28, borderRadius: 9, background: '#fff', border: '1px solid #dde2ea', font: `500 14px ${FONT.b}`, color: '#3a4557', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
            <b style={{ width: 10, height: 12, border: '2px solid #1a7f4b', borderTopWidth: 5, borderRadius: '3px 3px 2px 2px', boxSizing: 'border-box' }} />{url}
          </div>
        </div>
        <div style={{ position: 'absolute', left: 0, top: CHROME, width: SCR_W, height: SCR_H, overflow: 'hidden', background: '#fff' }}>{children}</div>
        {over}
      </div>
    </div>
  );
};
// which capture is on screen — hard cuts (UI state changes), never crossfades
export const Shots: React.FC<{ steps: [number, string][]; f?: number; y?: number }> = ({ steps, f: ff, y = 0 }) => {
  const cf = useF(); const f = ff ?? cf; let cur = steps[0][1]; steps.forEach(([a, n]) => { if (f >= a) cur = n; });
  return <Img src={S(cur)} style={{ position: 'absolute', left: 0, top: -y, width: SCR_W, display: 'block' }} />;
};
// a cropped region of a capture, used to animate rows / cards individually
export const Crop: React.FC<{ src: string; x: number; y: number; w: number; h: number; style?: React.CSSProperties }> = ({ src, x, y, w, h, style }) => (
  <div style={{ position: 'absolute', left: x, top: y, width: w, height: h, overflow: 'hidden', ...style }}><Img src={S(src)} style={{ position: 'absolute', left: -x, top: -y, width: SCR_W, display: 'block' }} /></div>
);

// ------------------------------------------------------------------ phone
export const Phone: React.FC<{ src: string; x: number; y: number; scale: number; ry?: number; rz?: number; opacity?: number; scroll?: number }> = ({ src, x, y, scale, ry = 0, rz = 0, opacity = 1, scroll = 0 }) => (
  <div style={{ position: 'absolute', left: x, top: y, width: 430, height: 904, transformOrigin: '50% 50%', transform: `translate(-50%,-50%) perspective(2200px) rotateY(${ry}deg) rotateZ(${rz}deg) scale(${scale})`, opacity, borderRadius: 66, background: 'linear-gradient(140deg,#3b4049,#0c0e12 40%,#2b2f36)', boxShadow: '0 70px 120px rgba(0,0,0,.6), inset 0 0 0 2px rgba(255,255,255,.14)' }}>
    <div style={{ position: 'absolute', left: 14, top: 14, width: 402, height: 876, borderRadius: 54, overflow: 'hidden', background: '#fff' }}><Img src={S(src)} style={{ width: 402, display: 'block', transform: `translateY(${-scroll}px)` }} /></div>
    <div style={{ position: 'absolute', left: '50%', top: 26, width: 118, height: 34, marginLeft: -59, borderRadius: 20, background: '#000' }} />
    <div style={{ position: 'absolute', inset: 0, borderRadius: 66, background: 'linear-gradient(115deg,rgba(255,255,255,.14) 0%,rgba(255,255,255,0) 35%)', pointerEvents: 'none' }} />
  </div>
);

// ------------------------------------------------------------------ price count-up in 160 px type with a light pulse
export const PriceUp: React.FC<{ at: number; value: number; dur?: number; label?: string; out?: number }> = ({ at, value, dur = 70, label, out = 1e9 }) => {
  const f = useF(); if (f < at || f > out + 12) return null;
  const p = P(f, at, at + dur, E.out), v = value * p, o = 1 - P(f, out, out + 12, E.in), done = f >= at + dur;
  const pulse = done ? 1 - P(f, at + dur, at + dur + 40, E.out) : 0;
  return (
    <div style={{ position: 'absolute', left: 0, right: 0, top: H / 2, transform: 'translateY(-50%)', textAlign: 'center', opacity: o }}>
      <div style={{ position: 'absolute', left: '50%', top: '50%', width: 1400, height: 700, transform: `translate(-50%,-50%) scale(${0.6 + pulse * 0.6})`, background: 'radial-gradient(ellipse, rgba(255,255,255,.35), rgba(59,123,255,0) 60%)', opacity: pulse }} />
      {label && <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 24, letterSpacing: '.32em', textTransform: 'uppercase', color: C.ice, marginBottom: 10 }}>{label}</div>}
      <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 160, letterSpacing: -6, color: '#fff', lineHeight: 1, textShadow: '0 0 60px rgba(120,170,255,.45), 0 20px 60px rgba(0,0,0,.6)', transform: `scale(${1 + pulse * 0.06})`, fontVariantNumeric: 'tabular-nums' }}>${v.toFixed(2)}</div>
    </div>
  );
};

// ------------------------------------------------------------------ a laptop built from CSS planes (base + lid), screen content = children
export const Laptop: React.FC<{ ry: number; rx: number; sweep: number; children: React.ReactNode; glow?: number; scale?: number }> = ({ ry, rx, sweep, children, glow = 1, scale = 1 }) => {
  const lw = 1240, lh = 790, bezel = 22; // lid outer, css px
  return (
    <div style={{ position: 'absolute', left: W / 2, top: H / 2 + 40, width: 0, height: 0, perspective: 2800 }}>
      <div style={{ position: 'absolute', left: 0, top: 0, transformStyle: 'preserve-3d', transform: `scale(${scale}) rotateX(${rx}deg) rotateY(${ry}deg)` }}>
        {/* base */}
        <div style={{ position: 'absolute', left: -lw / 2 - 20, top: lh / 2 - 8, width: lw + 40, height: 520, transformOrigin: '50% 0', transform: 'rotateX(90deg)', transformStyle: 'preserve-3d', borderRadius: 18, background: 'linear-gradient(180deg,#2f343c,#1b1f26)', boxShadow: '0 0 0 1px rgba(255,255,255,.08)' }}>
          <div style={{ position: 'absolute', left: 110, top: 70, right: 110, height: 240, borderRadius: 8, background: '#15181e', boxShadow: 'inset 0 0 0 1px rgba(255,255,255,.06)' }}>
            {[...Array(5)].map((_, r) => <div key={r} style={{ position: 'absolute', left: 8 + (r === 4 ? 160 : r * 10), top: 8 + r * 46, right: 8 + (r === 4 ? 160 : 0), height: 38, display: 'flex', gap: 6 }}>{[...Array(r === 4 ? 3 : 16 - r)].map((_, c) => <div key={c} style={{ flex: c === 1 && r === 4 ? 6 : 1, borderRadius: 4, background: 'linear-gradient(#2b3038,#1e2228)', boxShadow: '0 1px 0 rgba(255,255,255,.05)' }} />)}</div>)}
          </div>
          <div style={{ position: 'absolute', left: '50%', top: 330, width: 320, height: 150, marginLeft: -160, borderRadius: 10, background: '#1c2027', boxShadow: 'inset 0 0 0 1px rgba(255,255,255,.07)' }} />
        </div>
        {/* lid */}
        <div style={{ position: 'absolute', left: -lw / 2, top: -lh / 2, width: lw, height: lh, borderRadius: 22, background: 'linear-gradient(160deg,#2a2f37,#101317)', boxShadow: `0 0 0 1px rgba(255,255,255,.12), 0 40px 120px rgba(0,0,0,.7), 0 0 ${120 * glow}px rgba(80,140,255,${0.35 * glow})`, overflow: 'hidden' }}>
          <div style={{ position: 'absolute', left: bezel, top: bezel, width: lw - bezel * 2, height: lh - bezel * 2, borderRadius: 8, overflow: 'hidden', background: '#fff' }}>
            <div style={{ width: SCR_W, height: SCR_H, transformOrigin: '0 0', transform: `scale(${(lw - bezel * 2) / SCR_W})` }}>{children}</div>
          </div>
          <div style={{ position: 'absolute', left: '50%', top: 7, width: 8, height: 8, marginLeft: -4, borderRadius: '50%', background: '#0a0c10', boxShadow: 'inset 0 0 0 2px #22262d' }} />
          <div style={{ position: 'absolute', inset: 0, background: `linear-gradient(105deg, rgba(255,255,255,0) ${sweep * 160 - 60}%, rgba(255,255,255,.42) ${sweep * 160 - 40}%, rgba(255,255,255,0) ${sweep * 160 - 20}%)`, pointerEvents: 'none' }} />
        </div>
      </div>
    </div>
  );
};
