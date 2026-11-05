import React from 'react';
import { AbsoluteFill, useCurrentFrame, interpolate } from 'remotion';
import { COLORS as C, COPY, SCENES, CLICKS } from './config';
import { FONT, E, clamp, useLayout } from './theme';
import { Board, BrowserWindow, Cursor, Kinetic, Typed, Icon, Check, AgentCard, Backdrop, Logo, SceneOut, enter, enterSoft, exit, ease, drift, press } from './components';

const len = (k: keyof typeof SCENES) => SCENES[k][1] - SCENES[k][0];

// words that each appear at their own frame, side by side
const Words: React.FC<{ words: string[]; ats: number[]; size: number; y: number; colors?: string[] }> = ({ words, ats, size, y, colors = [] }) => {
  const f = useCurrentFrame(); const L = useLayout();
  return (
    <div style={{ position: 'absolute', left: L.cx, top: L.cy + y, transform: `translate(-50%, -50%) translateY(${drift(f, 3, 150)}px)`, display: 'flex', gap: size * 0.35, whiteSpace: 'nowrap' }}>
      {words.map((w, i) => { const p = enter(f, ats[i]); return <span key={w} style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: size, letterSpacing: -2, color: colors[i] ?? '#fff', opacity: Math.min(1, p * 1.4), transform: `translateY(${(1 - p) * size * 0.6}px)` }}>{w}</span>; })}
    </div>
  );
};
const Kicker: React.FC<{ text: string; y: number; at?: number; out?: number }> = ({ text, y, at = 0, out }) => {
  const f = useCurrentFrame(); const L = useLayout();
  const p = enterSoft(f, at); const x = out !== undefined ? exit(f, out) : 0;
  return <div style={{ position: 'absolute', left: L.cx, top: L.cy + y, width: L.W * 0.9, textAlign: 'center', lineHeight: 1.5, transform: `translate(-50%, -50%) translateY(${(1 - p) * 20}px)`, opacity: p * (1 - x), fontFamily: FONT.m, fontWeight: 600, fontSize: L.wide ? 26 : 22, letterSpacing: L.wide ? 6 : 4, color: C.blueL }}>{text}</div>;
};

// ================= 1 · hook =================
export const Hook: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const push = ease(f, 0, 240, 1, 1.06);
  const pill = enter(f, 10);
  return (
    <SceneOut len={len('hook')}>
      <Backdrop />
      <AbsoluteFill style={{ transform: `scale(${push})` }}>
        <div style={{ position: 'absolute', left: L.cx, top: L.cy - (L.tall ? 190 : 150), transform: `translate(-50%, -50%) scale(${0.8 + pill * 0.2}) translateY(${drift(f, 3, 130)}px)`, opacity: Math.min(1, pill * 1.5),
          display: 'flex', alignItems: 'center', gap: 16, padding: '16px 30px', borderRadius: 999, background: 'rgba(207,49,48,.14)', border: `2px solid ${C.red}`, whiteSpace: 'nowrap' }}>
          <span style={{ width: 16, height: 16, borderRadius: 8, background: C.red, boxShadow: `0 0 ${10 + drift(f, 6, 40)}px ${C.red}` }} />
          <span style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: L.tall ? 24 : 28, letterSpacing: 3, color: '#ffd6d6' }}>{COPY.pill}</span>
        </div>
        <Kinetic text={COPY.hook} at={34} size={L.wide ? 140 : 118} y={L.tall ? 60 : 40} accent={['down.']} maxW={L.W * 0.88} />
      </AbsoluteFill>
    </SceneOut>
  );
};

// ================= 2 · the parts hunt =================
const TAB_X = (i: number) => 18 + i * 236 + 115;
const HuntContent: React.FC<{ tab: number }> = ({ tab }) => {
  const rows = Array.from({ length: 15 });
  if (tab === 0) return (
    <div style={{ position: 'absolute', left: 280, top: 30, width: 740, height: 620, background: '#fff', boxShadow: '0 4px 20px rgba(0,0,0,.12)', padding: 30, boxSizing: 'border-box' }}>
      <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 22, color: C.ink2 }}>UNIVERSAL JOINT INTERCHANGE — PAGE 214</div>
      {rows.map((_, i) => <div key={i} style={{ marginTop: 12, height: 18, display: 'flex', gap: 10, background: i === 6 ? 'rgba(255,214,0,.35)' : 'transparent' }}>{[90, 160, 120, 200, 80].map((w, k) => <span key={k} style={{ width: w, background: '#d9dee8', borderRadius: 3 }} />)}</div>)}
    </div>
  );
  if (tab === 1) return (
    <div style={{ position: 'absolute', inset: 0, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 40, padding: 70 }}>
      <div style={{ background: '#eef1f6', borderRadius: 18, display: 'grid', placeItems: 'center' }}><Icon name="cross" size={150} color="#c4cbd8" /></div>
      <div>
        <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 38, color: C.ink }}>Driveshaft assembly</div>
        <div style={{ fontFamily: FONT.b, fontSize: 24, color: C.ink3, marginTop: 10 }}>Fitment: contact us</div>
        <div style={{ marginTop: 50, padding: '26px 40px', borderRadius: 14, border: `3px solid ${C.ink2}`, fontFamily: FONT.h, fontWeight: 800, fontSize: 36, color: C.ink2, display: 'inline-block' }}>Call for price</div>
        <div style={{ fontFamily: FONT.b, fontSize: 22, color: C.ink3, marginTop: 20 }}>Mon–Fri · hold times vary</div>
      </div>
    </div>
  );
  if (tab === 2) return (
    <div style={{ position: 'absolute', inset: 0, padding: 80 }}>
      <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 40, color: C.ink }}>Front driveshaft, 4WD</div>
      <div style={{ marginTop: 30, display: 'inline-block', padding: '14px 26px', borderRadius: 999, background: '#fde8e8', color: C.red, fontFamily: FONT.h, fontWeight: 800, fontSize: 34 }}>Out of stock</div>
      <div style={{ fontFamily: FONT.b, fontSize: 28, color: C.ink3, marginTop: 26 }}>Backorder: 3–5 weeks</div>
    </div>
  );
  return (
    <div style={{ position: 'absolute', inset: 30, display: 'grid', gridTemplateColumns: '240px 300px 300px 160px', gridAutoRows: 52, border: `1px solid ${C.line}`, fontFamily: FONT.m, fontWeight: 600, fontSize: 22, color: C.ink2 }}>
      {['PART #', 'FITS', 'CROSS-REF', 'PRICE'].map((h) => <div key={h} style={{ background: '#e7f4ec', padding: '13px 16px', borderRight: `1px solid ${C.line}` }}>{h}</div>)}
      {Array.from({ length: 9 }).flatMap((_, r) => [`3194-${2100 + r * 13}`, '?', `?`, r % 3 ? '?' : '$ ?'].map((v, k) => <div key={r + '-' + k} style={{ padding: '13px 16px', borderTop: `1px solid ${C.line}`, borderRight: `1px solid ${C.line}`, color: v.includes('?') ? C.red : C.ink2 }}>{v}</div>))}
    </div>
  );
};
export const Hunt: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const clicks = CLICKS.hunt;
  const tab = clicks.filter((c) => f >= c).length;
  const swapAt = tab ? clicks[tab - 1] : 0, sw = enterSoft(f, swapAt, 14);
  const win = enter(f, 6);
  return (
    <SceneOut len={len('hunt')}>
      <Backdrop />
      <Words words={COPY.hunt} ats={[30, 110, 180]} size={L.wide ? 84 : L.square ? 62 : 76} y={L.titleY} colors={['#fff', '#fff', C.red]} />
      <Board w={1300} h={700} cam={[{ f: 0, x: 600, y: 360, s: 1.04 }, { f: 330, x: 720, y: 360, s: 1.09 }]}>
        <div style={{ position: 'absolute', inset: 0, transform: `translateY(${(1 - win) * 120}px) scale(${0.94 + win * 0.06})`, opacity: Math.min(1, win * 1.4) }}>
          <BrowserWindow w={1300} h={700} url={['supplier-catalog.pdf', 'supplier-a.com/driveshafts', 'supplier-b.com/parts/4wd', 'cross-ref.xlsx'][tab]} tabs={COPY.tabs} active={tab}>
            <div style={{ position: 'absolute', inset: 0, transform: `translateX(${(1 - sw) * 40}px)`, opacity: tab ? sw : 1 }}><HuntContent tab={tab} /></div>
          </BrowserWindow>
          <Cursor keys={[
            { f: 0, x: 950, y: 470 }, { f: 80, x: TAB_X(1) - 8, y: 46 }, { f: 92, x: TAB_X(1) + 3, y: 39 }, { f: 100, x: TAB_X(1), y: 36, click: true },
            { f: 128, x: 500, y: 82 }, { f: 142, x: 512, y: 77 }, { f: 162, x: TAB_X(2) - 6, y: 43 }, { f: 170, x: TAB_X(2), y: 37, click: true },
            { f: 200, x: 730, y: 88 }, { f: 214, x: 742, y: 83 }, { f: 234, x: TAB_X(3) - 6, y: 43 }, { f: 240, x: TAB_X(3), y: 37, click: true },
            { f: 330, x: 900, y: 380 },
          ]} />
        </div>
      </Board>
    </SceneOut>
  );
};

// ================= 3 · the new site =================
export const Site: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const wipe = ease(f, 0, 32, 100, 0, E.out);
  const s = press(f, CLICKS.site[0]);
  return (
    <SceneOut len={len('site')}>
      <Backdrop />
      <Kicker text="INTRODUCING THE NEW" y={L.titleY - 34} at={4} />
      <Kinetic text={COPY.siteUrl} at={10} size={L.wide ? 66 : 58} y={L.titleY + 28} />
      <Board w={1300} h={760} cam={[{ f: 0, x: 650, y: 380, s: 1 }, { f: 50, x: 650, y: 380, s: 1 }, { f: 80, x: 430, y: 370, s: 1.24 }, { f: 142, x: 440, y: 372, s: 1.24 }, { f: 178, x: 650, y: 395, s: 1 }, { f: 300, x: 650, y: 395, s: 1.03 }]}>
        <div style={{ position: 'absolute', inset: 0, clipPath: `inset(0 ${wipe}% 0 0 round 22px)` }}>
          <BrowserWindow w={1300} h={760} url={COPY.siteUrl}>
            {/* header */}
            <div style={{ height: 80, display: 'flex', alignItems: 'center', padding: '0 36px', gap: 34, borderBottom: `1px solid ${C.line}` }}>
              <Logo h={40} />
              {['Catalog', 'Driveshafts', 'Services', 'About'].map((t) => <span key={t} style={{ fontFamily: FONT.b, fontWeight: 600, fontSize: 21, color: C.ink2 }}>{t}</span>)}
              <span style={{ marginLeft: 'auto', padding: '12px 24px', borderRadius: 999, background: C.blue2, color: '#fff', fontFamily: FONT.h, fontWeight: 700, fontSize: 19 }}>Online builder</span>
            </div>
            {/* hero */}
            <div style={{ position: 'absolute', top: 80, left: 0, right: 0, height: 360, background: `linear-gradient(135deg, ${C.navy}, ${C.navy2})` }}>
              <div style={{ position: 'absolute', left: 60, top: 54, fontFamily: FONT.h, fontWeight: 800, fontSize: 50, lineHeight: 1.08, letterSpacing: -1.5, color: '#fff', width: 760 }}>
                {COPY.hero[0]}<br /><span style={{ color: '#ff5a5f' }}>{COPY.hero[1]}</span>
              </div>
              <div style={{ position: 'absolute', left: 60, top: 216, width: 740, height: 76, borderRadius: 999, background: '#fff', display: 'flex', alignItems: 'center', padding: '0 30px', gap: 16, transform: `scale(${s})`,
                boxShadow: f >= CLICKS.site[0] ? `0 0 0 5px rgba(111,156,240,.55)` : 'none' }}>
                <Icon name="search" size={30} color={C.ink3} />
                <span style={{ fontFamily: FONT.b, fontWeight: 500, fontSize: 28, color: f >= 84 ? C.ink : '#8a98b5' }}>{f >= 84 ? <Typed text={COPY.search} at={84} cps={6} /> : 'Part number, series or vehicle'}</span>
              </div>
              {/* stats */}
              <div style={{ position: 'absolute', left: 880, top: 48, display: 'grid', gap: 22 }}>
                {COPY.stats.map((st, i) => {
                  const p = enterSoft(f, 186 + i * 4);
                  const n = 'n' in st && st.n !== undefined ? Math.round(interpolate(f, [186, 246], [0, st.n], { ...clamp, easing: E.out })).toLocaleString('en-US') + st.suffix : (st as any).text;
                  return <div key={i} style={{ opacity: p, transform: `translateX(${(1 - p) * 30}px)` }}>
                    <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 44, color: '#fff', letterSpacing: -1, fontVariantNumeric: 'tabular-nums' }}>{n}</div>
                    <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 17, letterSpacing: 3, color: C.blueL, textTransform: 'uppercase' }}>{st.label}</div>
                  </div>;
                })}
              </div>
            </div>
            {/* category cards: the search results by type */}
            <div style={{ position: 'absolute', left: 60, top: 470, display: 'flex', gap: 24 }}>
              {COPY.categories.map(([t, ic], i) => {
                const p = enter(f, 150 + i * 4);
                return <div key={t} style={{ width: 275, height: 190, borderRadius: 20, background: C.paper, border: `1px solid ${C.line}`, opacity: Math.min(1, p * 1.4), transform: `translateY(${(1 - p) * 60}px) translateY(${drift(f, 2, 120, i)}px)`, padding: 26, boxSizing: 'border-box' }}>
                  <div style={{ width: 70, height: 70, borderRadius: 18, background: '#eaf1fd', display: 'grid', placeItems: 'center' }}><Icon name={ic} size={40} /></div>
                  <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 28, color: C.ink, marginTop: 18 }}>{t}</div>
                  <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 17, color: C.blue, marginTop: 4 }}>1310 SERIES</div>
                </div>;
              })}
            </div>
          </BrowserWindow>
          <Cursor keys={[{ f: 0, x: 1150, y: 700 }, { f: 60, x: 432, y: 372 }, { f: 70, x: 430, y: 370, click: true }, { f: 150, x: 470, y: 400 }, { f: 260, x: 1050, y: 640 }]} />
        </div>
      </Board>
    </SceneOut>
  );
};

// ================= 4 · complete driveshafts (vehicle finder) =================
const TILE = (c: number, r: number) => ({ x: 60 + c * 400 + 190, y: 64 + 220 + r * 130 + 55 });
export const Finder: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const clicks = CLICKS.finder;
  const step = clicks.filter((c) => f >= c + 12).length; // options swap 12 frames after each pick
  const picks = COPY.finderSteps;
  const pos = [TILE(2, 0), TILE(2, 0), TILE(2, 1), TILE(0, 0)];
  const resAt = clicks[3] + 20, res = enter(f, resAt);
  const sw = enterSoft(f, step ? clicks[step - 1] + 12 : 0, 16);
  return (
    <SceneOut len={len('finder')}>
      <Backdrop />
      <Kinetic text={COPY.finderTitle} at={4} size={L.wide ? 64 : 56} y={L.titleY} accent={['vehicle.']} maxW={L.W * 0.88} out={236} />
      <Board w={1300} h={720} cam={[{ f: 0, x: 650, y: 360, s: 1 }, { f: 200, x: 620, y: 345, s: 1.04 }, { f: 262, x: 650, y: 560, s: L.wide ? 1.12 : 1.0 }, { f: 360, x: 650, y: 560, s: L.wide ? 1.14 : 1.02 }]}>
        <BrowserWindow w={1300} h={720} url={`${COPY.siteUrl}/catalog/driveshafts`}>
          <div style={{ position: 'absolute', left: 60, top: 34, fontFamily: FONT.h, fontWeight: 800, fontSize: 44, color: C.ink, letterSpacing: -1 }}>Complete driveshafts</div>
          {/* the vehicle builds up in the chip row */}
          <div style={{ position: 'absolute', left: 60, top: 112, display: 'flex', gap: 20 }}>
            {picks.map((s, i) => {
              const at = clicks[i] + 4, p = enter(f, at);
              const done = f >= at;
              return <div key={s.k} style={{ width: 250, height: 70, borderRadius: 16, border: `2px solid ${done ? C.blue : C.line}`, background: done ? '#eaf1fd' : '#fff', display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: '0 20px', transform: `scale(${done ? 0.9 + p * 0.1 : 1})` }}>
                <span style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 15, letterSpacing: 3, color: C.ink3 }}>{s.k}</span>
                <span style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 24, color: done ? C.blue2 : '#b6bfcd' }}>{done ? s.pick : '—'}</span>
              </div>;
            })}
          </div>
          {/* options for the current step */}
          {step < 4 && (
            <div style={{ position: 'absolute', left: 60, top: 220, display: 'grid', gridTemplateColumns: 'repeat(3, 380px)', gap: '20px 20px', opacity: sw, transform: `translateX(${(1 - sw) * 50}px)` }}>
              {picks[step].options.map((o, i) => {
                const picked = o === picks[step].pick && f >= clicks[step];
                const s2 = o === picks[step].pick ? press(f, clicks[step]) : 1;
                return <div key={o} style={{ height: 110, borderRadius: 18, border: `2px solid ${picked ? C.blue : C.line}`, background: picked ? '#eaf1fd' : C.paper, display: 'grid', placeItems: 'center', fontFamily: FONT.h, fontWeight: 800, fontSize: 30, color: C.blue2, transform: `scale(${s2})` }}>{o}</div>;
              })}
            </div>
          )}
          {/* the result */}
          {f >= resAt && (
            <div style={{ position: 'absolute', left: 60, top: 230 + (1 - res) * 80, width: 1180, height: 330, borderRadius: 24, border: `2px solid ${C.line}`, background: '#fff', opacity: Math.min(1, res * 1.4), boxShadow: '0 30px 60px -30px rgba(11,26,51,.4)' }}>
              <div style={{ position: 'absolute', left: 40, top: 34, fontFamily: FONT.h, fontWeight: 800, fontSize: 34, color: C.ink }}>{COPY.result.vehicle}</div>
              <svg width="420" height="120" viewBox="0 0 420 120" style={{ position: 'absolute', left: 40, top: 110 }}>
                <rect x="70" y="44" width="280" height="32" rx="6" fill={C.blue} /><rect x="20" y="38" width="50" height="44" rx="8" fill="#8a98b5" /><rect x="350" y="38" width="50" height="44" rx="8" fill="#8a98b5" />
                <circle cx="20" cy="60" r="18" fill="#5c6879" /><circle cx="400" cy="60" r="18" fill="#5c6879" /><rect x="190" y="44" width="10" height="32" fill={C.red} />
              </svg>
              <div style={{ position: 'absolute', left: 520, top: 112 }}>
                <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 30, color: C.blue2 }}>{COPY.result.sku}</div>
                <div style={{ fontFamily: FONT.b, fontWeight: 600, fontSize: 26, color: C.ink2, marginTop: 6 }}>{COPY.result.name}</div>
              </div>
              <div style={{ position: 'absolute', right: 40, top: 100, textAlign: 'right' }}>
                <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 56, color: C.ink, letterSpacing: -1.5 }}>${COPY.result.price.toFixed(2)}</div>
                <div style={{ marginTop: 14, display: 'inline-block', padding: '16px 34px', borderRadius: 999, background: C.blue2, color: '#fff', fontFamily: FONT.h, fontWeight: 700, fontSize: 24 }}>Add to cart</div>
              </div>
            </div>
          )}
        </BrowserWindow>
        <Cursor keys={[
          { f: 0, x: 620, y: 640 }, { f: 40, x: pos[0].x - 6, y: pos[0].y + 8 }, { f: 50, x: pos[0].x, y: pos[0].y, click: true },
          { f: 88, x: pos[1].x + 14, y: pos[1].y + 26 }, { f: 102, x: pos[1].x + 4, y: pos[1].y + 4 }, { f: 110, x: pos[1].x, y: pos[1].y, click: true },
          { f: 150, x: pos[2].x - 12, y: pos[2].y - 20 }, { f: 162, x: pos[2].x - 3, y: pos[2].y - 4 }, { f: 170, x: pos[2].x, y: pos[2].y, click: true },
          { f: 210, x: pos[3].x + 50, y: pos[3].y + 24 }, { f: 222, x: pos[3].x + 6, y: pos[3].y + 3 }, { f: 230, x: pos[3].x, y: pos[3].y, click: true },
          { f: 300, x: 1040, y: 560 },
        ]} />
      </Board>
    </SceneOut>
  );
};

// ================= 5 · driveshaft designer =================
const LBL = { C1: { x: 650, y: 112 }, W1: { x: 650, y: 172 }, T1: { x: 650, y: 272 } } as const;
export const Designer: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const [c1, c2, c3] = CLICKS.designer;
  const val = (i: number, at: number) => { const v = COPY.dims[i].v; const n = Math.max(0, Math.min(v.length, Math.floor((f - at - 8) / 5))); return { s: v.slice(0, n), done: n === v.length, typing: f >= at && n < v.length }; };
  const vs = [val(0, c1), val(1, c2), val(2, c3)];
  const draw = ease(f, 6, 60, 0, 1, E.out);
  const sheet = enter(f, 4);
  const lab = (k: 'C1' | 'W1' | 'T1', i: number, at: number) => {
    const v = vs[i]; const pz = press(f, at); const active = f >= at;
    return <text key={k} x={LBL[k].x} y={k === 'T1' ? LBL[k].y + 12 : LBL[k].y - 4} textAnchor="middle" style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 36, fill: active ? C.ink : C.blue }} transform={`translate(${LBL[k].x} ${LBL[k].y}) scale(${pz}) translate(${-LBL[k].x} ${-LBL[k].y})`}>
      {active && v.s ? v.s : `{${k}}`}{v.typing ? '|' : ''}
    </text>;
  };
  return (
    <SceneOut len={len('designer')}>
      <Backdrop />
      <Words words={COPY.designerTitle} ats={[4, 16]} size={L.wide ? 76 : 64} y={L.titleY - 16} colors={['#fff', C.red]} />
      <Kicker text={COPY.designerNote.toUpperCase()} y={L.titleY + 58} at={24} />
      <Board w={1300} h={700} cam={[{ f: 0, x: 650, y: 350, s: 1 }, { f: 58, x: 650, y: 140, s: 1.3 }, { f: 140, x: 650, y: 150, s: 1.3 }, { f: 152, x: 650, y: 200, s: 1.3 }, { f: 220, x: 650, y: 205, s: 1.3 }, { f: 236, x: 650, y: 275, s: 1.3 }, { f: 290, x: 650, y: 280, s: 1.3 }, { f: 335, x: 650, y: 350, s: 1 }]}>
        <div style={{ position: 'absolute', inset: 0, borderRadius: 20, overflow: 'hidden', background: '#fff', opacity: Math.min(1, sheet * 1.4), transform: `scale(${0.95 + sheet * 0.05})`, boxShadow: '0 60px 120px -40px rgba(0,0,0,.75)' }}>
          <div style={{ height: 60, display: 'flex', alignItems: 'center', gap: 22, padding: '0 30px', borderBottom: `2px solid ${C.ink}`, fontFamily: FONT.m, fontWeight: 600, fontSize: 20, letterSpacing: 4 }}>
            <span style={{ color: C.ink }}>DRIVESHAFT DRAWING</span><span style={{ color: C.blue }}>01+01R</span><span style={{ marginLeft: 'auto', color: C.ink3, letterSpacing: 2 }}>One piece · NTS · inches</span>
          </div>
          {/* blueprint drawing (vector) */}
          <svg width="1300" height="380" viewBox="0 60 1300 380" style={{ position: 'absolute', top: 60, left: 0, background: 'linear-gradient(rgba(1,93,202,.06) 1px, transparent 1px) 0 0/32px 32px, linear-gradient(90deg, rgba(1,93,202,.06) 1px, transparent 1px) 0 0/32px 32px' }}>
            <g stroke={C.ink} strokeWidth="3" fill="#fff" style={{ opacity: draw }}>
              <rect x="260" y="230" width="780" height="84" />
              <path d="M190 226 h70 v92 h-70" /><path d="M1110 226 h-70 v92 h70" />
              <circle cx="186" cy="272" r="22" /><circle cx="1114" cy="272" r="22" />
              <path d="M150 238 h36 M150 306 h36 M1114 238 h36 M1114 306 h36" />
            </g>
            <g stroke={C.blue} strokeWidth="2.5" fill={C.blue} style={{ opacity: ease(f, 20, 50, 0, 1) }}>
              <line x1="150" y1="112" x2={150 + 1000 * draw} y2="112" /><line x1="150" y1="96" x2="150" y2="230" strokeWidth="1.5" /><line x1="1150" y1="96" x2="1150" y2="230" strokeWidth="1.5" />
              <line x1="260" y1="172" x2={260 + 780 * draw} y2="172" /><line x1="260" y1="158" x2="260" y2="230" strokeWidth="1.5" /><line x1="1040" y1="158" x2="1040" y2="230" strokeWidth="1.5" />
              <line x1="300" y1="234" x2="300" y2="310" />
              <polygon points="150,112 168,105 168,119" /><polygon points="1150,112 1132,105 1132,119" />
              <polygon points="260,172 278,165 278,179" /><polygon points="1040,172 1022,165 1022,179" />
              <polygon points="300,234 293,250 307,250" /><polygon points="300,310 293,294 307,294" />
            </g>
            <rect x="560" y="74" width="180" height="48" fill="#fff" opacity="0.9" /><rect x="560" y="134" width="180" height="48" fill="#fff" opacity="0.9" />
            {lab('C1', 0, c1)}{lab('W1', 1, c2)}{lab('T1', 2, c3)}
          </svg>
          {/* measurement table fills row by row */}
          <div style={{ position: 'absolute', left: 30, right: 30, top: 460, fontFamily: FONT.b, fontSize: 24 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '120px 1fr 160px', fontFamily: FONT.m, fontWeight: 600, fontSize: 17, letterSpacing: 3, color: C.ink3, paddingBottom: 10, borderBottom: `1px solid ${C.line}` }}><span>REF</span><span>DESCRIPTION</span><span style={{ textAlign: 'right' }}>INCHES</span></div>
            {COPY.dims.map((d, i) => {
              const p = enterSoft(f, [c1, c2, c3][i] + 30);
              return <div key={d.ref} style={{ display: 'grid', gridTemplateColumns: '120px 1fr 160px', padding: '14px 0', borderBottom: `1px solid ${C.line}`, alignItems: 'center' }}>
                <span style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 26, color: C.blue }}>{d.ref}</span><span style={{ color: C.ink2 }}>{d.desc}</span>
                <span style={{ textAlign: 'right', fontFamily: FONT.m, fontWeight: 600, fontSize: 28, color: C.ink, opacity: p, transform: `translateX(${(1 - p) * 20}px)` }}>{vs[i].done ? d.v : ''}</span>
              </div>;
            })}
          </div>
        </div>
        <Cursor keys={[
          { f: 0, x: 1100, y: 600 }, { f: 60, x: LBL.C1.x + 70, y: LBL.C1.y - 2 }, { f: 70, x: LBL.C1.x + 60, y: LBL.C1.y - 8, click: true },
          { f: 140, x: LBL.W1.x + 74, y: LBL.W1.y - 2 }, { f: 150, x: LBL.W1.x + 60, y: LBL.W1.y - 8, click: true },
          { f: 220, x: LBL.T1.x + 72, y: LBL.T1.y - 6 }, { f: 230, x: LBL.T1.x + 50, y: LBL.T1.y - 14, click: true },
          { f: 320, x: 1120, y: 620 },
        ]} />
      </Board>
    </SceneOut>
  );
};

// ================= 6 · UDS Assistant (AI agent) =================
export const Assistant: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const [cIn, cSend] = CLICKS.assistant;
  const tall = L.tall;
  const W = tall ? 760 : 1400, Hh = tall ? 1230 : 720;
  const panel = { x: tall ? 0 : 0, y: 0, w: tall ? 760 : 640, h: tall ? 600 : 720 };
  const cardsAt = tall ? { x: 70, y: 640 } : { x: 720, y: 70 };
  const prompt = { x: panel.x + 30, y: panel.h - 106, w: panel.w - 60 };
  const sendX = prompt.x + prompt.w - 44, sendY = prompt.y + 44;
  const typedAt = cIn + 10;
  const sent = f >= cSend + 6;
  const pz = press(f, cSend);
  const pin = enter(f, 4);
  return (
    <SceneOut len={len('assistant')}>
      <Backdrop />
      <Words words={COPY.assistantTitle} ats={[4, 14]} size={L.wide ? 76 : 64} y={L.titleY} colors={['#fff', C.red]} />
      <Board w={W} h={Hh} cam={[{ f: 0, x: W / 2, y: Hh / 2, s: 1 }, { f: 30, x: prompt.x + prompt.w / 2, y: prompt.y + 44, s: 1.3 }, { f: cSend + 14, x: prompt.x + prompt.w / 2, y: prompt.y + 44, s: 1.3 }, { f: cSend + 52, x: W / 2, y: Hh / 2, s: 1 }, { f: 420, x: W / 2, y: Hh / 2, s: 1.03 }]}>
        <div style={{ position: 'absolute', left: panel.x, top: panel.y, width: panel.w, height: panel.h, borderRadius: 28, overflow: 'hidden', background: C.paper, opacity: Math.min(1, pin * 1.4), transform: `scale(${0.94 + pin * 0.06})`, boxShadow: '0 60px 120px -40px rgba(0,0,0,.8)' }}>
          <div style={{ height: 100, background: `linear-gradient(120deg, ${C.navy}, ${C.navy2})`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 28px' }}>
            <div style={{ width: 58, height: 58, borderRadius: 16, background: 'linear-gradient(135deg,#14b8a6,#1246ab)', display: 'grid', placeItems: 'center' }}><Icon name="search" size={30} color="#fff" /></div>
            <div><div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 30, color: '#fff' }}>UDS Assistant</div><div style={{ fontFamily: FONT.b, fontSize: 19, color: '#b7c2d9' }}>Parts, fitment, shipping & your orders</div></div>
          </div>
          <div style={{ padding: 28 }}>
            {!sent && <div style={{ fontFamily: FONT.h, fontWeight: 700, fontSize: 30, color: C.ink, opacity: 1 - enterSoft(f, cSend, 10) }}>Hi, how can I help?</div>}
            {sent && (() => { const p = enter(f, cSend + 6); return <div style={{ marginLeft: 'auto', maxWidth: 470, padding: '18px 22px', borderRadius: '22px 22px 6px 22px', background: C.blue2, color: '#fff', fontFamily: FONT.b, fontSize: 24, lineHeight: 1.35, opacity: Math.min(1, p * 1.4), transform: `translateY(${(1 - p) * 30}px)` }}>{COPY.prompt}</div>; })()}
          </div>
          <div style={{ position: 'absolute', left: 28, right: 28, bottom: 132, fontFamily: FONT.b, fontWeight: 600, fontSize: 19, color: C.blue2, opacity: ease(f, 60, 80, 0, 1) }}>Talk to a person →</div>
        </div>
        {/* prompt bar */}
        <div style={{ position: 'absolute', left: prompt.x, top: prompt.y, width: prompt.w, height: 88, borderRadius: 22, background: '#fff', border: `3px solid ${f >= cIn ? C.blue : C.line}`, display: 'flex', alignItems: 'center', padding: '0 100px 0 24px', boxSizing: 'border-box', fontFamily: FONT.b, fontSize: 24, color: C.ink, opacity: Math.min(1, pin * 1.4) }}>
          <span style={{ whiteSpace: 'nowrap', overflow: 'hidden' }}>{sent ? <span style={{ color: '#8a98b5' }}>Ask about a part, your truck or an order</span> : f >= typedAt ? <Typed text={COPY.prompt} at={typedAt} cps={2} /> : <span style={{ color: '#8a98b5' }}>Ask about a part, your truck or an order</span>}</span>
          <div style={{ position: 'absolute', right: 12, top: 10, width: 64, height: 64, borderRadius: 18, background: C.blue2, display: 'grid', placeItems: 'center', transform: `scale(${pz})` }}>
            <svg width="30" height="30" viewBox="0 0 24 24"><path d="M5 12h13M13 6l6 6-6 6" stroke="#fff" strokeWidth="2.6" fill="none" strokeLinecap="round" strokeLinejoin="round" /></svg>
          </div>
        </div>
        {/* agent cards: in one by one, they land, then each pulses as it starts working */}
        <div style={{ position: 'absolute', left: cardsAt.x, top: cardsAt.y, display: 'grid', gap: 20 }}>
          {COPY.agents.map((a, i) => <AgentCard key={a.t} {...a} w={620} enterAt={cSend + 50 + i * 24} workAt={cSend + 150 + i * 14} />)}
        </div>
        <Cursor show={[0, cSend + 40]} keys={[{ f: 0, x: W * 0.8, y: Hh * 0.3 }, { f: cIn - 10, x: prompt.x + 120, y: prompt.y + 50 }, { f: cIn, x: prompt.x + 110, y: prompt.y + 46, click: true }, { f: cSend - 12, x: sendX - 10, y: sendY + 8 }, { f: cSend, x: sendX, y: sendY, click: true }, { f: cSend + 40, x: sendX + 40, y: sendY + 60 }]} />
      </Board>
    </SceneOut>
  );
};

// ================= 7 · how to order =================
export const Order: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const tall = L.tall;
  const W = tall ? 940 : 1520, Hh = tall ? 1150 : 640;
  const checks = [24, 36, 48, 60], stepChecks = [150, 156, 162, 168];
  return (
    <SceneOut len={len('order')}>
      <Backdrop />
      <Kinetic text={COPY.orderTitle} at={4} size={L.wide ? 70 : 60} y={L.titleY} accent={['four']} maxW={L.W * 0.88} />
      <Board w={W} h={Hh} cam={[{ f: 0, x: W / 2, y: Hh / 2 - 20, s: 1 }, { f: 360, x: W / 2, y: Hh / 2 + 20, s: 1.04 }]}>
        <div style={{ position: 'absolute', left: tall ? 10 : 0, top: tall ? 0 : 88, display: 'grid', gridTemplateColumns: tall ? '450px 450px' : '560px', gap: 16 }}>
          {COPY.agents.map((a, i) => <AgentCard key={a.t} {...a} compact w={tall ? 450 : 560} enterAt={-40} workAt={-30} doneAt={-1} checkAt={checks[i]} />)}
        </div>
        <div style={{ position: 'absolute', left: tall ? 0 : 640, top: tall ? 260 : 0, width: tall ? 940 : 880, display: 'grid', gap: 18 }}>
          {COPY.steps.map((s, i) => {
            const p = enter(f, 70 + i * 10);
            return <div key={s.t} style={{ height: 142, borderRadius: 24, background: '#fff', display: 'flex', alignItems: 'center', gap: 26, padding: '0 30px', boxSizing: 'border-box', opacity: Math.min(1, p * 1.4), transform: `translateX(${(1 - p) * 80}px) translateY(${drift(f, 2, 130, i)}px)`, boxShadow: '0 24px 50px -26px rgba(0,0,0,.6)' }}>
              <div style={{ width: 70, height: 70, borderRadius: 35, background: C.blue2, color: '#fff', display: 'grid', placeItems: 'center', fontFamily: FONT.h, fontWeight: 800, fontSize: 34, flex: 'none' }}>{i + 1}</div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 36, color: C.ink }}>{s.t}</div>
                <div style={{ fontFamily: FONT.b, fontWeight: 500, fontSize: 23, color: C.ink3, marginTop: 4 }}>{s.d}</div>
              </div>
              <div style={{ flex: 'none' }}><Check at={stepChecks[i]} size={56} /></div>
            </div>;
          })}
        </div>
      </Board>
    </SceneOut>
  );
};

// ================= 8 · My Account =================
export const Account: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const tall = L.tall;
  const W = tall ? 900 : 1300, Hh = tall ? 1180 : 700;
  const zero = 180; // counter hits zero (counter runs 120 → 180)
  const dots = [20, 50, 80, 110];
  const buy = CLICKS.account[0];
  const order = tall ? { x: 0, y: 290, w: 900 } : { x: 0, y: 290, w: 800 };
  const side = tall ? { x: 0, y: 690 } : { x: 840, y: 290 };
  const buyPos = tall ? { x: 230, y: side.y + 50 } : { x: side.x + 230, y: side.y + 50 };
  const sheet = enter(f, 4);
  return (
    <SceneOut len={len('account')} scaleTo={0.8}>
      <Backdrop />
      {f >= zero - 2 && <Kinetic text={COPY.payoff} at={zero} size={L.wide ? 80 : 68} y={L.titleY} accent={['road.']} />}
      <Board w={W} h={Hh} cam={[{ f: 0, x: order.x + order.w / 2, y: order.y + 230, s: 1.2 }, { f: zero, x: order.x + order.w / 2, y: order.y + 230, s: 1.2 }, { f: zero + 60, x: W / 2, y: Hh / 2, s: 0.97 }, { f: 300, x: W / 2, y: Hh / 2, s: 0.98 }]}>
        <div style={{ position: 'absolute', inset: 0, opacity: Math.min(1, sheet * 1.4) }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 22 }}>
            <div style={{ width: 92, height: 92, borderRadius: 46, background: C.blue2, color: '#fff', display: 'grid', placeItems: 'center', fontFamily: FONT.h, fontWeight: 800, fontSize: 36 }}>AM</div>
            <div><div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 44, color: '#fff' }}>{COPY.account.hello}</div><div style={{ fontFamily: FONT.b, fontSize: 24, color: '#b7c2d9' }}>Your orders, addresses and saved builds in one place.</div></div>
          </div>
          <div style={{ position: 'absolute', top: 140, left: 0, display: 'flex', gap: 20 }}>
            {[['1', 'Open order'], ['4', 'Orders placed'], ['1', 'Saved address']].map(([n, l], i) => (
              <div key={l} style={{ width: tall ? 286 : 250, height: 120, borderRadius: 20, background: 'rgba(255,255,255,.08)', border: '1px solid rgba(255,255,255,.18)', padding: '18px 24px', boxSizing: 'border-box', transform: `translateY(${drift(f, 2, 140, i)}px)` }}>
                <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 44, color: '#fff' }}>{n}</div><div style={{ fontFamily: FONT.b, fontSize: 21, color: '#b7c2d9' }}>{l}</div>
              </div>
            ))}
          </div>
          {/* order card with the tracker */}
          <div style={{ position: 'absolute', left: order.x, top: order.y, width: order.w, height: 360, borderRadius: 26, background: '#fff', padding: 34, boxSizing: 'border-box', boxShadow: '0 40px 80px -40px rgba(0,0,0,.7)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
              <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 32, color: C.ink }}>Latest order</div>
              <div style={{ fontFamily: FONT.h, fontWeight: 800, fontSize: 40, color: C.ink }}>{COPY.account.total}</div>
            </div>
            <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 24, color: C.blue2, marginTop: 10 }}>{COPY.account.item}</div>
            <div style={{ position: 'absolute', left: 60, right: 60, top: 196 }}>
              <div style={{ position: 'absolute', left: 20, right: 20, top: 20, height: 6, background: '#e3e9f3', borderRadius: 3 }} />
              <div style={{ position: 'absolute', left: 20, top: 20, height: 6, background: C.green, borderRadius: 3, width: `calc((100% - 40px) * ${interpolate(f, [dots[0], dots[3]], [0, 1], { ...clamp, easing: E.inOut })})` }} />
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                {COPY.account.track.map((t, i) => { const p = enter(f, dots[i], 20); const on = f >= dots[i]; return (
                  <div key={t} style={{ textAlign: 'center', width: 46 }}>
                    <div style={{ width: 46, height: 46, borderRadius: 23, margin: '0 auto', background: on ? C.green : '#fff', border: `4px solid ${on ? C.green : '#cfd7e4'}`, boxSizing: 'border-box', transform: `scale(${on ? 0.8 + p * 0.2 : 1})`, display: 'grid', placeItems: 'center' }}>{on ? <Icon name="check" size={28} color="#fff" /> : null}</div>
                    <div style={{ fontFamily: FONT.b, fontWeight: 600, fontSize: 21, color: on ? C.ink : C.ink3, marginTop: 12, whiteSpace: 'nowrap', transform: 'translateX(-30%)' }}>{t}</div>
                  </div>); })}
              </div>
            </div>
          </div>
          {/* buy again, address, links */}
          <div style={{ position: 'absolute', left: side.x, top: side.y, width: tall ? 900 : 460, display: 'grid', gap: 18 }}>
            <div style={{ height: 100, borderRadius: 999, background: C.blue2, color: '#fff', display: 'grid', placeItems: 'center', fontFamily: FONT.h, fontWeight: 800, fontSize: 32, transform: `scale(${press(f, buy)})`, boxShadow: '0 20px 40px -14px rgba(18,70,171,.9)' }}>Buy again</div>
            <div style={{ borderRadius: 22, background: 'rgba(255,255,255,.08)', border: '1px solid rgba(255,255,255,.18)', padding: '20px 26px', fontFamily: FONT.b, fontSize: 22, color: '#d6def0', lineHeight: 1.45 }}>
              <div style={{ fontFamily: FONT.m, fontWeight: 600, fontSize: 17, letterSpacing: 3, color: C.blueL }}>SHIPS TO</div>Alex Morgan · Saved address
            </div>
            <div style={{ display: 'flex', gap: 24, fontFamily: FONT.b, fontWeight: 600, fontSize: 22, color: C.blueL }}><span>Invoice (PDF)</span><span>Returns</span><span>Tracking</span></div>
          </div>
        </div>
        <Cursor show={[100, 240]} keys={[{ f: 100, x: buyPos.x + 260, y: buyPos.y + 220 }, { f: buy - 10, x: buyPos.x + 12, y: buyPos.y + 10 }, { f: buy, x: buyPos.x, y: buyPos.y, click: true }, { f: 230, x: buyPos.x + 120, y: buyPos.y + 160 }]} />
      </Board>
    </SceneOut>
  );
};

// ================= 9 · logo + CTA =================
export const LogoCTA: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const w = L.wide ? 1100 : 900, h = w * 125 / 781;
  const mark = enter(f, 10, 34);
  const wipe = ease(f, 34, 74, 0, 1, E.out);
  const push = ease(f, 0, 300, 1, 1.05);
  const tag = enter(f, 92), cta = enter(f, 112);
  const markW = w * 285 / 781;
  return (
    <AbsoluteFill>
      <Backdrop />
      <AbsoluteFill style={{ transform: `scale(${push})` }}>
        <div style={{ position: 'absolute', left: L.cx - w / 2, top: L.cy - h / 2 - (L.tall ? 90 : 70) + drift(f, 3, 180), width: w, height: h }}>
          {/* white plate so the logo reads exactly as on the site */}
          <div style={{ position: 'absolute', left: -50, top: -36, right: -50, bottom: -36, borderRadius: 36, background: '#fff', opacity: Math.min(1, mark * 1.2), transform: `scale(${0.9 + mark * 0.1})`, boxShadow: '0 50px 120px -40px rgba(0,0,0,.85), 0 0 120px rgba(111,156,240,.35)' }} />
          <div style={{ position: 'absolute', left: 0, top: 0, width: markW, height: h, overflow: 'hidden', transform: `scale(${mark})`, transformOrigin: '50% 50%' }}><Logo h={h} /></div>
          <div style={{ position: 'absolute', left: markW, top: 0, width: (w - markW) * wipe, height: h, overflow: 'hidden' }}>
            <div style={{ marginLeft: -markW }}><Logo h={h} /></div>
          </div>
        </div>
        <div style={{ position: 'absolute', left: L.cx, top: L.cy + (L.tall ? 110 : 120), transform: `translate(-50%, -50%) translateY(${(1 - tag) * 30}px)`, opacity: Math.min(1, tag * 1.4), width: L.W * 0.88, textAlign: 'center', fontFamily: FONT.h, fontWeight: 700, fontSize: L.wide ? 46 : 42, color: '#fff' }}>{COPY.tagline}</div>
        <div style={{ position: 'absolute', left: L.cx, top: L.cy + (L.tall ? 250 : 230), transform: `translate(-50%, -50%) scale(${0.85 + cta * 0.15})`, opacity: Math.min(1, cta * 1.4), padding: '24px 48px', borderRadius: 999, background: C.red, whiteSpace: 'nowrap',
          fontFamily: FONT.h, fontWeight: 800, fontSize: L.wide ? 38 : 34, color: '#fff', boxShadow: `0 0 ${36 + drift(f, 12, 60)}px rgba(207,49,48,.85)` }}>{COPY.cta}</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
