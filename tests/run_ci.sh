#!/bin/bash

# Define colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo "=========================================="
echo "   GRACe Local CI/Test Suite"
echo "=========================================="

FAILures=0

# Run Database Migration Test
echo ""
echo "--- Database Migration Test ---"
OUTPUT=$(php tests/test_db_migration.php 2>&1)
EXIT_CODE=$?
echo "$OUTPUT"

if [ $EXIT_CODE -eq 0 ] && [[ "$OUTPUT" != *"Database error"* ]] && [[ "$OUTPUT" != *"[FAIL]"* ]]; then
    echo -e "${GREEN}[PASS]${NC} DB Migration verified"
else
    echo -e "${RED}[FAIL]${NC} DB Migration failed"
    FAILures=$((FAILures+1))
fi

# Run Permission Logic Test
echo ""
echo "--- Permission Logic Test ---"
php tests/test_permissions.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Permissions logic verified"
else
    echo -e "${RED}[FAIL]${NC} Permissions logic failed"
    FAILures=$((FAILures+1))
fi

# Run Static Checks
echo ""
echo "--- Static Analysis ---"
bash tests/static_checks.sh
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Static checks passed"
else
    echo -e "${RED}[FAIL]${NC} Static checks failed"
    FAILures=$((FAILures+1))
fi

echo ""
echo "=========================================="
if [ $FAILures -eq 0 ]; then
    echo -e "${GREEN}ALL CHECKS PASSED${NC}"
    exit 0
else
    echo -e "${RED}$FAILures CHECKS FAILED${NC}"
    exit 1
fi
