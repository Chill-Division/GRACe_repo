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

        setTimeout(() => {
            statusMessage.style.display = 'none';
            statusMessage.classList.remove(type);
        }, 5000);
    }

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
            alert('Please select a company for Testing or Send external transactions.');
            return;
        }
        this.submit();
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
        })
        .catch(error => console.error('Error fetching companies:', error));
}
