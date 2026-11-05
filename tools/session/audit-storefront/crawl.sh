#!/bin/bash
S=/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/audit-storefront
u="$1"; f=$(echo -n "$u" | md5sum | cut -c1-12)
out=$(curl -s -o "$S/bodies/$f.html" -D "$S/bodies/$f.hdr" -w "%{http_code}\t%{time_starttransfer}\t%{time_total}\t%{size_download}\t%{redirect_url}" --max-time 60 -A "uds-audit/1.0" "$u")
err=$(grep -c -E "Whoops|ErrorException|Undefined (variable|array key|property)|SQLSTATE|htmlspecialchars\(\)|stack trace|Stack trace" "$S/bodies/$f.html")
printf "%s\t%s\t%s\t%s\n" "$u" "$f" "$out" "$err"
