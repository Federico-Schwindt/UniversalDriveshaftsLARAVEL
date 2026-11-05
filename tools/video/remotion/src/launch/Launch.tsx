import React from 'react';
import { AbsoluteFill, Audio, Sequence, staticFile, useCurrentFrame, interpolate } from 'remotion';
import { COLORS as C, COPY, SCENES, CLICKS, POPS, TOTAL } from './config';
import { useFonts, clamp, useLayout } from './theme';
import { Counter } from './components';
import { Hook, Hunt, Site, Finder, Designer, Assistant, Order, Account, LogoCTA } from './scenes';

export { TOTAL };

const at = (k: keyof typeof SCENES) => SCENES[k][0];
const dur = (k: keyof typeof SCENES) => SCENES[k][1] - SCENES[k][0];

// the "hours down" counter rides over the hook + problem, then comes back in My Account and hits zero
const CounterTrack: React.FC = () => {
  const f = useCurrentFrame(); const L = useLayout();
  const sc = L.square ? 0.68 : 1;
  const a = interpolate(f, [56, 72, 586, 600], [0, 1, 1, 0], clamp);
  const b = interpolate(f, [at('account') + 30, at('account') + 46, at('account') + 285, at('account') + 300], [0, 1, 1, 0], clamp);
  if (a > 0) return <AbsoluteFill style={{ opacity: a }}><Counter from={COPY.counterFrom} to={COPY.counterTo} at={70} label={COPY.counterLabel} scale={sc} /></AbsoluteFill>;
  if (b > 0) return <AbsoluteFill style={{ opacity: b }}><Counter from={COPY.counterTo} to={0} at={at('account') + 120} cool={at('account') + 180} label={COPY.counterLabel} scale={sc} /></AbsoluteFill>;
  return null;
};

const Sfx: React.FC = () => (
  <>
    <Audio src={staticFile('launch-music.wav')} volume={(f) => interpolate(f, [0, 30, TOTAL - 120, TOTAL], [0, 0.55, 0.55, 0], clamp)} />
    {(Object.keys(CLICKS) as (keyof typeof CLICKS)[]).flatMap((k) => CLICKS[k].map((c) => (
      <Sequence key={`${k}-${c}`} from={at(k) + c} durationInFrames={30}><Audio src={staticFile('launch-click.wav')} volume={0.5} /></Sequence>
    )))}
    {POPS.order.map((c) => <Sequence key={'pop' + c} from={at('order') + c} durationInFrames={30}><Audio src={staticFile('launch-pop.wav')} volume={0.32} /></Sequence>)}
    <Sequence from={at('account') + 180} durationInFrames={60}><Audio src={staticFile('launch-pop.wav')} volume={0.45} /></Sequence>
  </>
);

export const Launch: React.FC = () => {
  useFonts();
  const s = (k: keyof typeof SCENES, C2: React.FC) => <Sequence key={k} from={at(k)} durationInFrames={dur(k)}><C2 /></Sequence>;
  return (
    <AbsoluteFill style={{ background: C.navy, overflow: 'hidden' }}>
      {s('hook', Hook)}{s('hunt', Hunt)}{s('site', Site)}{s('finder', Finder)}{s('designer', Designer)}
      {s('assistant', Assistant)}{s('order', Order)}{s('account', Account)}{s('logo', LogoCTA)}
      <CounterTrack />
      <Sfx />
    </AbsoluteFill>
  );
};
