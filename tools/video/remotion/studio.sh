#!/bin/bash
# Keeps Remotion Studio up on :3077 with the Chromium libs on the path; restarts it if a render crashes the process.
source "$HOME/pti-work/reel/env.sh"
cd "$HOME/pti-work/reel/remotion"
while true; do
  npx remotion studio --port 3077 --ipv4 --no-open >> out/studio.log 2>&1
  echo "[studio exited $(date -Is), restarting]" >> out/studio.log
  sleep 2
done
