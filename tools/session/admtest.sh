#!/bin/bash
S=/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad
J=$S/jar.txt; rm -f $J
PW=$(cat $S/qa.txt)
TOK=$(curl -s -c $J -b $J https://dev.udshafts.com/login | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -c $J -b $J -o /dev/null -w "login: %{http_code} -> %{redirect_url}\n" -X POST https://dev.udshafts.com/login --data-urlencode "_token=$TOK" --data-urlencode "email=qa-admin@udshafts.local" --data-urlencode "password=$PW"
for u in "$@"; do
  out=$(curl -s -b $J -c $J -w "\n__CODE__%{http_code}" "https://dev.udshafts.com$u")
  code=$(echo "$out" | tail -1 | sed 's/__CODE__//')
  err=$(echo "$out" | grep -o -E "Whoops|ErrorException|Undefined (variable|array key|property)[^<]{0,60}|SQLSTATE[^<]{0,80}|Call to [^<]{0,80}|syntax error[^<]{0,60}|Class \"[^\"]*\" not found" | head -1)
  title=$(echo "$out" | grep -o "<title>[^<]*" | head -1 | sed 's/<title>//')
  printf "%-45s %s  %s %s\n" "$u" "$code" "${title:0:40}" "${err:+ !! $err}"
done
