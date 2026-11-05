import React, { useMemo } from 'react';
import * as THREE from 'three';

// A procedural driveshaft built from real-looking primitives (no model files): painted tube with a balance
// weight, weld yokes with rounded beveled ears, u-joint crosses with bearing caps, a splined slip yoke at the
// front and a round companion flange with bolts at the rear. 1 unit ≈ 1 inch. The shaft runs along X.

const mats = (tube: string) => ({
  steel: new THREE.MeshStandardMaterial({ color: '#9aa3ae', metalness: 1, roughness: 0.32 }),
  forged: new THREE.MeshStandardMaterial({ color: '#6c7480', metalness: 0.9, roughness: 0.5 }),
  dark: new THREE.MeshStandardMaterial({ color: '#2b313a', metalness: 0.8, roughness: 0.45 }),
  tube: new THREE.MeshPhysicalMaterial({ color: tube, metalness: 0.6, roughness: 0.22, clearcoat: 1, clearcoatRoughness: 0.08 }),
  red: new THREE.MeshPhysicalMaterial({ color: '#e11b22', metalness: 0.3, roughness: 0.3, clearcoat: 1 }),
});

// one yoke ear: a tab with a round end and a bore for the bearing cap, extruded with a bevel
const earGeometry = () => {
  const s = new THREE.Shape();
  s.moveTo(-1.1, 0); s.lineTo(-1.1, 2.4); s.absarc(0, 2.4, 1.1, Math.PI, 0, true); s.lineTo(1.1, 0); s.lineTo(-1.1, 0);
  const hole = new THREE.Path(); hole.absarc(0, 2.4, 0.62, 0, Math.PI * 2, false); s.holes.push(hole);
  const g = new THREE.ExtrudeGeometry(s, { depth: 0.55, bevelEnabled: true, bevelThickness: 0.12, bevelSize: 0.12, bevelSegments: 3, curveSegments: 28 });
  g.translate(0, 0, -0.275);
  return g;
};

// a yoke: collar + two ears pointing along +X (rotate the group to point it the other way)
const Yoke: React.FC<{ ear: THREE.BufferGeometry; m: any; collar?: number }> = ({ ear, m, collar = 2.15 }) => (
  <group>
    <mesh material={m.forged} rotation={[0, 0, Math.PI / 2]}><cylinderGeometry args={[collar, collar + 0.15, 1.3, 56]} /></mesh>
    {[1, -1].map((s) => (
      <mesh key={s} geometry={ear} material={m.forged} position={[0.4, 0, s * 1.38]} rotation={[0, s > 0 ? 0 : Math.PI, -Math.PI / 2]} />
    ))}
  </group>
);

// u-joint cross: body + four trunnions + bearing caps
const Cross: React.FC<{ m: any }> = ({ m }) => (
  <group>
    <mesh material={m.dark}><sphereGeometry args={[0.85, 24, 16]} /></mesh>
    {[[1, 0], [-1, 0], [0, 1], [0, -1]].map(([y, z], i) => (
      <group key={i} rotation={[z !== 0 ? Math.PI / 2 : 0, 0, 0]}>
        <mesh material={m.dark} position={[0, (y || z) * 1.3, 0]}><cylinderGeometry args={[0.4, 0.4, 1.4, 20]} /></mesh>
        <mesh material={m.steel} position={[0, (y || z) * 2.3, 0]}><cylinderGeometry args={[0.66, 0.66, 0.7, 36]} /></mesh>
        <mesh material={m.steel} position={[0, (y || z) * 2.7, 0]}><cylinderGeometry args={[0.5, 0.66, 0.12, 36]} /></mesh>
      </group>
    ))}
  </group>
);

export const Driveshaft: React.FC<{ length?: number; tubeColor?: string; spin?: number }> = ({ length = 34, tubeColor = '#1246ab', spin = 0 }) => {
  const m = useMemo(() => mats(tubeColor), [tubeColor]);
  const ear = useMemo(earGeometry, []);
  const half = length / 2;
  const joint = 3.1; // yoke base to cross centre
  return (
    <group rotation={[spin, 0, 0]}>
      {/* tube, weld seams and a balance weight */}
      <mesh material={m.tube} rotation={[0, 0, Math.PI / 2]}><cylinderGeometry args={[1.95, 1.95, length, 72]} /></mesh>
      {[-half, half].map((x) => <mesh key={x} material={m.steel} position={[x, 0, 0]} rotation={[0, 0, Math.PI / 2]}><torusGeometry args={[1.98, 0.14, 12, 72]} /></mesh>)}
      <mesh material={m.red} position={[half * 0.35, 0, 0]} rotation={[0, 0, Math.PI / 2]}><cylinderGeometry args={[1.985, 1.985, 0.45, 72]} /></mesh>
      <mesh material={m.steel} position={[-half * 0.45, 1.98, 0]}><boxGeometry args={[1.4, 0.18, 1.2]} /></mesh>

      {/* weld yokes on the tube, crosses in their ears */}
      <group position={[half + 0.6, 0, 0]}><Yoke ear={ear} m={m} /></group>
      <group position={[-half - 0.6, 0, 0]} rotation={[0, Math.PI, 0]}><Yoke ear={ear} m={m} /></group>
      <group position={[half + joint, 0, 0]}><Cross m={m} /></group>
      <group position={[-half - joint, 0, 0]}><Cross m={m} /></group>

      {/* rear: flange yoke + round companion flange with bolts */}
      <group position={[half + joint * 2 - 0.6, 0, 0]} rotation={[Math.PI / 2, Math.PI, 0]}><Yoke ear={ear} m={m} collar={1.6} /></group>
      <group position={[half + joint * 2 + 0.4, 0, 0]} rotation={[0, 0, Math.PI / 2]}>
        <mesh material={m.forged}><cylinderGeometry args={[3.0, 3.0, 0.7, 64]} /></mesh>
        {[0, 1, 2, 3].map((i) => {
          const a = (i / 4) * Math.PI * 2 + Math.PI / 4;
          return <mesh key={i} material={m.steel} position={[Math.cos(a) * 2.25, 0.45, Math.sin(a) * 2.25]}><cylinderGeometry args={[0.36, 0.36, 0.5, 6]} /></mesh>;
        })}
      </group>

      {/* front: slip yoke with a splined sleeve and the transmission output seal surface */}
      <group position={[-half - joint * 2 + 0.6, 0, 0]} rotation={[Math.PI / 2, 0, 0]}><Yoke ear={ear} m={m} collar={1.5} /></group>
      <mesh material={m.forged} position={[-half - joint * 2 - 2.2, 0, 0]} rotation={[0, 0, Math.PI / 2]}><cylinderGeometry args={[1.45, 1.45, 4.2, 48]} /></mesh>
      <mesh material={m.steel} position={[-half - joint * 2 - 5.6, 0, 0]} rotation={[0, 0, Math.PI / 2]}><cylinderGeometry args={[1.05, 1.05, 2.8, 48]} /></mesh>
      {Array.from({ length: 16 }).map((_, i) => {
        const a = (i / 16) * Math.PI * 2;
        return <mesh key={i} material={m.dark} position={[-half - joint * 2 - 5.6, Math.cos(a) * 1.02, Math.sin(a) * 1.02]} rotation={[a, 0, 0]}><boxGeometry args={[2.8, 0.12, 0.12]} /></mesh>;
      })}
    </group>
  );
};
