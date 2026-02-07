#!/bin/bash

echo "Running Static Analysis Checks..."
FAIL=0

# 1. Verify 1MB limit in image-compress.js
# Searching for 1024 * 1024
if grep -q "1024 \* 1024" grace_addon/files/general/www/public/js/image-compress.js; then
    echo "[PASS] Verified 1MB limit in image-compress.js"
else
    echo "[FAIL] Could not find '1024 * 1024' (1MB limit) in image-compress.js"
    FAIL=1
fi

# 2. Verify /data/grace.db path in init_db.php
if grep -q "/data/grace.db" grace_addon/files/general/www/public/init_db.php; then
    echo "[PASS] Verified /data/grace.db path in init_db.php"
else
    echo "[FAIL] Could not find '/data/grace.db' in init_db.php"
    FAIL=1
fi

# 3. Verify /data/uploads/ path in upload.php
if grep -q "/data/uploads/" grace_addon/files/general/www/public/upload.php; then
    echo "[PASS] Verified /data/uploads/ in upload.php"
else
    echo "[FAIL] Could not find '/data/uploads/' in upload.php"
    FAIL=1
fi

# 4. Verify Timezone in init_db.php
if grep -q "Pacific/Auckland" grace_addon/files/general/www/public/init_db.php; then
    echo "[PASS] Verified Pacific/Auckland timezone in init_db.php"
else
    echo "[FAIL] Could not find 'Pacific/Auckland' timezone in init_db.php"
    FAIL=1
fi

# 5. Check for dangerous relative paths
if grep -r "__DIR__ \. '/uploads/'" grace_addon/files/general/www/public/*.php; then
    echo "[FAIL] Found dangerous relative path usage!"
    FAIL=1
else
    echo "[PASS] No dangerous relative upload paths found"
fi

echo "Static Analysis Complete"
if [ $FAIL -eq 1 ]; then
    exit 1
else
    exit 0
fi
