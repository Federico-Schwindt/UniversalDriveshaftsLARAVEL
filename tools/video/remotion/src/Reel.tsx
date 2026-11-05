import React from 'react';
import {
  AbsoluteFill, Img, Sequence, staticFile, useCurrentFrame, useVideoConfig,
  interpolate, spring, Easing, continueRender, delayRender,
} from 'remotion';

// ---------- brand ----------
const NAVY = '#0b1a33', NAVY2 = '#122244', BLUE = '#1246ab', BLUE_L = '#6f9cf0', RED = '#e11b22', PAPER = '#f6f8fc';
const H = 'UDSPoppins, Poppins, Arial, sans-serif';
const B = 'UDSHanken, "Hanken Grotesk", Arial, sans-serif';
const M = 'UDSMono, "IBM Plex Mono", monospace';

// fonts load before frames are captured (inside the component, once per render tab, with a safety cap)
const useBrandFonts = () => {
  const [handle] = React.useState(() => delayRender('fonts', { timeoutInMilliseconds: 60000 }));
  React.useEffect(() => {
    let done = false;
    const finish = () => { if (!done) { done = true; continueRender(handle); } };
    const cap = setTimeout(finish, 15000);
    Promise.all([
      new FontFace('UDSPoppins', `url(${staticFile('poppins-800.woff2')})`, { weight: '800' }).load(),
      new FontFace('UDSPoppins', `url(${staticFile('poppins-700.woff2')})`, { weight: '700' }).load(),
      new FontFace('UDSHanken', `url(${staticFile('hankengrotesk-var.woff2')})`, { weight: '100 900' }).load(),
      new FontFace('UDSMono', `url(${staticFile('ibmplexmono-600.woff2')})`, { weight: '600' }).load(),
    ]).then((fs) => { fs.forEach((f) => document.fonts.add(f)); }).catch(() => {}).finally(() => { clearTimeout(cap); finish(); });
  }, [handle]);
};

// ---------- scene timing (frames @30fps) ----------
const S = {
  intro: [0, 96], home: [96, 105], complete: [201, 120], designer: [321, 126],
  assistant: [447, 141], account: [588, 111], outro: [699, 111],
} as const;
export const TOTAL = 810;

const ease = Easing.bezier(0.16, 1, 0.3, 1);
const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
const fadeIO = (f: number, len: number, inF = 12, outF = 10) =>
  interpolate(f, [0, inF, len - outF, len], [0, 1, 1, 0], clamp);

// ---------- background: blueprint grid that drifts slowly ----------
const Blueprint: React.FC = () => {
  const f = useCurrentFrame();
  const shift = (f * 0.35) % 60;
  return (
    <AbsoluteFill style={{ background: `radial-gradient(120% 70% at 50% 35%, ${NAVY2} 0%, ${NAVY} 62%, #07111f 100%)` }}>
      <AbsoluteFill style={{
        opacity: 0.55,
        backgroundImage: `linear-gradient(rgba(111,156,240,.10) 1px, transparent 1px), linear-gradient(90deg, rgba(111,156,240,.10) 1px, transparent 1px),
          linear-gradient(rgba(111,156,240,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(111,156,240,.05) 1px, transparent 1px)`,
        backgroundSize: '120px 120px, 120px 120px, 24px 24px, 24px 24px',
        backgroundPosition: `0 ${shift}px, 0 ${shift}px, 0 ${shift}px, 0 ${shift}px`,
      }} />
    </AbsoluteFill>
  );
};

// ---------- a dimension line that draws itself: |<———— label ————>| ----------
const DimLine: React.FC<{ y: number; x1: number; x2: number; label: string; start: number; color?: string; size?: number }> =
  ({ y, x1, x2, label, start, color = BLUE_L, size = 34 }) => {
    const f = useCurrentFrame();
    const p = interpolate(f - start, [0, 22], [0, 1], { ...clamp, easing: ease });
    const mid = (x1 + x2) / 2, half = ((x2 - x1) / 2) * p;
    const lab = interpolate(f - start, [14, 26], [0, 1], clamp);
    return (
      <svg width={1080} height={1920} style={{ position: 'absolute', inset: 0 }}>
        <line x1={x1} x2={x1} y1={y - 26} y2={y + 26} stroke={color} strokeWidth={3} opacity={p} />
        <line x1={x2} x2={x2} y1={y - 26} y2={y + 26} stroke={color} strokeWidth={3} opacity={p} />
        <line x1={mid - half} x2={mid + half} y1={y} y2={y} stroke={color} strokeWidth={3} />
        <polygon points={`${mid - half},${y} ${mid - half + 22},${y - 9} ${mid - half + 22},${y + 9}`} fill={color} opacity={p} />
        <polygon points={`${mid + half},${y} ${mid + half - 22},${y - 9} ${mid + half - 22},${y + 9}`} fill={color} opacity={p} />
        <rect x={mid - (label.length * size * 0.33) - 18} y={y - size * 0.85} width={label.length * size * 0.66 + 36} height={size * 1.7} fill={NAVY} opacity={lab} />
        <text x={mid} y={y + size * 0.36} textAnchor="middle" fill="#fff" opacity={lab}
          style={{ fontFamily: M, fontWeight: 600, fontSize: size, letterSpacing: 2 }}>{label}</text>
      </svg>
    );
  };

// ---------- phone with a real screenshot ----------
const Phone: React.FC<{ src: string; enter?: number; pan?: number; y?: number; w?: number }> =
  ({ src, enter = 0, pan = 0, y = 640, w = 540 }) => {
    const f = useCurrentFrame(); const { fps } = useVideoConfig();
    const s = spring({ frame: f - enter, fps, config: { damping: 18, stiffness: 90, mass: 0.9 } });
    const lift = interpolate(s, [0, 1], [260, 0]);
    const screenH = w * (1748 / 804);
    const panY = interpolate(f, [enter + 20, enter + 110], [0, pan], { ...clamp, easing: Easing.inOut(Easing.cubic) });
    const bez = 18;
    return (
      <div style={{
        position: 'absolute', left: (1080 - w - bez * 2) / 2, top: y + lift, width: w + bez * 2, height: Math.min(screenH, 1180) + bez * 2,
        borderRadius: 72, background: '#05080f', padding: bez, opacity: s,
        boxShadow: '0 60px 120px -30px rgba(0,0,0,.75), 0 0 0 2px rgba(111,156,240,.25), inset 0 0 0 2px rgba(255,255,255,.06)',
      }}>
        <div style={{ width: w, height: Math.min(screenH, 1180), borderRadius: 56, overflow: 'hidden', position: 'relative', background: '#fff' }}>
          {/* status bar, so the notch sits over it instead of over the site's header */}
          <Img src={staticFile(src)} style={{ width: w, position: 'absolute', top: 58 - panY }} />
          <div style={{
            position: 'absolute', top: 0, left: 0, right: 0, height: 58, background: '#05080f', color: '#fff',
            display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 40px',
            fontFamily: H, fontWeight: 700, fontSize: 22,
          }}>
            <span>9:41</span>
            <span style={{ display: 'flex', gap: 7, alignItems: 'center' }}>
              <span style={{ width: 22, height: 12, borderRadius: 3, border: '2px solid #fff', position: 'relative' }}><span style={{ position: 'absolute', inset: 1.5, right: 5, background: '#fff', borderRadius: 1 }} /></span>
            </span>
          </div>
        </div>
        <div style={{ position: 'absolute', top: bez + 14, left: '50%', width: 150, height: 38, marginLeft: -75, borderRadius: 20, background: '#05080f' }} />
      </div>
    );
  };

// ---------- scene title block ----------
const Title: React.FC<{ n: string; kicker: string; lines: string[]; accent?: number; sub?: string; top?: number }> =
  ({ n, kicker, lines, accent = -1, sub, top = 250 }) => {
    const f = useCurrentFrame();
    const k = interpolate(f, [0, 12], [0, 1], { ...clamp, easing: ease });
    return (
      <div style={{ position: 'absolute', left: 80, right: 80, top }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 18, opacity: k, transform: `translateX(${(1 - k) * -40}px)` }}>
          <span style={{ fontFamily: M, fontWeight: 600, fontSize: 30, color: '#fff', background: RED, padding: '6px 14px', borderRadius: 8 }}>{n}</span>
          <span style={{ fontFamily: M, fontWeight: 600, fontSize: 30, letterSpacing: 6, color: BLUE_L }}>{kicker}</span>
        </div>
        <div style={{ marginTop: 26 }}>
          {lines.map((l, i) => {
            const a = interpolate(f, [6 + i * 5, 22 + i * 5], [0, 1], { ...clamp, easing: ease });
            return (
              <div key={i} style={{ overflow: 'hidden' }}>
                <div style={{
                  fontFamily: H, fontWeight: 800, fontSize: 92, lineHeight: 1.04, letterSpacing: -2.5,
                  color: i === accent ? RED : '#fff', transform: `translateY(${(1 - a) * 110}%)`,
                }}>{l}</div>
              </div>
            );
          })}
        </div>
        {sub && (
          <div style={{
            marginTop: 24, fontFamily: B, fontWeight: 500, fontSize: 38, lineHeight: 1.35, color: '#c6d2ea', maxWidth: 880,
            opacity: interpolate(f, [20, 34], [0, 1], clamp),
          }}>{sub}</div>
        )}
      </div>
    );
  };

// ---------- floating pill (a feature chip next to the phone) ----------
const Chip: React.FC<{ text: string; at: number; x: number; y: number; dot?: string }> = ({ text, at, x, y, dot = '#22c55e' }) => {
  const f = useCurrentFrame(); const { fps } = useVideoConfig();
  const s = spring({ frame: f - at, fps, config: { damping: 14, stiffness: 120 } });
  return (
    <div style={{
      position: 'absolute', left: x, top: y, transform: `scale(${s})`, transformOrigin: 'left center', opacity: s,
      display: 'flex', alignItems: 'center', gap: 14, padding: '18px 28px', borderRadius: 999,
      background: 'rgba(255,255,255,.96)', boxShadow: '0 24px 50px -20px rgba(0,0,0,.6)',
      fontFamily: H, fontWeight: 700, fontSize: 32, color: NAVY, whiteSpace: 'nowrap',
    }}>
      <span style={{ width: 16, height: 16, borderRadius: 8, background: dot }} />{text}
    </div>
  );
};

// ================= scenes =================
const Intro: React.FC = () => {
  const f = useCurrentFrame(); const { fps } = useVideoConfig();
  const logo = spring({ frame: f - 30, fps, config: { damping: 16, stiffness: 100 } });
  const part = spring({ frame: f - 4, fps, config: { damping: 20, stiffness: 60 } });
  const spin = interpolate(f, [0, 96], [-18, 6]);
  const out = interpolate(f, [84, 96], [1, 0], clamp);
  return (
    <AbsoluteFill style={{ opacity: out }}>
      <Img src={staticFile('ujoint.webp')} style={{
        position: 'absolute', width: 760, left: 160, top: 300, opacity: part * 0.95,
        transform: `scale(${0.7 + part * 0.3}) rotate(${spin}deg)`, filter: 'drop-shadow(0 50px 60px rgba(0,0,0,.6))',
      }} />
      <DimLine y={1150} x1={120} x2={960} label="NEW WEBSITE" start={18} />
      <div style={{
        position: 'absolute', left: 0, right: 0, top: 1250, textAlign: 'center',
        opacity: logo, transform: `translateY(${(1 - logo) * 40}px) scale(${0.92 + logo * 0.08})`,
      }}>
        <div style={{ display: 'inline-block', background: '#fff', padding: '26px 44px', borderRadius: 28, boxShadow: '0 30px 60px -20px rgba(0,0,0,.6)' }}>
          <Img src={staticFile('logo.webp')} style={{ width: 600, display: 'block' }} />
        </div>
      </div>
      <div style={{
        position: 'absolute', left: 60, right: 60, top: 1520, textAlign: 'center',
        fontFamily: M, fontWeight: 600, fontSize: 30, letterSpacing: 5, color: BLUE_L,
        opacity: interpolate(f, [46, 60], [0, 1], clamp),
      }}>COMPONENTS · COMPLETE DRIVESHAFTS · PERFORMANCE</div>
    </AbsoluteFill>
  );
};

const Feature: React.FC<{
  len: number; n: string; kicker: string; lines: string[]; accent?: number; sub: string; src: string; pan?: number;
  chips?: { text: string; at: number; x: number; y: number; dot?: string }[]; children?: React.ReactNode;
}> = ({ len, n, kicker, lines, accent, sub, src, pan, chips = [], children }) => {
  const f = useCurrentFrame();
  return (
    <AbsoluteFill style={{ opacity: fadeIO(f, len) }}>
      <Title n={n} kicker={kicker} lines={lines} accent={accent} sub={sub} />
      <Phone src={src} enter={8} pan={pan} y={760} w={500} />
      {chips.map((c, i) => <Chip key={i} {...c} />)}
      {children}
    </AbsoluteFill>
  );
};

const Outro: React.FC = () => {
  const f = useCurrentFrame(); const { fps } = useVideoConfig();
  const s = spring({ frame: f - 6, fps, config: { damping: 16, stiffness: 90 } });
  const url = spring({ frame: f - 26, fps, config: { damping: 18, stiffness: 110 } });
  return (
    <AbsoluteFill style={{ opacity: interpolate(f, [0, 10], [0, 1], clamp) }}>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 560, textAlign: 'center', opacity: s, transform: `scale(${0.9 + s * 0.1})` }}>
        <div style={{ display: 'inline-block', background: '#fff', padding: '34px 56px', borderRadius: 32, boxShadow: '0 40px 80px -30px rgba(0,0,0,.7)' }}>
          <Img src={staticFile('logo.webp')} style={{ width: 720, display: 'block' }} />
        </div>
      </div>
      <DimLine y={930} x1={150} x2={930} label="BUILT & BALANCED IN THE USA" start={14} size={28} />
      <div style={{
        position: 'absolute', left: 0, right: 0, top: 1040, textAlign: 'center',
        fontFamily: H, fontWeight: 800, fontSize: 74, letterSpacing: -1.5, color: '#fff',
        opacity: url, transform: `translateY(${(1 - url) * 30}px)`,
      }}>universaldriveshafts.com</div>
      <div style={{
        position: 'absolute', left: 0, right: 0, top: 1160, textAlign: 'center',
        fontFamily: B, fontWeight: 500, fontSize: 40, color: '#c6d2ea', opacity: interpolate(f, [34, 48], [0, 1], clamp),
      }}>Shop parts · Find your driveshaft · Ask UDS</div>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 1290, textAlign: 'center', opacity: interpolate(f, [44, 58], [0, 1], clamp) }}>
        <span style={{
          display: 'inline-block', fontFamily: H, fontWeight: 700, fontSize: 40, color: '#fff', background: BLUE,
          padding: '24px 56px', borderRadius: 999, boxShadow: '0 20px 40px -14px rgba(18,70,171,.9)',
        }}>Link in bio  →</span>
      </div>
    </AbsoluteFill>
  );
};

export const UdsReel: React.FC = () => { useBrandFonts(); return (
  <AbsoluteFill style={{ background: NAVY }}>
    <Blueprint />
    <Sequence from={S.intro[0]} durationInFrames={S.intro[1]}><Intro /></Sequence>
    <Sequence from={S.home[0]} durationInFrames={S.home[1]}>
      <Feature len={S.home[1]} n="01" kicker="THE NEW SITE" lines={['Every part,', 'on the shelf', 'today.']} accent={2}
        sub="U-joints, yokes, bearings and tubing, 1310 to 1810. Ships the same day."
        src="home.png" pan={240}
        chips={[{ text: 'Same-day shipping', at: 40, x: 560, y: 1430 }]} />
    </Sequence>
    <Sequence from={S.complete[0]} durationInFrames={S.complete[1]}>
      <Feature len={S.complete[1]} n="02" kicker="COMPLETE DRIVESHAFTS" lines={['Find yours', 'by vehicle.']} accent={1}
        sub="Pick the make, model and year. We show the exact shaft that fits."
        src="complete.png" pan={300}
        chips={[{ text: 'Ready to install', at: 42, x: 80, y: 1450, dot: '#6f9cf0' }]} />
    </Sequence>
    <Sequence from={S.designer[0]} durationInFrames={S.designer[1]}>
      <Feature len={S.designer[1]} n="03" kicker="DRIVESHAFT DESIGNER" lines={['Draw it.', 'Dimension it.']} accent={1}
        sub="Tap a dimension, type it in inches and send the sheet to our shop."
        src="designer.png" pan={60}
        chips={[{ text: 'Free · No account needed', at: 46, x: 440, y: 1450, dot: '#f59e0b' }]} />
    </Sequence>
    <Sequence from={S.assistant[0]} durationInFrames={S.assistant[1]}>
      <Feature len={S.assistant[1]} n="04" kicker="UDS ASSISTANT · AI" lines={['Ask UDS.', 'Get the right', 'part, fast.']} accent={0}
        sub="AI that knows our catalog: fitment, stock and shipping. Send a photo, or chat with our team."
        src="assistant.png" pan={180}
        chips={[
          { text: 'Send a photo', at: 46, x: 70, y: 1380, dot: '#6f9cf0' },
          { text: 'Real people too', at: 62, x: 560, y: 1480 },
        ]} />
    </Sequence>
    <Sequence from={S.account[0]} durationInFrames={S.account[1]}>
      <Feature len={S.account[1]} n="05" kicker="MY ACCOUNT" lines={['Your orders,', 'tracked.']} accent={1}
        sub="Live tracking, one-tap reorder, saved addresses and returns."
        src="account.png" pan={200}
        chips={[{ text: 'Buy again in one tap', at: 40, x: 480, y: 1450, dot: '#22c55e' }]} />
    </Sequence>
    <Sequence from={S.outro[0]} durationInFrames={S.outro[1]}><Outro /></Sequence>
  </AbsoluteFill>
); };
