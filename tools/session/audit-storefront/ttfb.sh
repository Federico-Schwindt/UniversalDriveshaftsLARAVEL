#!/bin/bash
for u in "https://dev.udshafts.com/" "https://dev.udshafts.com/shop/driveshaft-components/u-joints" "https://dev.udshafts.com/parts/5-153X" "https://dev.udshafts.com/search?q=1310" "https://dev.udshafts.com/shop/driveshaft-components" "https://dev.udshafts.com/catalog/driveshafts" "https://dev.udshafts.com/catalog/driveshafts/part/U0844-1" "https://dev.udshafts.com/guides/how-to-measure-a-driveshaft" "https://dev.udshafts.com/series/1310" "https://dev.udshafts.com/cart"; do
  for i in 1 2 3 4 5; do curl -s -o /dev/null -A uds-audit -w "%{time_starttransfer}\n" "$u"; done | sort -n | awk -v u="$u" '{a[NR]=$1} END{printf "%s\tmedian=%.3f min=%.3f max=%.3f\n", u, a[3], a[1], a[5]}'
done
