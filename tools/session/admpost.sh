#!/bin/bash
# POST-flow smoke tests for the admin (uses the QA admin session)
S=/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad; J=$S/jar.txt; rm -f $J; H=https://dev.udshafts.com
PW=$(cat $S/qa.txt)
tok() { curl -s -c $J -b $J "$H$1" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
T=$(tok /login); curl -s -c $J -b $J -o /dev/null -X POST $H/login --data-urlencode "_token=$T" --data-urlencode "email=qa-admin@udshafts.local" --data-urlencode "password=$PW"
post() { # post URL then print code + redirect + flash from the landing page
  local u=$1; shift; local T=$(tok "$u" ); [ -z "$T" ] && T=$(tok /admin)
  local r=$(curl -s -c $J -b $J -o /dev/null -w "%{http_code} %{redirect_url}" -X POST "$H$u" --data-urlencode "_token=$T" "$@")
  local code=${r%% *}; local loc=${r#* }
  local flash=""; if [ -n "$loc" ]; then flash=$(curl -s -c $J -b $J "$loc" | grep -o 'class="flash flash--[a-z]*"[^>]*>.\{0,200\}' | sed 's/<[^>]*>//g' | head -1 | tr -s ' ' | cut -c1-120); fi
  printf "POST %-42s %s -> %s | %s\n" "$u" "$code" "${loc#$H}" "$flash"; echo "$loc" > $S/lastloc
}
echo "== product create"; post /admin/products -d name="QA Test U-Joint 1310" -d sku=QA-TEST-1310 -d status=publish -d regular_price=42.50 -d sale_price= -d cost=20 -d weight=1.2 -d manage_stock=1 -d stock=7 -d backorders=no -d "categories[]=u-joints" -d "attrs[pa_series]=1310" -d "attrs[pa_brand]=UDS" -d "attrs[pa_style]=Full round" -d description="QA created product" -d interchange="QA-X1, 5-153X"
PID=$(cat $S/lastloc | grep -o '[0-9]*$'); echo "new product id: $PID"
curl -s -b $J "$H/admin/products/$PID" | grep -o 'value="QA-TEST-1310"\|value="42.50"\|value="1310"' | sort -u | tr '\n' ' '; echo
echo "storefront:"; curl -s -o /dev/null -w "  /product/qa-test-u-joint-1310 -> %{http_code}\n" "$H/product/qa-test-u-joint-1310"; curl -s "$H/shop/driveshaft-components/u-joints?q=QA-TEST" | grep -c "QA-TEST-1310" | sed 's/^/  listed in category rows: /'; curl -s "$H/api/search?q=QA-TEST-1310" | grep -c "QA-TEST-1310" | sed 's/^/  in search api: /'
echo "== product update (price/stock/attr)"; post /admin/products/$PID -d name="QA Test U-Joint 1310" -d sku=QA-TEST-1310 -d status=publish -d regular_price=39.99 -d manage_stock=1 -d stock=3 -d backorders=no -d "categories[]=u-joints" -d "attrs[pa_series]=1330" -d description="QA updated"
curl -s -b $J "$H/admin/products/$PID" | grep -o 'value="39.99"\|value="1330"\|value="3"' | sort -u | tr '\n' ' '; echo
echo "== quick edit"; curl -s -b $J -c $J -X POST "$H/admin/products/$PID/quick" -H "X-CSRF-TOKEN: $(tok /admin)" -H "Content-Type: application/json" -H "Accept: application/json" -d '{"field":"stock","value":"11"}'; echo
echo "== driveshaft spec"; post /admin/driveshafts/0844-11/spec -d series_front=1310 -d series_rear=1310 -d tube_od='3.0"' -d tube_wall='.083"' -d length_cc='44.25"' -d pieces=1 -d build_notes="QA note" -d "components[0][sku]=5-153X" -d "components[0][name]=1310 u-joint" -d "components[0][qty]=2" -d status=verified
curl -s -b $J "$H/admin/driveshafts/0844-11" | grep -o 'Spec verified\|value="44.25&quot;"\|value="5-153X"' | sort -u | tr '\n' ' '; echo
echo "== vehicle note"; post /admin/driveshafts/vehicle-note -d make=Ford -d model=F-150 -d year_from=2015 -d year_to=2020 -d notes="QA vehicle note"
echo "== manual order"; post /admin/orders/new -d email=qa-customer@udshafts.local -d billing_first_name=QA -d billing_last_name=Customer -d phone="+1 813-555-0100" -d billing_address_1="11611 Prospect Dr" -d billing_city=Odessa -d billing_state=FL -d billing_postcode=33556 -d billing_country=US -d shipping_method=manual -d shipping_label="UPS Ground (manual)" -d shipping_total=18.50 -d discount_total=5 -d tax_total=0 -d status=processing -d payment_status=paid -d payment_method=call -d source=phone -d "lines[0][type]=product" -d "lines[0][id]=$PID" -d "lines[0][qty]=2" -d "lines[0][price]=39.99" -d "lines[1][type]=driveshaft" -d "lines[1][sku]=U0844-11" -d "lines[1][qty]=1" -d "lines[1][price]=299" -d "lines[2][type]=custom" -d "lines[2][name]=Core charge" -d "lines[2][qty]=1" -d "lines[2][price]=50" -d internal_note="QA internal" -d create_account=1
OID=$(cat $S/lastloc | grep -o '[0-9]*$'); echo "order id: $OID"; curl -s -b $J "$H/admin/orders/$OID" | grep -o 'UDS-[0-9]*-[A-Z0-9]*\|\$443.47\|Build spec verified\|QA internal' | sort -u | tr '\n' ' '; echo
echo "== order edits"; post /admin/orders/$OID/items -d action=update -d item_id=$(php -r 'echo 0;') ; post /admin/orders/$OID/note -d body="QA customer note" -d kind=internal; post /admin/orders/$OID/tracking -d tracking_carrier=UPS -d tracking_number=1Z999QA -d notify=0; post /admin/orders/$OID/refund -d amount=10 -d reason="QA partial"
curl -s -b $J "$H/admin/orders/$OID" | grep -o 'Partial refund \$10.00\|1Z999QA\|badge--completed\|badge--partially_refunded' | sort -u | tr '\n' ' '; echo
echo "== coupon"; post /admin/coupons -d code=QATEST10 -d type=percent -d amount=10 -d active=1 -d minimum_amount=50
echo "== customer note + settings"; post "/admin/customers/qa-customer@udshafts.local/note" -d body="QA note"; post /admin/settings -d tab=storefront -d promo__text="QA PROMO TEXT" -d promo__link=/shipping-policy -d promo__enabled=1
curl -s "$H/" | grep -c "QA PROMO TEXT" | sed 's/^/  promo on storefront: /'
post /admin/settings -d tab=storefront -d promo__text="" -d promo__link=/shipping-policy -d promo__enabled=1
echo "== category create + delete"; post /admin/categories -d name="QA Category" -d group=small-parts -d parent=0; post /admin/categories/qa-category/delete
echo "== cleanup"; post /admin/products/$PID/delete; post /admin/coupons/$(php -r 'echo 0;')/delete >/dev/null
curl -s -o /dev/null -w "  deleted product page -> %{http_code}\n" "$H/product/qa-test-u-joint-1310"
