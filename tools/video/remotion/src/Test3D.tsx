import React from 'react';
import { AbsoluteFill, useCurrentFrame, useVideoConfig } from 'remotion';
import { ThreeCanvas } from '@remotion/three';
import { Driveshaft } from './Driveshaft3D';
import { StudioEnv, Lights } from './Studio3D';

export const Test3D: React.FC = () => {
  const f = useCurrentFrame(); const { width, height } = useVideoConfig();
  return (
    <AbsoluteFill style={{ background: 'radial-gradient(90% 60% at 50% 45%, #1a3366, #0b1a33 60%, #050b16)' }}>
      <ThreeCanvas width={width} height={height} camera={{ position: [0, 0, 120], fov: 32 }}>
        <StudioEnv />
        <Lights />
        <group rotation={[0.35, -0.55 + f * 0.01, 1.1]}>
          <Driveshaft spin={f * 0.12} />
        </group>
      </ThreeCanvas>
    </AbsoluteFill>
  );
};
