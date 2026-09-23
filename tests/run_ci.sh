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

# Run Genetics Upgrade Test
echo ""
echo "--- Genetics Upgrade Test ---"
php tests/test_genetics_upgrade.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Genetics upgrade verified"
else
    echo -e "${RED}[FAIL]${NC} Genetics upgrade failed"
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

# Run Harvest Safeguard Test
echo ""
echo "--- Harvest Safeguard Test ---"
php tests/test_harvest_safeguards.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Harvest safeguards verified"
else
    echo -e "${RED}[FAIL]${NC} Harvest safeguards failed"
    FAILures=$((FAILures+1))
fi

# Run Annual Stocktake Test
echo ""
echo "--- Annual Stocktake Test ---"
php tests/test_annual_stocktake.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Annual stocktake logic verified"
else
    echo -e "${RED}[FAIL]${NC} Annual stocktake logic failed"
    FAILures=$((FAILures+1))
fi

# Run NZ Time Test
echo ""
echo "--- NZ Time Test ---"
php tests/test_nz_time.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} NZ time verified"
else
    echo -e "${RED}[FAIL]${NC} NZ time failed"
    FAILures=$((FAILures+1))
fi

# Run Report Reminder Test
echo ""
echo "--- Report Reminder Test ---"
php tests/test_report_reminders.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Report reminder logic verified"
else
    echo -e "${RED}[FAIL]${NC} Report reminder logic failed"
    FAILures=$((FAILures+1))
fi

# Run Report Period Test
echo ""
echo "--- Report Period Test ---"
php tests/test_report_periods.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Report periods verified"
else
    echo -e "${RED}[FAIL]${NC} Report periods failed"
    FAILures=$((FAILures+1))
fi

# Run Company Editing Test
echo ""
echo "--- Company Editing Test ---"
php tests/test_company_editing.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Company editing logic verified"
else
    echo -e "${RED}[FAIL]${NC} Company editing logic failed"
    FAILures=$((FAILures+1))
fi

# Run Own Company Test
echo ""
echo "--- Own Company Test ---"
php tests/test_own_company.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Own company details verified"
else
    echo -e "${RED}[FAIL]${NC} Own company details failed"
    FAILures=$((FAILures+1))
fi

# Run License Alerts Test
echo ""
echo "--- License Alerts Test ---"
php tests/test_license_alerts.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} License alert logic verified"
else
    echo -e "${RED}[FAIL]${NC} License alert logic failed"
    FAILures=$((FAILures+1))
fi

# Run License Expiry Limit Test
echo ""
echo "--- License Expiry Limit Test ---"
php tests/test_license_expiry.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} License expiry limit verified"
else
    echo -e "${RED}[FAIL]${NC} License expiry limit failed"
    FAILures=$((FAILures+1))
fi

# Run Download Filename Test
echo ""
echo "--- Download Filename Test ---"
php tests/test_download_names.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Download filename logic verified"
else
    echo -e "${RED}[FAIL]${NC} Download filename logic failed"
    FAILures=$((FAILures+1))
fi

# Run Genetics Duplicate Test
echo ""
echo "--- Genetics Duplicate Test ---"
php tests/test_genetics_duplicates.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Genetics duplicate check verified"
else
    echo -e "${RED}[FAIL]${NC} Genetics duplicate check failed"
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

# Run Flower Stock Test
echo ""
echo "--- Flower Stock Test ---"
php tests/test_flower_stock.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Flower stock checks verified"
else
    echo -e "${RED}[FAIL]${NC} Flower stock checks failed"
    FAILures=$((FAILures+1))
fi

# Run Entry Message Test
echo ""
echo "--- Entry Message Test ---"
php tests/test_entry_messages.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Stock on hand and entry messages verified"
else
    echo -e "${RED}[FAIL]${NC} Stock on hand and entry messages failed"
    FAILures=$((FAILures+1))
fi

# Run Confirm Entry Test
echo ""
echo "--- Confirm Entry Test ---"
php tests/test_confirm_entries.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Confirm steps verified"
else
    echo -e "${RED}[FAIL]${NC} Confirm steps failed"
    FAILures=$((FAILures+1))
fi

# Run Version Consistency Check
echo ""
echo "--- Version Consistency ---"
php tests/test_version_consistency.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Versions consistent"
else
    echo -e "${RED}[FAIL]${NC} Version verification failed"
    FAILures=$((FAILures+1))
fi

# Run Quick Add Test
echo ""
echo "--- Quick Add Test ---"
php tests/test_quick_add.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Quick add wiring verified"
else
    echo -e "${RED}[FAIL]${NC} Quick add wiring failed"
    FAILures=$((FAILures+1))
fi

# Run Syntax Check
echo ""
echo "--- PHP Syntax Check ---"
bash tests/syntax_check.sh
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} PHP Syntax verified"
else
    echo -e "${RED}[FAIL]${NC} PHP Syntax failed"
    FAILures=$((FAILures+1))
fi

# Run Company Adding Test
echo ""
echo "--- Company Adding Test ---"
php tests/test_company_adding.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[PASS]${NC} Company adding verified"
else
    echo -e "${RED}[FAIL]${NC} Company adding failed"
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
