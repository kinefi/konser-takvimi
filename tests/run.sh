#!/usr/bin/env sh
# Runs the whole test suite locally: ./tests/run.sh
set -e
cd "$(dirname "$0")/.."

echo "== PHP lint =="
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l >/dev/null

echo "== JS syntax =="
for f in assets/js/*.js tests/*.js; do node --check "$f"; done

echo "== PHP tests =="
for f in tests/test_*.php; do echo "-- $f"; php "$f"; done

echo "== JS tests =="
node tests/tracker.test.js

echo "All tests passed."
