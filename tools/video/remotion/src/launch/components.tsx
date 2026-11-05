import React from 'react';
import { AbsoluteFill, useCurrentFrame, interpolate, Img, staticFile } from 'remotion';
import { COLORS as C } from './config';
import { FONT, E, clamp, enter, enterSoft, exit, ease, drift, press, useLayout } from './theme';

// ================= stage, board & camera =================
// A board is a fixed-size design surface (px) centred on the canvas, scaled with ONE uniform factor.
// The camera moves inside it: keys say which board point is centred and the zoom, eased in-out between keys,
// always with a micro push so nothing fully stops.
export type CamKey = { f: number; x: number; y: number; s: number };
const camAt = (keys: CamKey[], f: number) => {
  if (f <= keys[0].f) return keys[0];
  for (let i = 0; i < keys.length - 1; i++) {
    const a = keys[i], b = keys[i + 1];
    if (f <= b.f) {
      const p = interpolate(f, [a.f, b.f], [0, 1], { ...clamp, easing: E.inOut });
      return { f, x: a.x + (b.x - a.x) * p, y: a.y + (b.y - a.y) * p, s: a.s + (b.s - a.s) * p };
    }
  }
  return keys[keys.length - 1];
};

export const Board: React.FC<{ w: number; h: number; cam?: CamKey[]; y?: number; scale?: number; children: React.ReactNode; style?: React.CSSProperties }> =
  ({ w, h, cam, y, scale, children, style }) => {
    const f = useCurrentFrame(); const L = useLayout();
    const base = scale ?? L.fit(w, h);
    const k = cam ? camAt(cam, f) : { x: w / 2, y: h / 2, s: 1 };
    const s = base * k.s * (1 + f * 0.00004);
    const cy = L.cy + (y ?? L.boardY);
    return (
      <div style={{ position: 'absolute', left: L.cx, top: cy, width: 0, height: 0 }}>
        <div style={{ position: 'absolute', left: 0, top: 0, width: w, height: h, transformOrigin: '0 0',
          transform: `scale(${s}) translate(${-k.x}px, ${-k.y}px)`, ...style }}>{children}</div>
      </div>
    );
  };

// ================= cursor =================
// Travels on a quadratic arc (control point offset ~15% of the distance), eased in-out, settles with a tiny
// overshoot; hesitation = a key that holds near the same spot while a slow drift keeps it alive.
export type CurKey = { f: number; x: number; y: number; click?: boolean };
export const cursorAt = (keys: CurKey[], f: number) => {
  if (f <= keys[0].f) return { x: keys[0].x, y: keys[0].y };
  for (let i = 0; i < keys.length - 1; i++) {
    const a = keys[i], b = keys[i + 1];
    if (f <= b.f) {
      const p = interpolate(f, [a.f, b.f], [0, 1], { ...clamp, easing: E.inOut });
      const dx = b.x - a.x, dy = b.y - a.y, dist = Math.hypot(dx, dy) || 1, side = i % 2 ? 1 : -1;
      const cx = a.x + dx / 2 - (dy / dist) * dist * 0.15 * side, cy = a.y + dy / 2 + (dx / dist) * dist * 0.15 * side;
      const q = 1 - p;
      return { x: q * q * a.x + 2 * q * p * cx + p * p * b.x, y: q * q * a.y + 2 * q * p * cy + p * p * b.y };
    }
  }
  const last = keys[keys.length - 1];
  return { x: last.x, y: last.y };
};
export const Cursor: React.FC<{ keys: CurKey[]; show?: [number, number] }> = ({ keys, show }) => {
  const f = useCurrentFrame();
  let { x, y } = cursorAt(keys, f);
  // settle overshoot after each arrival + idle drift
  for (let i = 1; i < keys.length; i++) {
    const t = f - keys[i].f;
    if (t >= 0 && t < 14) { const a = keys[i - 1], b = keys[i]; const d = Math.hypot(b.x - a.x, b.y - a.y) || 1; const o = Math.sin((t / 14) * Math.PI) * Math.min(8, d * 0.03) * (1 - t / 14); x += ((b.x - a.x) / d) * o; y += ((b.y - a.y) / d) * o; }
  }
  x += drift(f, 2, 90); y += drift(f, 2, 110, 1);
  const click = keys.filter((k) => k.click).map((k) => k.f);
  const s = click.reduce((acc, c) => acc * press(f, c), 1);
  const op = show ? interpolate(f, [show[0], show[0] + 10, show[1] - 8, show[1]], [0, 1, 1, 0], clamp) : 1;
  return (
    <div style={{ position: 'absolute', left: x, top: y, zIndex: 50, opacity: op, transform: `scale(${s})`, transformOrigin: '0 0', pointerEvents: 'none' }}>
      {click.map((c) => { const t = f - c; return t >= 0 && t < 22 ? <div key={c} style={{ position: 'absolute', left: -26, top: -26, width: 52, height: 52, borderRadius: 26, border: `3px solid ${C.blue}`, opacity: 1 - t / 22, transform: `scale(${0.4 + (t / 22) * 1.2})` }} /> : null; })}
      <svg width="40" height="48" viewBox="0 0 20 24" style={{ filter: 'drop-shadow(0 4px 8px rgba(0,0,0,.35))' }}>
        <path d="M1 1 L1 19 L6 14.5 L9.2 22 L12.4 20.6 L9.2 13.2 L15.6 13.2 Z" fill="#fff" stroke="#0b1a33" strokeWidth="1.4" strokeLinejoin="round" />
      </svg>
    </div>
  );
};

// ================= text =================
export const Kinetic: React.FC<{ text: string; at: number; size: number; color?: string; accent?: string[]; y: number; maxW?: number; weight?: number; out?: number }> =
  ({ text, at, size, color = '#fff', accent = [], y, maxW, weight = 800, out }) => {
    const f = useCurrentFrame(); const L = useLayout();
    const words = text.split(' ');
    const ex = out !== undefined ? exit(f, out) : 0;
    return (
      <div style={{ position: 'absolute', left: L.cx, top: L.cy + y, width: maxW ?? L.W * 0.9, transform: `translate(-50%, -50%) translateY(${drift(f, 3, 160)}px)`, textAlign: 'center',
        fontFamily: FONT.h, fontWeight: weight, fontSize: size, lineHeight: 1.06, letterSpacing: size > 80 ? -2.5 : -1, color, opacity: 1 - ex }}>
        {words.map((w, i) => {
          const p = enter(f, at + i * 3);
          return <span key={i} style={{ display: 'inline-block', marginRight: i < words.length - 1 ? '0.24em' : 0, opacity: Math.min(1, p * 1.4), color: accent.includes(w) ? C.red : undefined,
            transform: `translateY(${(1 - p) * size * 0.7}px) scale(${0.92 + p * 0.08})` }}>{w}</span>;
        })}
      </div>
    );
  };

export const Typed: React.FC<{ text: string; at: number; cps?: number; caret?: boolean }> = ({ text, at, cps = 2.6, caret = true }) => {
  const f = useCurrentFrame();
  const n = Math.max(0, Math.min(text.length, Math.floor((f - at) / cps)));
  const typing = f >= at && n < text.length;
  return <span>{text.slice(0, n)}{caret && (typing || (f >= at && Math.floor(f / 16) % 2 === 0)) ? <span style={{ display: 'inline-block', width: 3, height: '1.05em', background: C.blue, verticalAlign: '-0.15em', marginLeft: 2 }} /> : null}</span>;
};

// ================= counter (soft red glow, ~1 s count) =================
export const Counter: React.FC<{ from: number; to: number; at: number; label: string; cool?: number; frame?: number; scale?: number }> =
  ({ from, to, at, label, cool, frame, scale = 1 }) => {
    const cf = useCurrentFrame(); const f = frame ?? cf; const L = useLayout();
    const p = interpolate(f, [at, at + 60], [0, 1], { ...clamp, easing: E.out });
    const v = Math.round(from + (to - from) * p);
    const coolP = cool !== undefined ? ease(f, cool, cool + 40, 0, 1) : 0;
    const glow = `rgba(${Math.round(225 - 194 * coolP)}, ${Math.round(40 + 117 * coolP)}, ${Math.round(40 + 45 * coolP)}, ${0.75})`;
    const col = coolP > 0.5 ? '#7ee2a8' : '#ff6b6b';
    return (
      <div style={{ position: 'absolute', right: L.margin, top: L.margin, textAlign: 'right', transform: `scale(${scale}) translateY(${drift(f, 2, 120)}px)`, transformOrigin: 'right top' }}>
        <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 26, letterSpacing: 5, color: C.blueL }}>{label}</div>
        <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 112, lineHeight: 1, color: col, letterSpacing: -3, fontVariantNumeric: 'tabular-nums',
          textShadow: `0 0 28px ${glow}, 0 0 60px ${glow}` }}>{v}<span style={{ fontSize: 56, marginLeft: 6 }}>h</span></div>
      </div>
    );
  };

// ================= chrome & icons =================
export const BrowserWindow: React.FC<{ w: number; h: number; url: string; tabs?: string[]; active?: number; children: React.ReactNode }> = ({ w, h, url, tabs, active = 0, children }) => (
  <div style={{ position: 'absolute', left: 0, top: 0, width: w, height: h, borderRadius: 22, overflow: 'hidden', background: '#fff', boxShadow: '0 60px 120px -40px rgba(0,0,0,.75), 0 0 0 1px rgba(255,255,255,.12)' }}>
    <div style={{ height: tabs ? 104 : 64, background: '#e9eef7', borderBottom: `1px solid ${C.line}` }}>
      {tabs && (
        <div style={{ display: 'flex', gap: 6, padding: '12px 18px 0', height: 46 }}>
          {tabs.map((t, i) => (
            <div key={t} style={{ width: 230, padding: '0 18px', display: 'flex', alignItems: 'center', gap: 10, borderRadius: '12px 12px 0 0', fontFamily: FONT.b, fontWeight: 600, fontSize: 19,
              background: i === active ? '#fff' : 'transparent', color: i === active ? C.ink : C.ink3, boxShadow: i === active ? '0 -1px 0 rgba(0,0,0,.04)' : 'none' }}>
              <span style={{ width: 14, height: 14, borderRadius: 4, background: ['#e5534b', '#8a98b5', '#8a98b5', '#21a366'][i % 4] }} />{t}
            </div>
          ))}
        </div>
      )}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '0 20px', height: tabs ? 58 : 64 }}>
        {['#ff5f57', '#febc2e', '#28c840'].map((c) => <span key={c} style={{ width: 14, height: 14, borderRadius: 7, background: c }} />)}
        <div style={{ marginLeft: 14, flex: 1, height: 36, borderRadius: 18, background: '#fff', border: `1px solid ${C.line}`, display: 'flex', alignItems: 'center', padding: '0 18px', fontFamily: FONT.m, fontWeight: 600, fontSize: 18, color: C.ink3 }}>{url}</div>
      </div>
    </div>
    <div style={{ position: 'relative', width: w, height: h - (tabs ? 104 : 64), overflow: 'hidden' }}>{children}</div>
  </div>
);

export const Icon: React.FC<{ name: string; size?: number; color?: string }> = ({ name, size = 40, color = C.blue }) => {
  const p: Record<string, React.ReactNode> = {
    cross: <><rect x="10" y="10" width="12" height="12" rx="2" /><rect x="13" y="2" width="6" height="8" rx="1.5" /><rect x="13" y="22" width="6" height="8" rx="1.5" /><rect x="2" y="13" width="8" height="6" rx="1.5" /><rect x="22" y="13" width="8" height="6" rx="1.5" /></>,
    yoke: <><path d="M6 6v10a10 10 0 0 0 20 0V6" fill="none" strokeWidth="3.2" stroke={color} /><rect x="13" y="24" width="6" height="7" rx="1.5" /></>,
    bearing: <><circle cx="16" cy="16" r="12" fill="none" strokeWidth="3.2" stroke={color} /><circle cx="16" cy="16" r="4.5" /></>,
    tube: <><rect x="2" y="11" width="28" height="10" rx="5" /></>,
    fit: <><path d="M5 22l7-12h8l7 12" fill="none" strokeWidth="3" stroke={color} /><circle cx="9" cy="23" r="3.5" /><circle cx="23" cy="23" r="3.5" /></>,
    search: <><circle cx="14" cy="14" r="8" fill="none" strokeWidth="3.2" stroke={color} /><path d="M20 20l7 7" strokeWidth="3.6" stroke={color} strokeLinecap="round" /></>,
    tag: <><path d="M4 16V6a2 2 0 0 1 2-2h10l12 12-12 12z" fill="none" strokeWidth="3" stroke={color} /><circle cx="10.5" cy="10.5" r="2.5" /></>,
    truck: <><rect x="2" y="9" width="17" height="13" rx="2" /><path d="M19 13h6l4 5v4H19z" /><circle cx="8" cy="25" r="3" fill="#fff" stroke={color} strokeWidth="2.5" /><circle cx="24" cy="25" r="3" fill="#fff" stroke={color} strokeWidth="2.5" /></>,
    check: <path d="M7 16.5l6 6 12-13" fill="none" strokeWidth="4.2" stroke={color} strokeLinecap="round" strokeLinejoin="round" />,
  };
  return <svg width={size} height={size} viewBox="0 0 32 32" fill={color}>{p[name]}</svg>;
};

// checkmark that pops in with overshoot
export const Check: React.FC<{ at: number; size?: number }> = ({ at, size = 54 }) => {
  const f = useCurrentFrame(); const p = enter(f, at, 20);
  if (f < at) return null;
  return (
    <div style={{ width: size, height: size, borderRadius: size / 2, background: C.green, display: 'grid', placeItems: 'center', transform: `scale(${p})`, boxShadow: `0 0 0 ${8 * (1 - Math.min(1, (f - at) / 24))}px rgba(31,157,85,.35)` }}>
      <Icon name="check" size={size * 0.7} color="#fff" />
    </div>
  );
};

// ================= agent card (UDS Assistant at work) =================
export const AgentCard: React.FC<{ t: string; d: string; icon: string; enterAt: number; workAt: number; doneAt?: number; checkAt?: number; w?: number; compact?: boolean }> =
  ({ t, d, icon, enterAt, workAt, doneAt = workAt + 70, checkAt, w = 620, compact = false }) => {
    const f = useCurrentFrame();
    const p = enter(f, enterAt);
    // one pulse when the card starts working
    const pulse = f >= workAt && f < workAt + 24 ? Math.sin(((f - workAt) / 24) * Math.PI) : 0;
    const prog = interpolate(f, [workAt, doneAt], [0, 1], { ...clamp, easing: E.inOut });
    const h = compact ? 104 : 132;
    return (
      <div style={{ width: w, height: h, borderRadius: 22, background: '#fff', position: 'relative', overflow: 'hidden',
        opacity: Math.min(1, p * 1.5), transform: `translateX(${(1 - p) * 120}px) scale(${(0.9 + p * 0.1) * (1 + pulse * 0.035)})`,
        boxShadow: `0 24px 50px -24px rgba(0,0,0,.55), 0 0 0 ${2 + pulse * 6}px rgba(1,93,202,${0.18 + pulse * 0.4})` }}>
        <div style={{ position: 'absolute', left: 26, top: '50%', transform: 'translateY(-50%)', width: compact ? 58 : 70, height: compact ? 58 : 70, borderRadius: 18, background: '#eaf1fd', display: 'grid', placeItems: 'center' }}><Icon name={icon} size={compact ? 32 : 38} /></div>
        <div style={{ position: 'absolute', left: compact ? 104 : 120, top: compact ? 20 : 26, right: 100 }}>
          <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: compact ? 26 : 30, color: C.ink }}>{t}</div>
          <div style={{ fontFamily: FONT.b, fontWeight: 500, fontSize: compact ? 19 : 22, color: C.ink3, marginTop: 4, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{d}</div>
        </div>
        <div style={{ position: 'absolute', left: compact ? 104 : 120, right: 100, bottom: compact ? 18 : 22, height: 8, borderRadius: 4, background: '#e8edf6' }}>
          <div style={{ width: `${prog * 100}%`, height: '100%', borderRadius: 4, background: prog >= 1 ? C.green : C.blue }} />
        </div>
        <div style={{ position: 'absolute', right: 26, top: '50%', transform: 'translateY(-50%)' }}>
          {checkAt !== undefined ? <Check at={checkAt} size={compact ? 46 : 54} /> : f >= workAt && prog < 1 ? <Spinner /> : null}
        </div>
      </div>
    );
  };

const Spinner: React.FC = () => { const f = useCurrentFrame(); return <div style={{ width: 40, height: 40, borderRadius: 20, border: `5px solid #dbe5f5`, borderTopColor: C.blue, transform: `rotate(${f * 9}deg)` }} />; };

// ================= background =================
export const Backdrop: React.FC<{ light?: boolean }> = ({ light = false }) => {
  const f = useCurrentFrame();
  const par = drift(f, 12, 400);
  return (
    <AbsoluteFill style={{ background: light ? C.paper : `radial-gradient(90% 70% at 50% 40%, ${C.navy2}, ${C.navy} 60%, #060e1d)` }}>
      <AbsoluteFill style={{ opacity: light ? 0.5 : 0.35, backgroundImage: `linear-gradient(${light ? 'rgba(1,93,202,.07)' : 'rgba(111,156,240,.10)'} 1px, transparent 1px), linear-gradient(90deg, ${light ? 'rgba(1,93,202,.07)' : 'rgba(111,156,240,.10)'} 1px, transparent 1px)`,
        backgroundSize: '64px 64px', backgroundPosition: `${par}px ${par * 0.6}px` }} />
    </AbsoluteFill>
  );
};

export const Logo: React.FC<{ h: number }> = ({ h }) => <Img src={staticFile('uds-logo.svg')} style={{ height: h, display: 'block' }} />;

// scene shell: fast ease-in exit on the last frames
export const SceneOut: React.FC<{ len: number; children: React.ReactNode; scaleTo?: number }> = ({ len, children, scaleTo = 0.94 }) => {
  const f = useCurrentFrame(); const x = exit(f, len - 18);
  return <AbsoluteFill style={{ opacity: 1 - x, transform: `scale(${1 - (1 - scaleTo) * x})` }}>{children}</AbsoluteFill>;
};
export { enter, enterSoft, exit, ease, drift, press };
