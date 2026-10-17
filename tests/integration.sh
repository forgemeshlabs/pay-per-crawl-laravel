#!/usr/bin/env bash
# Runs the HTTP matrix against a Laravel app that has this package installed in gate mode.
#   APP_DIR     Laravel app (default ~/dev/ppc-testbed/laravel13)
#   PHP         php binary for the app and the mock (default php)
# The app must have: PAY_PER_CRAWL_MODE=gate, PAY_PER_CRAWL_PAY_TO=<wallet>,
# PAY_PER_CRAWL_FACILITATOR_URL=http://127.0.0.1:8413, a GET /paid-test route containing ZZPAIDZZ,
# and llms_pages listing "Paid test post".
set -u
HERE=$(cd "$(dirname "$0")" && pwd)
APP_DIR=${APP_DIR:-$HOME/dev/ppc-testbed/laravel13}
PHP=${PHP:-php}
APP_PORT=8412; MOCK_PORT=8413
export MOCK_LOG=$(mktemp)
cleanup(){ kill $APP_PID $MOCK_PID 2>/dev/null; fuser -k -n tcp $APP_PORT $MOCK_PORT >/dev/null 2>&1; rm -f "$MOCK_LOG"; }
fuser -k -n tcp $APP_PORT $MOCK_PORT >/dev/null 2>&1; sleep 0.3
trap cleanup EXIT
cd "$APP_DIR" || exit 2
$PHP artisan migrate:fresh --force >/dev/null 2>&1; $PHP artisan cache:clear >/dev/null 2>&1; $PHP artisan config:clear >/dev/null 2>&1
MODE=${MOCK_MODE:-ok} $PHP -S 127.0.0.1:$MOCK_PORT "$HERE/integration/mock-facilitator.php" >/dev/null 2>&1 & MOCK_PID=$!
(cd public && PHP_CLI_SERVER_WORKERS=4 exec $PHP -S 127.0.0.1:$APP_PORT -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >"$APP_DIR/storage/logs/php-server.log" 2>&1) & APP_PID=$!
sleep 1.5
BASE_URL=http://127.0.0.1:$APP_PORT CORE_SRC="$APP_DIR/vendor/forgemesh/pay-per-crawl-core/src" "$HERE/integration/matrix.sh"
RC=$?

# Laravel-specific: hits table, stats command, discovery content-types.
pass=0; fail=0
ok(){ if [ "$1" = "$2" ]; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL: $3 (got '$1' want '$2')"; fi; }
STATS=$($PHP artisan pay-per-crawl:stats --days=1 --json)
ok "$(echo "$STATS" | $PHP -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j["settlements"]);')" 3 "stats lists the three settlements (v2, v1, MPP)"
ok "$(echo "$STATS" | $PHP -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["bots"]["paid"],"|",$j["bots"]["revenue_units"];')" "3|30000" "stats revenue 3 x 10000 units"
$PHP artisan pay-per-crawl:stats --days=1 | grep -q 'GPTBot'; ok $? 0 "stats table shows GPTBot"
ok "$(echo "$STATS" | $PHP -r '$j=json_decode(stream_get_contents(STDIN),true); echo ($j["human_hits"]>=1)?"yes":"no";')" yes "human page view counted"
ok "$(sqlite3 database/database.sqlite "select count(*) from pay_per_crawl_hits where ip_hash=''" 2>/dev/null || echo 0)" 0 "every hit carries a hashed source"
ok "$(curl -s -o /dev/null -w '%{content_type}' http://127.0.0.1:$APP_PORT/llms.txt)" "text/plain; charset=utf-8" "llms.txt content-type"
ok "$(curl -s -o /dev/null -w '%{content_type}' http://127.0.0.1:$APP_PORT/.well-known/x402.json)" "application/json; charset=utf-8" "manifest content-type"
ok "$( [ -f storage/logs/laravel.log ] && grep -ci 'error\|exception' storage/logs/laravel.log || echo 0)" 0 "no errors in laravel.log"
echo "laravel pass=$pass fail=$fail"
exit $((RC + fail))
