import React, { useEffect } from 'react';
import * as THREE from 'three';
import { useThree } from '@react-three/fiber';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

// Studio reflections for the metal (a procedural room environment, no HDR files needed).
export const StudioEnv: React.FC<{ intensity?: number }> = ({ intensity = 0.55 }) => {
  const { gl, scene } = useThree();
  useEffect(() => {
    const pmrem = new THREE.PMREMGenerator(gl);
    const env = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environment = env;
    (scene as any).environmentIntensity = intensity;
    gl.toneMapping = THREE.ACESFilmicToneMapping;
    gl.toneMappingExposure = 0.95;
    return () => { env.dispose(); pmrem.dispose(); };
  }, [gl, scene, intensity]);
  return null;
};

export const Lights: React.FC = () => (
  <>
    <ambientLight intensity={0.15} />
    <directionalLight position={[30, 40, 30]} intensity={2.2} />
    <directionalLight position={[-40, -10, 20]} intensity={1.1} color="#6f9cf0" />
    <pointLight position={[0, -30, 30]} intensity={1600} distance={200} color="#e11b22" />
  </>
);
