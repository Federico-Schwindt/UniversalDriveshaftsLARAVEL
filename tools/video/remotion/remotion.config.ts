// Render defaults so the Studio's Render button works on this server (Playwright Chromium headless shell, no GPU).
import { Config } from '@remotion/cli/config';
if (process.env.BX) Config.setBrowserExecutable(process.env.BX);
Config.setChromiumOpenGlRenderer('swangle');
Config.setConcurrency(2);
Config.setVideoImageFormat('jpeg');
Config.setJpegQuality(95);
Config.setCodec('h264');
Config.setCrf(16);
Config.setPixelFormat('yuv420p');
Config.setOverwriteOutput(true);
Config.setDelayRenderTimeoutInMilliseconds(120000);
