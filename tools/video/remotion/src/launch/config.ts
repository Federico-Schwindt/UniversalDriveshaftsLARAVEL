// Every number, line of copy and colour of the launch video lives here, so a client revision is a one-line change.
// Timeline is at 60 fps. Scene ranges match storyboard.md exactly (3000 frames = 50.0 s).

export const FPS = 60;

export const COLORS = {
  navy: '#0b1a33', navy2: '#12234a', ink: '#141b28', ink2: '#3a4557', ink3: '#5c6879', line: '#d6dfee',
  paper: '#f6f8fc', white: '#ffffff', blue: '#015dca', blue2: '#1246ab', blueL: '#6f9cf0', red: '#cf3130', green: '#1f9d55',
};

export const SCENES = {
  hook: [0, 240], hunt: [240, 600], site: [600, 900], finder: [900, 1260], designer: [1260, 1620],
  assistant: [1620, 2040], order: [2040, 2400], account: [2400, 2700], logo: [2700, 3000],
} as const;
export const TOTAL = 3000;

export const COPY = {
  pill: '2015 FORD F-150 · 4WD · DRIVESHAFT',
  hook: 'Your truck is down.',
  counterLabel: 'HOURS DOWN',
  counterFrom: 0, counterTo: 72,
  hunt: ['Search.', 'Call.', 'Wait.'],
  tabs: ['Catalog.pdf', 'Supplier A', 'Supplier B', 'Cross-ref.xlsx'],
  siteUrl: 'universaldriveshafts.com',
  hero: ['The driveshaft parts you need,', 'on the shelf today.'],
  search: '1310',
  categories: [['U-joints', 'cross'], ['Yokes', 'yoke'], ['Center bearings', 'bearing'], ['Tubing', 'tube']] as [string, string][],
  stats: [{ n: 2600, suffix: '+', label: 'parts' }, { text: '1310–1810', label: 'series' }, { text: 'Same day', label: 'ships before 2pm ET' }],
  finderTitle: 'Find your complete driveshaft by vehicle.',
  finderSteps: [
    { k: 'MAKE', options: ['CHEVROLET', 'DODGE', 'FORD', 'GMC', 'JEEP', 'TOYOTA'], pick: 'FORD' },
    { k: 'YEAR', options: ['2017', '2016', '2015', '2014', '2013', '2012'], pick: '2015' },
    { k: 'MODEL', options: ['E350 Super Duty', 'Edge', 'Escape', 'Expedition', 'Explorer', 'F150'], pick: 'F150' },
    { k: 'DRIVE', options: ['AWD/4WD', 'RWD/2WD'], pick: 'AWD/4WD' },
  ],
  result: { vehicle: 'FORD F150 · 2015 · AWD/4WD', sku: 'U3194-2200', name: 'Ford front driveshaft', price: 456.28 },
  designerTitle: ['Draw it.', 'Dimension it.'],
  designerNote: 'Free · No account needed · Print it or send it to our shop',
  dims: [{ ref: 'C1', desc: 'Overall length, end to end', v: '52.25' }, { ref: 'W1', desc: 'Tube length, weld to weld', v: '48.5' }, { ref: 'T1', desc: 'Tube outside diameter', v: '3.5' }],
  prompt: '1350 u-joint for my lifted Jeep. Can you ship it?',
  agents: [
    { t: 'Fitment check', d: '1350 series · lifted Jeep', icon: 'fit' },
    { t: 'Part search', d: '5-1350XU · 1350TST · 5-7166XU', icon: 'search' },
    { t: 'Stock & price', d: 'Live from our catalog', icon: 'tag' },
    { t: 'Shipping quote', d: 'Live UPS & FedEx rates', icon: 'truck' },
  ],
  assistantTitle: ['Just ask', 'UDS Assistant.'],
  orderTitle: 'Ordering takes four steps.',
  steps: [
    { t: 'Find it', d: 'Search, by vehicle, the designer or Ask UDS' },
    { t: 'Add to cart', d: 'Live price for your account' },
    { t: 'Check out', d: 'UPS & FedEx · LTL freight for pallets · Local pickup' },
    { t: 'Track it', d: 'Every order lives in My Account' },
  ],
  account: { hello: 'Welcome back, Alex', item: 'U3194-2200 · Ford front driveshaft', total: '$456.28', track: ['Placed', 'Paid', 'Shipped', 'Delivered'] },
  payoff: 'Back on the road.',
  tagline: 'The driveshaft parts you need, on the shelf today.',
  cta: 'Now live · universaldriveshafts.com',
};

// cursor clicks per scene (scene-local frames) — the click sounds are generated from these
export const CLICKS = {
  hunt: [100, 170, 240],
  site: [70],
  finder: [50, 110, 170, 230],
  designer: [70, 150, 230],
  assistant: [30, 150],
  account: [150],
};
// checkmark pops (scene-local)
export const POPS = { order: [24, 36, 48, 60, 150, 156, 162, 168] };
