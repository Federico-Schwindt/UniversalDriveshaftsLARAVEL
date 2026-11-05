#!/bin/bash
u="$1"; for lang in en es; do
  url="$u"; [ "$lang" = es ] && url="${u}$( [[ "$u" == *\?* ]] && echo '&' || echo '?')lang=es"
  body=$(curl -s -L -m 40 -A "Mozilla/5.0 UDS-audit" -w "\n__CODE__%{http_code}" "$url")
  code=${body##*__CODE__}; hits=$(printf "%s" "$body" | grep -o -i "odessa\|prospect dr[a-z]*\|33556\|tampa[ a-z]*\|maps\.google[^\"']*\|land o.lakes\|lutz" | sort -u | tr '\n' '|')
  echo "$code	$lang	$u	$hits"
done
