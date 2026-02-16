#!/bin/bash

echo "Running PHP Syntax Checks..."
FAIL=0

find grace_addon/files/general/www/public/ -name "*.php" -print0 | while IFS= read -r -d '' file; do
    OUTPUT=$(php -l "$file" 2>&1)
    if [ $? -ne 0 ]; then
        echo "[FAIL] Syntax error in $file"
        echo "$OUTPUT"
        FAIL=1
    fi
done

if [ $FAIL -eq 1 ]; then
    echo "Syntax checks failed"
    exit 1
else
    echo "[PASS] No PHP syntax errors found"
    exit 0
fi
