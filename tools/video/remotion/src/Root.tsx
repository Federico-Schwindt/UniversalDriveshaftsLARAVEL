import React from 'react';
import { Composition } from 'remotion';
import { UdsReel, TOTAL } from './Reel';
import { Test3D } from './Test3D';
import { ProReel, PRO_TOTAL } from './Pro';
import { Launch, TOTAL as LAUNCH_TOTAL } from './launch/Launch';
import { Reel2, TOTAL2 } from './v2/Reel2';
import { Reel3, TOTAL as TOTAL3 } from './v3/Reel3';
import { ShaftSprite3D, SPRITE } from './v3/Shaft3D';
import { CvReel } from './cv/Cv';
import { TOTAL as CV_TOTAL, FPS as CV_FPS } from './cv/timeline';

export const RemotionRoot: React.FC = () => (
  <>
  <Composition id="CvReel" component={CvReel} durationInFrames={CV_TOTAL} fps={CV_FPS} width={1080} height={1920} />
  <Composition id="Launch3" component={Reel3} durationInFrames={TOTAL3} fps={60} width={1920} height={1080} />
  {['a', 'b', 'c'].map((v) => <Composition key={v} id={'Shaft' + v.toUpperCase()} component={ShaftSprite3D} defaultProps={{ variant: v }} durationInFrames={SPRITE.n} fps={30} width={SPRITE.w} height={SPRITE.h} />)}
  <Composition id="Launch916" component={Reel2} durationInFrames={TOTAL2} fps={30} width={1080} height={1920} />
  <Composition id="LaunchWide" component={Launch} durationInFrames={LAUNCH_TOTAL} fps={60} width={1920} height={1080} />
  <Composition id="LaunchTall" component={Launch} durationInFrames={LAUNCH_TOTAL} fps={60} width={1080} height={1920} />
  <Composition id="LaunchSquare" component={Launch} durationInFrames={LAUNCH_TOTAL} fps={60} width={1080} height={1080} />
  <Composition id="UdsReelPro" component={ProReel} durationInFrames={PRO_TOTAL} fps={30} width={1080} height={1920} />
  <Composition id="Test3D" component={Test3D} durationInFrames={90} fps={30} width={1080} height={1920} />
  <Composition id="UdsReel" component={UdsReel} durationInFrames={TOTAL} fps={30} width={1080} height={1920} />
  </>
);
