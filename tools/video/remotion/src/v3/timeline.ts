// Timeline of the launch video, 60 fps, 1920x1080. Music: Mixkit 846 "I Won't Surrender" — 110 BPM, first beat at 0.488 s.
// Every section boundary sits on a beat so transitions land with the music.
export const FPS = 60, W = 1920, H = 1080;
export const BPM = 110, BEAT0 = 0.488;
export const beat = (n: number) => Math.round((BEAT0 + (n * 60) / BPM) * FPS);
export const BEATF = (60 / BPM) * FPS; // 32.7 frames per beat

export type Key = keyof typeof SEC;
export const SEC = {
  open: [beat(0), beat(15)],
  home: [beat(15), beat(30)],
  search: [beat(30), beat(45)],
  chat: [beat(45), beat(67)],
  vehicle: [beat(67), beat(100)],
  builder: [beat(100), beat(140)],
  designer: [beat(140), beat(170)],
  checkout: [beat(170), beat(184)],
  account: [beat(184), beat(198)],
  end: [beat(198), beat(206)],
} as const;
export const TOTAL = beat(206) + 40;
export const T = 16; // transition length (frames); both scenes render for these frames
export const T2 = T / 2;
export const ORDER: Key[] = ['open', 'home', 'search', 'chat', 'vehicle', 'builder', 'designer', 'checkout', 'account', 'end'];
// transition INTO each scene
export const TRANS: Record<Key, 'dive' | 'whip' | 'cube' | 'none'> = { open: 'none', home: 'dive', search: 'whip', chat: 'cube', vehicle: 'whip', builder: 'cube', designer: 'whip', checkout: 'whip', account: 'cube', end: 'whip' };
export const at = (k: Key) => SEC[k][0];
export const len = (k: Key) => SEC[k][1] - SEC[k][0];

// ---- sound events (absolute frames) — filled by the scenes through sfx(); the mixer reads the exported list
export type Sfx = { f: number; src: string; vol: number; dur?: number; off?: number; duck?: boolean };
export const SFX: Sfx[] = [];
export const sfx = (f: number, src: string, vol = 0.6, dur?: number, off?: number, duck = false) => { SFX.push({ f, src, vol, dur, off, duck }); };
let clickSeed = 11;
export const CLICKS = ['s2568', 's1109', 's3124'];
export const click = (f: number, vol = 0.7) => { clickSeed = (clickSeed * 1103515245 + 12345) & 0x7fffffff; const v = CLICKS[clickSeed % 3]; sfx(f, v, vol, v === 's1109' ? 0.35 : 0.5); };
export const tick = (f: number, vol = 0.32) => sfx(f, 's1120', vol, 0.25);
export const pop = (f: number, vol = 0.5) => sfx(f, 's3005', vol, 0.25);
export const whoosh = (f: number, vol = 0.55) => sfx(f - 10, 's1492', vol, 1.3);
export const riser = (f: number, vol = 0.5) => sfx(f - 48, 's1486', vol, 0.9, 5.6); // last 0.9 s of the tunnel woosh ends on the cut
export const impact = (f: number, vol = 0.8) => sfx(f, 's2908', vol, 2.2, 0, true);
export const thump = (f: number, vol = 0.9) => sfx(f, 's498', vol, 2.5, 0, true);

// ---- motion report: which independent layers move in every section (for the quality gate)
export const LAYERS: Record<Key, string[]> = {
  open: ['background blobs+grain', 'dust particles', 'laptop orbit / dive', 'screen scroll', 'light sweep / logo wipe', 'text'],
  home: ['background blobs+grain', 'dust particles', 'camera + window float', 'page scroll', 'callouts', 'cursor+trail'],
  search: ['background blobs+grain', 'dust particles', 'camera', 'typed results', 'callouts', 'cursor+trail'],
  chat: ['background blobs+grain', 'dust particles', 'camera', 'typing / answer reveal / cards', 'callouts', 'cursor+trail'],
  vehicle: ['background blobs+grain', 'dust particles', 'camera', 'dropdowns / rows fly-in', 'callouts', 'cursor+trail', '3D driveshaft (title card)'],
  builder: ['background blobs+grain', 'dust particles', 'blueprint grid pulses', '3D driveshaft', 'camera', 'UI steps / price', 'callouts', 'cursor+trail'],
  designer: ['background blobs+grain', 'dust particles', 'blueprint grid pulses', 'camera orbit', 'dimension lines + digits', 'callouts', 'cursor+trail'],
  checkout: ['background blobs+grain', 'dust particles', 'camera', 'cart → checkout', 'callouts', 'cursor+trail'],
  account: ['background blobs+grain', 'dust particles', 'camera', 'phone parallax', 'callouts', 'cursor+trail'],
  end: ['background blobs+grain', 'dust particles', '3D driveshaft', 'logo wipe', 'text', 'light pulse'],
};
