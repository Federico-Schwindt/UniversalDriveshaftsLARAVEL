// Scene-local event timings (frames from the scene's first beat) shared by the scenes and the audio mixer.
// Pure data: no React here, so `node --experimental-strip-types` can import it for the mix and the quality gate.
import { at, SEC, beat, click, tick, pop, whoosh, riser, impact, thump, sfx, SFX, TRANS, ORDER, T2, len } from './timeline.ts';

export const OPEN = { line: 0, glow: 36, laptop: 70, logo: 120, scrollStart: 200, dive: 410, flash: 478, title: 250, sweep2: 320 };
export const HOME = { land: 0, float: 60, nav: 110, icons: 175, hero: 240, vehicle: 310, toSearch: 380, click: 440 };
export const SEARCH = { typed: [70, 84, 98, 112] as number[], results: 130, zoom: 160, callout: 200, hover: 300, add: 380, out: 460 };
export const CHAT = { open: 60, zoom: 120, typeStart: 160, typeStep: 7, nType: 24, send: 350, dots: 366, answer: 430, reveal: 450, lineDur: 36, cards: [590, 616] as number[], callout: 660 };
export const VEH = { card: 0, cardOut: 56, page: 60, make: { open: 130, pick: 190, set: 215 }, year: { open: 290, pick: 350, set: 375 }, model: { open: 440, pick: 500, set: 525 }, results: 560, rowsIn: 580, snap: 700, hover: 760, open: 820, product: 840, price: 900, ship: 960 };
export const BLD = { card: 0, cardOut: 56, page: 60, stepDur: 150, steps: [
  { shot: 'bld-0a', click: [352, 769] as [number, number], text: 'Start a custom build', sub: 'step 1 · build type', focus: [500, 700] as [number, number], z: 1.25 },
  { shot: 'bld-1a', click: [543, 691] as [number, number], text: 'Pick your duty rating', sub: 'step 2 · light / medium', focus: [560, 720] as [number, number], z: 1.3 },
  { shot: 'bld-2a', click: [543, 776] as [number, number], text: 'Choose the series', sub: 'step 3 · 1350 series', focus: [560, 720] as [number, number], z: 1.3 },
  { shot: 'bld-3a', click: [352, 780] as [number, number], text: 'Choose the material', sub: 'steel tube', focus: [520, 720] as [number, number], z: 1.3 },
  { shot: 'bld-4a', click: [751, 735] as [number, number], text: 'Choose the front yoke', sub: 'weld yoke · open u-joint', focus: [600, 720] as [number, number], z: 1.25 },
  { shot: 'bld-5a', click: [415, 735] as [number, number], text: 'Choose the rear yoke', sub: 'weld yoke · open u-joint', focus: [600, 720] as [number, number], z: 1.25 },
  { shot: 'bld-6a', click: [700, 860] as [number, number], text: 'Set the length', sub: 'A1 52.25" · C1 48.5"', focus: [700, 700] as [number, number], z: 1.2 },
], chip: 112, ready: 60 + 150 * 7 + 20, price: 370 };
export const DSG = { card: 0, cardOut: 56, page: 60, stateDur: 18, states: 16, typeStart: 130, lift: 800, rows: [560, 615, 670] as number[], share: 730, saved: 860, dims: [
  { from: 1, to: 5, text: 'C1 · overall length, end to end', value: '52.25"', y: 288 },
  { from: 6, to: 9, text: 'W1 · tube length, weld to weld', value: '48.5"', y: 393 },
  { from: 10, to: 15, text: 'T1 · tube outside diameter', value: '3.5"', y: 520 },
] };
export const CHK = { cart: 0, click: 150, checkout: 170, callout: 230, total: 300, sms: 370 };
export const ACC = { phone: 90, callout: 150, tracker: 270, sidebar: 340, table: 410 };
export const END = { logo: 20, tag: 70, cta: 120 };

// ---------------------------------------------------------------- register every sound cue (absolute frames)
let registered = false;
export const registerCues = () => {
  if (registered) return SFX; registered = true;
  const A = (k: keyof typeof SEC, f: number) => (k === 'open' ? f : at(k) + f); // the open scene counts from frame 0
  // open
  sfx(0, 's1714', 0.35, 4.6); // rocket whoosh under the light line
  sfx(A('open', OPEN.laptop), 's1489', 0.4, 2.3);
  sfx(A('open', OPEN.logo), 's2902', 0.5, 3, 0, true);
  riser(A('open', OPEN.dive + 60)); thump(A('open', OPEN.flash), 1); whoosh(A('open', OPEN.dive + 30), 0.6);
  // transitions
  ORDER.forEach((k) => { if (TRANS[k] === 'whip' || TRANS[k] === 'cube') { riser(at(k)); whoosh(at(k), TRANS[k] === 'cube' ? 0.5 : 0.6); } });
  // home
  click(A('home', HOME.click)); pop(A('home', HOME.nav + 12), 0.35); pop(A('home', HOME.hero + 12), 0.35);
  // search
  SEARCH.typed.forEach((t) => tick(A('search', t), 0.5)); sfx(A('search', SEARCH.results), 's2358', 0.45, 0.6); click(A('search', SEARCH.add)); pop(A('search', SEARCH.callout + 12), 0.35);
  // chat
  click(A('chat', CHAT.open)); for (let i = 0; i < CHAT.nType; i++) tick(A('chat', CHAT.typeStart + i * CHAT.typeStep), 0.28 + (i % 3) * 0.05); click(A('chat', CHAT.send)); sfx(A('chat', CHAT.dots), 's2577', 0.3, 1); sfx(A('chat', CHAT.answer), 's2358', 0.45, 0.6); CHAT.cards.forEach((c) => pop(A('chat', c), 0.55)); pop(A('chat', CHAT.callout + 12), 0.35);
  // vehicle
  impact(A('vehicle', VEH.card)); [VEH.make, VEH.year, VEH.model].forEach((s) => { click(A('vehicle', s.open)); click(A('vehicle', s.pick)); pop(A('vehicle', s.set), 0.4); });
  for (let i = 0; i < 8; i++) sfx(A('vehicle', VEH.rowsIn + i * 5), 's1491', 0.22, 0.5); sfx(A('vehicle', VEH.hover), 's2577', 0.3, 1); click(A('vehicle', VEH.open)); pop(A('vehicle', VEH.price + 12), 0.4); pop(A('vehicle', VEH.ship + 10), 0.5);
  // builder
  impact(A('builder', BLD.card)); BLD.steps.forEach((s, i) => { const b = BLD.page + i * BLD.stepDur; pop(A('builder', b + 40), 0.3); click(A('builder', b + 80)); if (i === 6) for (let j = 0; j < 9; j++) tick(A('builder', b + 84 + j * 6), 0.4); });
  impact(A('builder', BLD.ready), 0.9); for (let j = 0; j < 16; j++) tick(A('builder', BLD.ready + 6 + j * 4), 0.45); pop(A('builder', BLD.ready + 74), 0.5);
  // designer
  impact(A('designer', DSG.card)); for (let i = 1; i < DSG.states; i++) tick(A('designer', DSG.typeStart + i * DSG.stateDur), 0.42); DSG.dims.forEach((d) => pop(A('designer', DSG.typeStart + d.from * DSG.stateDur + 10), 0.35)); sfx(A('designer', DSG.lift), 's1489', 0.5, 2.3);
  // checkout / account / end
  click(A('checkout', CHK.click)); pop(A('checkout', CHK.callout + 12), 0.35); sfx(A('account', ACC.phone), 's2358', 0.5, 0.6); pop(A('account', ACC.callout + 12), 0.35);
  impact(A('end', END.logo), 1); sfx(A('end', END.cta), 's2900', 0.45, 5, 0, true);
  // new beats get a soft pop so they read on the soundtrack too
  pop(A('home', HOME.icons + 12), 0.3); pop(A('home', HOME.vehicle + 12), 0.3); pop(A('open', OPEN.sweep2 + 20), 0.25);
  BLD.steps.forEach((_, i) => pop(A('builder', BLD.page + i * BLD.stepDur + BLD.chip), 0.4));
  DSG.rows.forEach((r) => tick(A('designer', r), 0.4)); pop(A('designer', DSG.share + 12), 0.35); pop(A('designer', DSG.saved), 0.5);
  pop(A('checkout', CHK.total + 12), 0.35); pop(A('checkout', CHK.sms + 12), 0.35);
  pop(A('account', ACC.tracker + 12), 0.35); pop(A('account', ACC.sidebar + 12), 0.35); pop(A('account', ACC.table + 12), 0.35);
  // visual-only events (camera snaps, shot changes, callouts, title words) — counted by the quality gate, silent in the mix
  const V = (k: keyof typeof SEC, f: number) => VIS.push(A(k, f));
  [OPEN.laptop, OPEN.logo, OPEN.scrollStart, OPEN.scrollStart + 40, OPEN.title, OPEN.sweep2, OPEN.dive].forEach((f) => V('open', f));
  [HOME.float, HOME.float + 4, HOME.nav, HOME.icons, HOME.hero, HOME.vehicle, HOME.toSearch, HOME.toSearch + 20].forEach((f) => V('home', f));
  [4, 56, 60, SEARCH.zoom, SEARCH.callout, SEARCH.hover, SEARCH.add + 6].forEach((f) => V('search', f));
  [4, 56, CHAT.open + 4, CHAT.zoom, CHAT.zoom + 30, CHAT.send + 4, CHAT.reveal, CHAT.reveal + CHAT.lineDur, CHAT.reveal + 2 * CHAT.lineDur].forEach((f) => V('chat', f));
  [4, VEH.cardOut, VEH.page, VEH.page + 50, VEH.rowsIn + 60, VEH.snap, VEH.hover + 16, VEH.product].forEach((f) => V('vehicle', f));
  [4, BLD.cardOut, BLD.page, BLD.page + 30].forEach((f) => V('builder', f));
  BLD.steps.forEach((_, i) => { const b = BLD.page + i * BLD.stepDur; [b + 30, b + 40, b + 84, b + BLD.chip].forEach((f) => V('builder', f)); });
  [BLD.ready + 4, BLD.ready + 80].forEach((f) => V('builder', f));
  [4, DSG.cardOut, DSG.page, DSG.page + 60, DSG.typeStart + 16 * DSG.stateDur + 60, DSG.typeStart + 16 * DSG.stateDur + 90, ...DSG.rows, DSG.share, DSG.lift, DSG.saved].forEach((f) => V('designer', f));
  [4, 56, CHK.click - 60, CHK.checkout, CHK.callout, CHK.total, CHK.sms].forEach((f) => V('checkout', f));
  [4, 56, ACC.phone, ACC.callout, ACC.callout + 60, ACC.tracker, ACC.sidebar, ACC.table].forEach((f) => V('account', f));
  [END.logo, END.tag, END.cta].forEach((f) => V('end', f));
  return SFX;
};
export const VIS: number[] = [];
export { at, SEC, beat, ORDER, TRANS, T2, len };
