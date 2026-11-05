# Tools

Scripts used while building the store, kept as they were written: most are one-off checks, not maintained code.
Nothing here is loaded by the app. Passwords and keys are read from the environment (`UDS_QA_PASSWORD`, `PTI_PASS`, …)
or from local files that are not in this repository.

| Folder | What it holds |
|---|---|
| `session/` | Headless-Chromium screenshot and audit scripts (Playwright, run with `PWC` = path to playwright-core and `BX` = Chromium binary), the in-process page renderer `render.php`, PTI watermark prototypes (`wm/`), translation helpers (`i18n-tools/`), UX and performance audits. |
| `marketing/` | Instagram posts built as HTML and rendered to PNG at 1080 px: the launch campaign and the 5-star review carousel (`ig-review/`). |
| `pti-research/` | Scripts used to map the Powertrain Industries dealer portal before `app/Services/PtiClient.php` was written. They need a PTI dealer login (`PTI_USER`, `PTI_PASS`). |
| `video/` | The launch video: the Remotion project (`remotion/src`, render, mix and OCR QA scripts), the scripts that recorded the UI clips (`capture/`, `ui-clips/`). Stock music, footage and renders are not included (licensed assets); run `npm ci` in `video/remotion` to restore its packages. |
