import React from 'react';
import { AbsoluteFill, Sequence, useCurrentFrame } from 'remotion';
import { CameraMotionBlur } from '@remotion/motion-blur';
import { useFonts } from '../launch/theme';
import { W, H, T, T2, SEC, ORDER, TRANS, Key, at, len, TOTAL } from './timeline';
import { OPEN } from './cues';
import { Background, Particles, BlurDefs, Streaks, E, P, mbx } from './fx';
import { Open, Home, Search, Chat, Vehicle, Builder, Designer, Checkout, Account, End } from './scenes';

export { TOTAL };
const COMP: Record<Key, React.FC> = { open: Open, home: Home, search: Search, chat: Chat, vehicle: Vehicle, builder: Builder, designer: Designer, checkout: Checkout, account: Account, end: End };

// Scene wrapper: handles the transition INTO this scene (whip-pan / cube) and OUT of it (the next scene's type).
// Both scenes render for T frames around the cut; nothing ever crossfades.
const Scene: React.FC<{ k: Key }> = ({ k }) => {
  const lf = useCurrentFrame(); // 0 = cut - T2
  const i = ORDER.indexOf(k), next = ORDER[i + 1], tin = TRANS[k], tout = next ? TRANS[next] : 'none';
  const L = len(k), outStart = L; // local frame where the out-transition starts (cut - T2 of the next scene)
  const C2 = COMP[k];
  let style: React.CSSProperties = {};
  if (tin === 'whip' && lf < T) { const p = P(lf, 0, T, E.out); const v = (1 - p); style = { transform: `translateX(${v * W * 1.15}px)`, filter: mbx(v * 60) }; }
  if (tin === 'cube' && lf < T) { const p = P(lf, 0, T, E.io); style = { transformOrigin: '0% 50%', transform: `translateX(${(1 - p) * W}px) rotateY(${(1 - p) * 90}deg)`, filter: mbx((1 - Math.abs(p * 2 - 1)) * 20) }; }
  if (tout === 'whip' && lf >= outStart) { const p = P(lf, outStart, outStart + T, E.in); style = { transform: `translateX(${-p * W * 1.15}px)`, filter: mbx(p * 60) }; }
  if (tout === 'cube' && lf >= outStart) { const p = P(lf, outStart, outStart + T, E.io); style = { transformOrigin: '100% 50%', transform: `translateX(${-p * W}px) rotateY(${-p * 90}deg)`, filter: mbx((1 - Math.abs(p * 2 - 1)) * 20) }; }
  return (
    <AbsoluteFill style={{ perspective: 2200 }}>
      <AbsoluteFill style={style}><C2 /></AbsoluteFill>
    </AbsoluteFill>
  );
};

export const Reel3: React.FC = () => {
  useFonts();
  const f = useCurrentFrame();
  const blue = f >= at('builder') - T2 && f < at('checkout') - T2;
  const pulses = ORDER.filter((k) => TRANS[k] !== 'none').map((k) => at(k));
  return (
    <AbsoluteFill style={{ background: '#071226', overflow: 'hidden' }}>
      <BlurDefs />
      <Background pulses={[...pulses, at('open') + OPEN.flash]} blue={blue} />
      <Particles n={260} speed={blue ? 1.3 : 1} />
      {/* open scene: plain, then the dive wrapped in camera motion blur (4 sub-frames) */}
      <Sequence from={0} durationInFrames={OPEN.dive}><Open base={0} /></Sequence>
      <Sequence from={OPEN.dive} durationInFrames={SEC.open[1] - OPEN.dive + T2}>
        <CameraMotionBlur samples={4} shutterAngle={200}><Open base={OPEN.dive} /></CameraMotionBlur>
      </Sequence>
      {ORDER.filter((k) => k !== 'open').map((k) => (
        <Sequence key={k} from={at(k) - T2} durationInFrames={len(k) + T + (k === 'end' ? 40 : 0)} layout="none"><Scene k={k} /></Sequence>
      ))}
      {ORDER.filter((k) => TRANS[k] === 'whip' || TRANS[k] === 'cube').map((k) => <Streaks key={k} at={at(k) - T2 - 2} dir={-1} dur={T + 6} />)}
      <AbsoluteFill style={{ background: 'radial-gradient(120% 90% at 50% 50%, rgba(0,0,0,0) 58%, rgba(0,0,0,.45))', pointerEvents: 'none' }} />
    </AbsoluteFill>
  );
};
