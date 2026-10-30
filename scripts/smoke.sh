#!/usr/bin/env bash
# End-to-end smoke test for Spin City Laundry.
# Boots the app on a throwaway SQLite database, then exercises the real HTTP
# flows: login guard, order creation with line items, a payment, the printable
# receipt, reports and the CSV export.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PORT="${SMOKE_PORT:-8099}"
BASE="http://127.0.0.1:${PORT}/index.php"
DB_FILE="$(mktemp -u /tmp/laundry-smoke-XXXXXX.sqlite)"
JAR="$(mktemp -u /tmp/laundry-cookies-XXXXXX.txt)"
LOG="$(mktemp /tmp/laundry-server-XXXXXX.log)"
SERVER_PID=""

failures=0
pass() { printf '  ok   %s\n' "$1"; }
fail() { printf '  FAIL %s\n' "$1"; failures=$((failures + 1)); }
check() { if [ "$2" = "1" ]; then pass "$1"; else fail "$1"; fi; }

cleanup() {
    if [ -n "$SERVER_PID" ]; then kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; fi
    rm -f "$DB_FILE" "$DB_FILE-wal" "$DB_FILE-shm" "$JAR" "$LOG"
    rm -rf "$ROOT/public/assets"
}
trap cleanup EXIT

echo "== booting app on ${BASE} (sqlite: ${DB_FILE})"

# The web root needs the static assets alongside index.php.
cp -r "$ROOT/assets" "$ROOT/public/assets"

DB_DRIVER=sqlite DB_SQLITE_PATH="$DB_FILE" \
    php -S "127.0.0.1:${PORT}" -t "$ROOT/public" >"$LOG" 2>&1 &
SERVER_PID=$!

ready=0
for _ in $(seq 1 60); do
    if curl -s -o /dev/null "${BASE}?r=login"; then ready=1; break; fi
    sleep 0.5
done
if [ "$ready" != "1" ]; then
    echo "server never came up"; cat "$LOG"; exit 1
fi

get() { curl -s -c "$JAR" -b "$JAR" "$1"; }
status_of() { curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$1"; }
post() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$JAR" -b "$JAR" -X POST --data-urlencode "csrf=$CSRF" "${@:2}" "$1"; }
csrf_from() { printf '%s' "$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "== schema and seed data"
tables=$(sqlite3 "$DB_FILE" "SELECT group_concat(name) FROM sqlite_master WHERE type='table';" 2>/dev/null || echo "")
for t in users customers services employees orders order_items payments settings; do
    echo "$tables" | grep -q "$t" && check "table $t created" 1 || check "table $t created" 0
done
users=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM users;" 2>/dev/null || echo 0)
[ "$users" -ge 2 ] && pass "seeded $users users" || fail "users not seeded ($users)"
services=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM services;" 2>/dev/null || echo 0)
[ "$services" -ge 4 ] && pass "seeded $services services" || fail "services not seeded ($services)"
customers=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM customers;" 2>/dev/null || echo 0)
[ "$customers" -ge 3 ] && pass "seeded $customers customers" || fail "customers not seeded ($customers)"

echo "== auth guard"
code=$(status_of "${BASE}?r=dashboard")
[ "$code" = "302" ] && pass "anonymous dashboard redirects (302)" || fail "anonymous dashboard returned $code"

echo "== login"
login_page=$(get "${BASE}?r=login")
CSRF=$(csrf_from "$login_page")
[ -n "$CSRF" ] && pass "login page exposes CSRF token" || fail "no CSRF token on login page"
result=$(post "${BASE}?r=login" -d "action=login" -d "email=admin@laundry.test" -d "password=admin123")
case "$result" in
    302*dashboard*) pass "valid credentials redirect to dashboard" ;;
    *) fail "login returned: $result" ;;
esac

dash=$(get "${BASE}?r=dashboard")
printf '%s' "$dash" | grep -q "Spin City" && pass "dashboard renders brand" || fail "dashboard missing brand"
CSRF=$(csrf_from "$dash")
[ -n "$CSRF" ] && pass "authenticated CSRF token available" || fail "no CSRF token after login"

echo "== order creation with line items"
CUSTOMER_ID=$(sqlite3 "$DB_FILE" "SELECT id FROM customers ORDER BY id LIMIT 1;")
SERVICE_ROW=$(sqlite3 "$DB_FILE" "SELECT id || '|' || price || '|' || name FROM services ORDER BY id LIMIT 1;")
SERVICE_ID=${SERVICE_ROW%%|*}
SERVICE_REST=${SERVICE_ROW#*|}
SERVICE_PRICE=${SERVICE_REST%%|*}
SERVICE_NAME=${SERVICE_REST#*|}
result=$(post "${BASE}?r=orders/new" \
    -d "action=order.save" \
    -d "customer_id=${CUSTOMER_ID}" \
    -d "employee_id=" \
    -d "status=received" \
    -d "pickup_date=$(date +%F)" \
    -d "delivery_date=" \
    -d "notes=Smoke test order" \
    -d "discount=50" \
    -d "delivery_fee=150" \
    -d "item_service[]=${SERVICE_ID}" \
    -d "item_qty[]=2" \
    -d "item_price[]=${SERVICE_PRICE}")
case "$result" in
    302*orders/show*id=*) pass "order saved and redirected to detail page" ;;
    *) fail "order save returned: $result" ;;
esac
ORDER_ID=$(sqlite3 "$DB_FILE" "SELECT MAX(id) FROM orders;")
ORDER_NO=$(sqlite3 "$DB_FILE" "SELECT order_no FROM orders WHERE id=${ORDER_ID};")
items=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM order_items WHERE order_id=${ORDER_ID};")
[ "$items" -eq 1 ] && pass "line item stored for ${ORDER_NO}" || fail "line items stored: $items"
expected=$(awk -v p="$SERVICE_PRICE" 'BEGIN { printf "%.2f", (2*p) - 50 + 150 }')
stored=$(sqlite3 "$DB_FILE" "SELECT printf('%.2f', total) FROM orders WHERE id=${ORDER_ID};")
[ "$expected" = "$stored" ] && pass "order total ${stored} = qty*price - discount + delivery" \
    || fail "order total ${stored} expected ${expected}"

echo "== payment and balance"
result=$(post "${BASE}?r=payments" -d "action=payment.save" -d "order_id=${ORDER_ID}" \
    -d "amount=100" -d "method=cash" -d "reference=SMOKE-1" -d "note=smoke" -d "paid_at=")
case "$result" in
    302*) pass "payment accepted" ;;
    *) fail "payment returned: $result" ;;
esac
paid=$(sqlite3 "$DB_FILE" "SELECT printf('%.2f', COALESCE(SUM(amount),0)) FROM payments WHERE order_id=${ORDER_ID};")
[ "$paid" = "100.00" ] && pass "payment persisted (${paid})" || fail "payment stored: $paid"
over=$(post "${BASE}?r=payments" -d "action=payment.save" -d "order_id=${ORDER_ID}" -d "amount=999999" -d "method=cash")
payments=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM payments WHERE order_id=${ORDER_ID};")
[ "$payments" -eq 1 ] && pass "overpayment rejected, ledger unchanged" || fail "overpayment created ${payments} payments"

show=$(get "${BASE}?r=orders/show&id=${ORDER_ID}")
printf '%s' "$show" | grep -q "${ORDER_NO}" && pass "order detail shows ${ORDER_NO}" || fail "order detail missing order number"
printf '%s' "$show" | grep -qi "partial" && pass "order detail flags partial balance" || fail "order detail missing partial state"

echo "== receipt, reports, csv"
inv=$(get "${BASE}?r=invoice&id=${ORDER_ID}")
printf '%s' "$inv" | grep -q "RECEIPT" && pass "receipt page renders" || fail "receipt page missing heading"
printf '%s' "$inv" | grep -q "${ORDER_NO}" && pass "receipt shows order number" || fail "receipt missing order number"
printf '%s' "$inv" | grep -q "Smoke Test Order" && pass "receipt shows line item label" || fail "receipt missing line items"

rep=$(get "${BASE}?r=reports")
printf '%s' "$rep" | grep -qi "revenue" && pass "reports page renders" || fail "reports page missing revenue section"

csv_headers=$(curl -s -D - -o /tmp/laundry-smoke.csv -b "$JAR" "${BASE}?r=reports&export=csv" | tr -d '\r')
echo "$csv_headers" | grep -qi "content-type: text/csv" && pass "csv export sends text/csv" || fail "csv content-type: $csv_headers"
head -1 /tmp/laundry-smoke.csv | grep -q "^Order,Date" && pass "csv header row correct" || fail "csv header: $(head -1 /tmp/laundry-smoke.csv)"
grep -q "${ORDER_NO}" /tmp/laundry-smoke.csv && pass "csv contains ${ORDER_NO}" || fail "csv missing ${ORDER_NO}"
rm -f /tmp/laundry-smoke.csv

echo "== admin pages and 404"
for route in customers services employees payments settings; do
    code=$(status_of "${BASE}?r=${route}")
    [ "$code" = "200" ] && pass "${route} page renders" || fail "${route} returned ${code}"
done
code=$(status_of "${BASE}?r=does-not-exist")
[ "$code" = "404" ] && pass "unknown route returns 404" || fail "unknown route returned ${code}"

echo "== server error log"
if grep -qiE "PHP (Fatal|Parse) error|Uncaught" "$LOG"; then
    echo "--- php warnings/errors ---"; grep -iE "PHP (Fatal|Parse) error|Uncaught" "$LOG" | head -20
    failures=$((failures + 1))
else
    pass "no PHP fatal or parse errors during the run"
fi

echo
if [ "$failures" -eq 0 ]; then
    echo "SMOKE TEST PASSED"
    exit 0
fi
echo "SMOKE TEST FAILED: ${failures} check(s)"
exit 1
