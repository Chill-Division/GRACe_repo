#!/bin/bash
# php -l on every PHP file in the add-on (or in the folder given, which
# tests/test_check_scripts.php uses to prove a broken file fails the run).

DIR="${1:-grace_addon/files/general/www/public/}"
echo "Running PHP Syntax Checks..."
FAIL=0

# Read find's output with process substitution, not a pipe: a piped loop runs
# in a subshell, so FAIL=1 was lost and broken files still "passed"
while IFS= read -r -d '' file; do
    OUTPUT=$(php -l "$file" 2>&1)
    if [ $? -ne 0 ]; then
        echo "[FAIL] Syntax error in $file"
        echo "$OUTPUT"
        FAIL=1
    fi
done < <(find "$DIR" -name "*.php" -print0)

if [ $FAIL -eq 1 ]; then
    echo "Syntax checks failed"
    exit 1
else
    echo "[PASS] No PHP syntax errors found"
    exit 0
fi
