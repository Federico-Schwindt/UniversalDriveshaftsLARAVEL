import React from 'react';
import { continueRender, delayRender, Easing, interpolate, staticFile, useVideoConfig } from 'remotion';

export const FONT = { h: 'LPoppins, sans-serif', b: 'LHanken, sans-serif', m: 'LMono, monospace' };

// Fonts load before frame 0: delayRender until every face is in document.fonts and fonts.ready resolves.
let fontsPromise: Promise<void> | null = null;
const loadFonts = () => fontsPromise ??= Promise.all([
  new FontFace('LPoppins', `url(${staticFile('poppins-800.woff2')})`, { weight: '800' }),
  new FontFace('LPoppins', `url(${staticFile('poppins-700.woff2')})`, { weight: '700' }),
  new FontFace('LHanken', `url(${staticFile('hankengrotesk-var.woff2')})`, { weight: '100 900' }),
  new FontFace('LMono', `url(${staticFile('ibmplexmono-600.woff2')})`, { weight: '600' }),
].map((f) => f.load().then((x) => { (document.fonts as any).add(x); }))).then(() => document.fonts.ready).then(() => undefined);

export const useFonts = () => {
  const [h] = React.useState(() => delayRender('launch fonts', { timeoutInMilliseconds: 60000 }));
  React.useEffect(() => { loadFonts().then(() => continueRender(h)).catch(() => continueRender(h)); }, [h]);
};

// ---- easing (skill: nothing linear; entrances overshoot; exits faster, ease-in; camera easy-ease) ----
export const E = {
  overshoot: Easing.bezier(0.34, 1.56, 0.64, 1),
  out: Easing.bezier(0.16, 1, 0.3, 1),
  in: Easing.bezier(0.55, 0, 0.75, 0.2),
  inOut: Easing.bezier(0.65, 0, 0.35, 1),
};
export const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
export const ENTER = 30, EXIT = 18; // exit ≈ 60% of entrance
export const enter = (f: number, at: number, dur = ENTER) => interpolate(f, [at, at + dur], [0, 1], { ...clamp, easing: E.overshoot });
export const enterSoft = (f: number, at: number, dur = ENTER) => interpolate(f, [at, at + dur], [0, 1], { ...clamp, easing: E.out });
export const exit = (f: number, at: number, dur = EXIT) => interpolate(f, [at, at + dur], [0, 1], { ...clamp, easing: E.in });
export const ease = (f: number, a: number, b: number, from: number, to: number, e = E.inOut) => interpolate(f, [a, b], [from, to], { ...clamp, easing: e });
// click press: 1 → 0.96 → 1 over 8 frames
export const press = (f: number, at: number) => (f >= at && f <= at + 8 ? 1 - 0.04 * Math.sin(((f - at) / 8) * Math.PI) : 1);
// golden rule: nothing ever fully stops
export const drift = (f: number, amp = 4, period = 140, phase = 0) => Math.sin((f / period) * Math.PI * 2 + phase) * amp;

// ---- layout: everything from the canvas centre so 16:9, 9:16 and 1:1 re-render the same scenes ----
export const useLayout = () => {
  const { width: W, height: H } = useVideoConfig();
  const tall = H > W * 1.2, wide = W > H * 1.2;
  const margin = Math.min(W, H) * 0.05;
  // the board (main UI of a scene) is scaled uniformly to fit the space left below the title
  const fit = (bw: number, bh: number, hShare = wide ? 0.66 : tall ? 0.56 : 0.6) => Math.min(1, (W * (tall ? 0.96 : 0.9)) / bw, (H * hShare) / bh);
  const titleY = wide ? -H * 0.37 : tall ? -H * 0.32 : -H * 0.38;
  const boardY = wide ? H * 0.08 : tall ? H * 0.07 : H * 0.09;
  return { W, H, tall, wide, square: !tall && !wide, margin, fit, titleY, boardY, cx: W / 2, cy: H / 2 };
};
