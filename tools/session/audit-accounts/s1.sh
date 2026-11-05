#!/bin/bash
B=https://dev.udshafts.com
tok(){ curl -s -b $1 -c $1 "$B$2" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
echo "### A. cookie flags on GET /login"
curl -s -D - -o /dev/null -c jarA $B/login | grep -i "set-cookie" | sed 's/=[^;]*;/=…;/'
echo "### B. register A (a-audit@udshafts.local)"
T=$(tok jarA /register)
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" -b jarA -c jarA -X POST $B/register -d "_token=$T&name=Audit%20A%20%3Cb%3Ex%3C%2Fb%3E&email=a-audit@udshafts.local&phone=%2B1%20813-524-6491&password=${UDS_QA_PASSWORD}&password_confirmation=${UDS_QA_PASSWORD}"
echo "### B2. register duplicate email (fresh jar) -> error text"
curl -s -c jarD -o /dev/null $B/register; T=$(tok jarD /register)
curl -s -o /dev/null -b jarD -c jarD -X POST $B/register -d "_token=$T&name=Dup&email=a-audit@udshafts.local&password=${UDS_QA_PASSWORD}&password_confirmation=${UDS_QA_PASSWORD}"
curl -s -b jarD $B/register | grep -o 'already been taken[^<]*\|invalid[^<]*' | head -2
echo "### C. login throttle: 6 bad logins same email+ip"
curl -s -c jarL -o /dev/null $B/login; T=$(tok jarL /login)
for i in 1 2 3 4 5 6; do curl -s -o /dev/null -w "$i:%{http_code} " -b jarL -c jarL -X POST $B/login -d "_token=$T&email=a-audit@udshafts.local&password=wrong"; done; echo
echo "### C2. wrong-password vs unknown-user message"
curl -s -c jarM -o /dev/null $B/login; T=$(tok jarM /login)
curl -s -b jarM -c jarM -X POST $B/login -d "_token=$T&email=nobody-zz@udshafts.local&password=wrong" -o /dev/null; curl -s -b jarM $B/login | grep -o 'Invalid email or password[^<]*' | head -1
echo "### D. session id regenerate on login + open redirect probes"
for R in "//evil.com" "/\\evil.com" "https://evil.com" "/%2F%2Fevil.com" "/account/orders"; do
  rm -f jarR; curl -s -c jarR -o /dev/null "$B/login?redirect=$R"; BEFORE=$(grep -i session jarR | awk '{print $7}' | cut -c1-12); T=$(tok jarR /login)
  curl -s -o /dev/null -w "redirect=$R -> %{http_code} %{redirect_url} " -b jarR -c jarR -X POST $B/login -d "_token=$T&email=a-audit@udshafts.local&password=${UDS_QA_PASSWORD}"
  AFTER=$(grep -i session jarR | awk '{print $7}' | cut -c1-12); echo "| sid before=$BEFORE after=$AFTER"; sleep 1
done
echo "### D2. cookies set on login (remember?)"
grep -v "^#" jarR | awk '{print $6, $5, $4, $3}' 
echo "### E. logout GET / POST w/o token"
curl -s -o /dev/null -w "GET /logout: %{http_code}\n" -b jarR $B/logout
curl -s -o /dev/null -w "POST /logout no token: %{http_code}\n" -b jarR -X POST $B/logout
