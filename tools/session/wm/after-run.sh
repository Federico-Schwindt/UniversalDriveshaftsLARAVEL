#!/bin/bash
# Waits for the full swap to finish, then redoes the 30 transparent part photos from their originals with the alpha fix.
W=/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/wm
cd /var/www/vhosts/dev.udshafts.com/uds-app
while pgrep -f "artisan pti:dewatermark --set=all" > /dev/null; do sleep 60; done
while read -r n; do cp "storage/app/pti/originals/img/pti/$n" "public/img/pti/$n"; done < $W/alpha-list.txt
php artisan pti:dewatermark --set=parts 2>&1 | tr '\r' '\n' | grep -v "░\|▓\|^\s*[0-9]*/[0-9]*\|^$"
df -h / | tail -1
