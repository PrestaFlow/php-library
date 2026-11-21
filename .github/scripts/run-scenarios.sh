#!/bin/sh
# Runs the library's scenario suites against the shop the job started, one
# JUnit report per suite, then checks that nothing failed or skipped
# unexpectedly (see check-scenario-results.php).
#
# Usage: run-scenarios.sh <cell>     (e.g. 9.2.0-hummingbird)
# Expects the PRESTAFLOW_* variables of the shop in the environment.
set -u

cell="$1"
out="scenario-output"
mkdir -p "$out"

# Order matters:
# - EnsureTestAccount first: it creates the customer account (FO_EMAIL /
#   FO_PASSWD) the logged-in scenarios use.
# - The One Page Checkout scenarios last: they switch the shop to the one page
#   layout and leave it so; every four-page scenario switches it back, but
#   only on its own run.
suites="EnsureTestAccount FrontOfficeHealth AddProductToCart CartQuantities CheckoutOrder GuestCheckout OrderLifecycle CustomerOrderHistory ContactForm OutOfStockProduct ProductSearch OnePageCheckoutGuest OnePageCheckoutOrder"

reports=""
for suite in $suites; do
  start=$(date +%s)
  php bin/prestaflow run --junit="$out/$suite.xml" "src/Tests/Suites/Scenarios/$suite.php" > "$out/$suite.log" 2>&1
  echo "$suite: exit $? in $(( $(date +%s) - start ))s"
  grep -E 'PASS|FAIL|SKIP|⨯' "$out/$suite.log" | sed 's/^/    /'
  if [ -d prestaflow ]; then
    mkdir -p "$out/$suite"
    cp -R prestaflow/. "$out/$suite/"
  fi
  reports="$reports $out/$suite.xml"
done

# shellcheck disable=SC2086
php .github/scripts/check-scenario-results.php .github/scenario-expected-skips.json "$cell" $reports
