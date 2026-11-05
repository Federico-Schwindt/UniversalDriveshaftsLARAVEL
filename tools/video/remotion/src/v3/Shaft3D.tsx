import React from 'react';
import { ThreeCanvas } from '@remotion/three';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { Driveshaft } from '../Driveshaft3D';

// The procedural driveshaft from Driveshaft3D, lit like a product shot. Software GL on the render box costs ~9 s per frame,
// so the model is rendered ONCE per variant as a 120-frame turntable sprite (ShaftSprite composition, PNG with alpha) and the
// main video plays those sprites back. Variants: a = blue tube 34", b = steel tube 34", c = steel tube 52".
export const SPRITE = { w: 1200, h: 720, n: 90 };
export const VARIANTS: Record<string, { tube: string; length: number }> = { a: { tube: '#1246ab', length: 34 }, b: { tube: '#8d97a6', length: 34 }, c: { tube: '#8d97a6', length: 52 } };

export const ShaftSprite3D: React.FC<{ variant: string }> = ({ variant }) => {
  const f = useCurrentFrame(); const v = VARIANTS[variant];
  // a slow sway (±38°) keeps the shaft broadside; the u-joints spin. Loop-safe: both are periodic in SPRITE.n frames.
  const ph = (f / SPRITE.n) * Math.PI * 2, ry = 0.66 * Math.sin(ph), sp = ph * 2;
  return (
    <ThreeCanvas width={SPRITE.w} height={SPRITE.h} camera={{ fov: 32, position: [0, 6, v.length > 40 ? 128 : 100], near: 1, far: 1000 }} gl={{ antialias: false, alpha: true }} style={{ background: 'transparent' }}>
      <hemisphereLight args={['#eaf1ff', '#1a2744', 2.4]} />
      <directionalLight position={[40, 60, 80]} intensity={5.5} color="#ffffff" />
      <directionalLight position={[-70, 20, -40]} intensity={3.5} color="#7fb0ff" />
      <pointLight position={[10, -30, 60]} intensity={2200} color="#ffc9a8" distance={400} decay={2} />
      <group rotation={[0.28, ry, 0.08]}>
        <Driveshaft length={v.length} tubeColor={v.tube} spin={sp} />
      </group>
    </ThreeCanvas>
  );
};

// playback: same props the scenes used before, now a sprite frame (half speed → one turn every 4 s), with CSS for the yoke pop
export const Shaft3D: React.FC<{ variant?: string; width?: number; height?: number; x?: number; y?: number; opacity?: number; blur?: number; yokeScale?: number; phase?: number; speed?: number; scale?: number; tilt?: number }> =
  ({ variant = 'a', width = SPRITE.w, height = SPRITE.h, x = 0, y = 0, opacity = 1, blur = 0, yokeScale = 1, phase = 0, speed = 0.4, scale = 1.25, tilt = 0 }) => {
    const f = useCurrentFrame(); const n = ((Math.floor(f * speed + phase) % SPRITE.n) + SPRITE.n) % SPRITE.n;
    return (
      <div style={{ position: 'absolute', left: x, top: y, width, height, opacity, filter: blur ? `blur(${blur}px)` : undefined, pointerEvents: 'none', transform: `rotate(${tilt}deg) scale(${scale * yokeScale})`, transformOrigin: '50% 50%' }}>
        <Img src={staticFile(`v3/shaft/${variant}/element-${String(n).padStart(2, '0')}.png`)} style={{ position: 'absolute', inset: 0, width, height, display: 'block' }} />
      </div>
    );
  };
