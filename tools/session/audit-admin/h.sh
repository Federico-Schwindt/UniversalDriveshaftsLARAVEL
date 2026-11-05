#!/bin/bash
# usage: source h.sh ; login role email ; rq role METHOD path [curl args...]
B=https://dev.udshafts.com
D=/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/audit-admin
login() { # role email
  local J=$D/jar-$1.txt; rm -f $J
  local tok=$(curl -s -c $J -b $J $B/login | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -w "login $1 -> %{http_code} %{redirect_url}\n" -c $J -b $J -X POST $B/login --data-urlencode "_token=$tok" --data-urlencode "email=$2" --data-urlencode "password=${UDS_QA_PASSWORD}"
  curl -s -c $J -b $J $B/admin -o $D/page-$1.html
  grep -o 'name="csrf-token" content="[^"]*"' $D/page-$1.html | sed 's/.*content="//;s/"//' > $D/csrf-$1.txt
  echo "csrf $1: $(cat $D/csrf-$1.txt | cut -c1-8)..."
}
tok() { cat $D/csrf-$1.txt; }
rq() { # role method path [extra curl args]
  local R=$1 M=$2 P=$3; shift 3
  local J=$D/jar-$R.txt
  if [ "$M" = GET ]; then curl -s -o $D/last.html -w "%{http_code} %{redirect_url} %{size_download}B" -c $J -b $J "$B$P" "$@"
  else curl -s -o $D/last.html -w "%{http_code} %{redirect_url} %{size_download}B" -c $J -b $J -X $M "$B$P" --data-urlencode "_token=$(tok $R)" "$@"; fi
  echo "   <- $R $M $P"
}
rqj() { # role path json
  local R=$1 P=$2 J=$D/jar-$R.txt
  curl -s -o $D/last.html -w "%{http_code} %{size_download}B" -c $J -b $J -X POST "$B$P" -H "Content-Type: application/json" -H "Accept: application/json" -H "X-CSRF-TOKEN: $(tok $R)" --data "$3"
  echo "   <- $R JSON $P"
}
