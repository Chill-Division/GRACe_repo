/**
 * transaction_form.js
 * 
 * Called from:
 * - record_dry_weight.php: To handle the "Record Flower Transaction" form.
 * 
 * Why:
 * Manages complex form logic including:
 * - Dynamic "Reason" options based on "Transaction Type" (Add vs Subtract).
 * - Showing/hiding "Company" dropdown for specific reasons.
 * - Form validation and status message display.
 */

function initTransactionForm() {
    const form = document.getElementById('recordFlowerTransactionForm');
    const statusMessage = document.getElementById('statusMessage');
    const geneticsDropdown = document.getElementById('geneticsName');
    const transactionTypeDropdown = document.getElementById('transactionType');
    const reasonDropdown = document.getElementById('reason');
    const companySelection = document.getElementById('companySelection');
    const companyDropdown = document.getElementById('companyId');
    const otherReasonSection = document.getElementById('otherReasonSection');

    // Check if there's a success or error message in the URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    const errorMessage = urlParams.get('error');

    if (successMessage) {
        showStatusMessage(successMessage, 'success');
        form.reset();
    } else if (errorMessage) {
        showStatusMessage(errorMessage, 'error');
    }

    // Populate form with submitted data if there was an error
    const submittedData = JSON.parse(urlParams.get('data') || '{}');
    if (submittedData.geneticsName) form.geneticsName.value = submittedData.geneticsName;
    if (submittedData.weight) form.weight.value = submittedData.weight;
    if (submittedData.transactionType) form.transactionType.value = submittedData.transactionType;
    if (submittedData.reason) form.reason.value = submittedData.reason;

    if (submittedData.reason === 'Other') {
        otherReasonSection.style.display = 'block';
        if (submittedData.otherReason) form.otherReason.value = submittedData.otherReason;
    }
    if (submittedData.transactionType === 'Subtract' &&
        (submittedData.reason === 'Testing' || submittedData.reason === 'Send external')) {
        companySelection.style.display = 'block';
        if (submittedData.companyId) form.companyId.value = submittedData.companyId;
    }

    function showStatusMessage(message, type) {
        statusMessage.textContent = message;
        statusMessage.classList.add(type);
        statusMessage.style.display = 'block';
        statusMessage.setAttribute('role', type === 'error' ? 'alert' : 'status');

        // Errors stay until the next entry, so there's time to read why
        if (type !== 'error') {
            setTimeout(() => {
                statusMessage.style.display = 'none';
                statusMessage.classList.remove(type);
            }, 8000);
        }
    }

    // Flower on hand for the chosen genetics, and what it will be after this
    // entry. The server refuses a Subtract of more than is on hand; this
    // just warns before you get that far.
    const stockHint = document.getElementById('stockHint');
    let stock = {};
    function updateStockHint() {
        if (!stockHint) return;
        const id = geneticsDropdown.value;
        if (!/^\d+$/.test(id)) {
            stockHint.textContent = ''; // nothing chosen, or "+ Add new genetics…"
            stockHint.classList.remove('stock-hint--warning');
            return;
        }
        const name = geneticsDropdown.options[geneticsDropdown.selectedIndex].textContent;
        const onHand = stock[id] ? stock[id].flower : 0;
        const weight = parseFloat(form.weight.value);
        let text = `On hand: ${formatGrams(onHand)} g of ${name}.`;
        let warning = false;
        if (weight > 0) {
            const after = transactionTypeDropdown.value === 'Subtract' ? onHand - weight : onHand + weight;
            if (after < -0.001) {
                text += ' That is more than is on hand.';
                warning = true;
            } else {
                text += ` After this: ${formatGrams(after)} g.`;
            }
        }
        stockHint.textContent = text;
        stockHint.classList.toggle('stock-hint--warning', warning);
    }
    [geneticsDropdown, transactionTypeDropdown].forEach(el => el.addEventListener('change', updateStockHint));
    form.weight.addEventListener('input', updateStockHint);
    fetch('get_stock_on_hand.php')
        .then(response => response.json())
        .then(data => { stock = data || {}; updateStockHint(); })
        .catch(error => console.error('Error fetching stock on hand:', error));

    transactionTypeDropdown.addEventListener('change', updateReasonOptions);
    reasonDropdown.addEventListener('change', updateCompanyVisibility);

    function updateReasonOptions() {
        // Save current selection if possible, though options change
        const currentReason = reasonDropdown.value;

        reasonDropdown.innerHTML = '<option value="" disabled selected>Select Reason</option>';
        if (transactionTypeDropdown.value === 'Subtract') {
            reasonDropdown.innerHTML += `
                <option value="Testing">Testing</option>
                <option value="Destroy">Destroy</option>
                <option value="Send external">Send External</option>
                <option value="Other">Other</option>
            `;
        } else {
            reasonDropdown.innerHTML += `
                <option value="Harvest">Harvest</option>
                <option value="Other">Other</option>
            `;
        }

        // Try to restore selection if it exists in new options
        // But usually when type changes, reason invalidates. 
        // We only restore if it was pre-populated by PHP reload logic, handled above by explicit value setting.
        // If user changes type manually, we reset reason.

        updateCompanyVisibility();
    }

    function updateCompanyVisibility() {
        if (transactionTypeDropdown.value === 'Subtract' &&
            (reasonDropdown.value === 'Testing' || reasonDropdown.value === 'Send external')) {
            companySelection.style.display = 'block';
            companyDropdown.required = true;
        } else {
            companySelection.style.display = 'none';
            companyDropdown.required = false;
        }
        otherReasonSection.style.display = reasonDropdown.value === 'Other' ? 'block' : 'none';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (transactionTypeDropdown.value === 'Subtract' &&
            (reasonDropdown.value === 'Testing' || reasonDropdown.value === 'Send external') &&
            !companyDropdown.value) {
            showToast('Please select a company for Testing or Send external transactions.', 'error');
            return;
        }

        // Show exactly what's about to be recorded before saving it: the
        // ledger can't be edited afterwards
        const type = transactionTypeDropdown.value;
        const reason = reasonDropdown.value;
        const verb = type === 'Subtract' ? 'Subtract' : 'Add';
        const grams = parseFloat(form.weight.value);
        const geneticsName = geneticsDropdown.options[geneticsDropdown.selectedIndex].textContent;
        const onHand = stock[geneticsDropdown.value] ? stock[geneticsDropdown.value].flower : 0;
        const items = [
            `${verb} ${formatGrams(grams)} g of ${geneticsName}`,
            `Reason: ${reason === 'Other' ? form.otherReason.value.trim() : reason}`
        ];
        if (companySelection.style.display !== 'none' && companyDropdown.value) {
            items.push(`Company: ${companyDropdown.options[companyDropdown.selectedIndex].textContent}`);
        }
        items.push(`On hand after: ${formatGrams(type === 'Subtract' ? onHand - grams : onHand + grams)} g`);
        // Bigger than the large-entry limit (Administration → Entry warning
        // limits)? Then it has to be ticked as right, to catch an extra zero
        const largeGrams = parseInt(form.dataset.largeGrams, 10) || 0;
        const isLarge = largeGrams > 0 && grams > largeGrams;

        confirmAction({
            title: `${verb} ${formatGrams(grams)} g of ${geneticsName}?`,
            message: "This is recorded in the ledger and can't be edited afterwards.",
            items: items,
            confirmLabel: `${verb} ${formatGrams(grams)} g`,
            danger: reason === 'Destroy',
            warning: isLarge ? `That's more than ${formatGrams(largeGrams)} g, your large-entry limit. Check the weight before you confirm.` : '',
            warningTick: `Yes, ${formatGrams(grams)} g is right`
        }).then(confirmed => {
            if (!confirmed) return;
            form.submit();
        });
    });

    // Initial setup
    updateReasonOptions();

    // Re-apply values after updateReasonOptions resets them (for page reload case)
    if (submittedData.reason) {
        // We need to ensure the options are correct before setting value
        if ((submittedData.transactionType === 'Subtract' && ['Testing', 'Destroy', 'Send external', 'Other'].includes(submittedData.reason)) ||
            (submittedData.transactionType !== 'Subtract' && ['Harvest', 'Other'].includes(submittedData.reason))) {
            reasonDropdown.value = submittedData.reason;
        }
        updateCompanyVisibility();
    }


    // Fetch and populate genetics dropdown
    fetch('get_genetics.php')
        .then(response => response.json())
        .then(genetics => {
            genetics.forEach(genetic => {
                const option = document.createElement('option');
                option.value = genetic.id;
                option.textContent = genetic.name;
                geneticsDropdown.appendChild(option);
            });
            // Re-select if needed
            if (submittedData.geneticsName) geneticsDropdown.value = submittedData.geneticsName;
            updateStockHint();

            // "+ Add new genetics…" at the bottom of the list (quick_add.js)
            if (typeof enableQuickAddGenetics === 'function') enableQuickAddGenetics(geneticsDropdown);
        })
        .catch(error => console.error('Error fetching genetics:', error));

    // Fetch and populate company dropdown
    fetch('get_companies.php')
        .then(response => response.json())
        .then(companies => {
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name;
                companyDropdown.appendChild(option);
            });
            // Re-select if needed
            if (submittedData.companyId) companyDropdown.value = submittedData.companyId;

            // "+ Add new company…" at the bottom of the list (quick_add.js)
            if (typeof enableQuickAddCompany === 'function') enableQuickAddCompany(companyDropdown);
        })
        .catch(error => console.error('Error fetching companies:', error));
}
