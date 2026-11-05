#!/bin/bash
# usage: bing.sh <dir> <tag> <query> [n]
D=$1; T=$2; Q=$3; N=${4:-8}
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36"
curl -s -m 25 -A "$UA" "https://www.bing.com/images/search?q=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote_plus(sys.argv[1]))" "$Q")&qft=+filterui:imagesize-large&form=HDRSC2" | grep -o 'murl&quot;:&quot;[^&]*' | sed 's/murl&quot;:&quot;//' | head -$N > $D/$T.urls
i=0; while read u; do i=$((i+1)); ext=$(echo "$u" | grep -oiE '\.(jpg|jpeg|png|webp)' | tail -1); ext=${ext:-.jpg}; curl -s -L -m 20 -A "$UA" -o "$D/$T-$i$ext" "$u"; s=$(stat -c %s "$D/$T-$i$ext" 2>/dev/null || echo 0); [ "$s" -lt 15000 ] && rm -f "$D/$T-$i$ext"; done < $D/$T.urls
