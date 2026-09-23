/**
 * quick_add.js
 *
 * Called from:
 * - receive_genetics.php (via genetics.js) and record_dry_weight.php (via
 *   transaction_form.js): "+ Add new genetics…" at the bottom of the
 *   genetics list.
 *
 * Why:
 * Adding something that's missing from a drop-down used to mean leaving the
 * page (and losing whatever was typed). This adds a "+ Add new…" choice to
 * the end of a <select>. Picking it opens a small pop-up form; saving it
 * adds the new entry to the list and selects it. Cancelling puts the list
 * back the way it was.
 *
 * The endpoint receives the pop-up's fields as a normal form POST and
 * answers with JSON: { success, message, id, name, duplicate }. A duplicate
 * (it already exists) is selected too, since that's what the user wanted.
 */

const QUICK_ADD_VALUE = '__quick_add__';

/**
 * @param {HTMLSelectElement} select
 * @param {Object} options
 * @param {string} options.optionLabel   e.g. '+ Add new genetics…'
 * @param {string} options.title         pop-up heading
 * @param {string} options.submitLabel   e.g. 'Add genetics'
 * @param {string} options.endpoint      relative URL of the JSON endpoint
 * @param {Object[]} options.fields      { name, label, type ('text'|'email'|'tel'|'textarea'), required, autocomplete }
 */
function enableQuickAdd(select, options) {
    let previous = select.value;

    // Always the last choice, even if the list is filled in again later
    let addOption = select.querySelector(`option[value="${QUICK_ADD_VALUE}"]`);
    if (!addOption) {
        addOption = document.createElement('option');
        addOption.value = QUICK_ADD_VALUE;
        addOption.textContent = options.optionLabel;
    }
    select.appendChild(addOption);

    if (select.dataset.quickAdd) {
        return; // already wired up, only the option needed moving
    }
    select.dataset.quickAdd = 'on';

    select.addEventListener('change', () => {
        if (select.value !== QUICK_ADD_VALUE) {
            previous = select.value;
            return;
        }
        openQuickAddForm(options).then(saved => {
            if (!saved) {
                select.value = previous; // cancelled: back to what it was
                return;
            }
            selectQuickAddOption(select, saved.id, saved.name);
            previous = select.value;
            // Let the page react as if the user had picked it themselves
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

/** Add (or find) an option for a saved entry, in alphabetical order, and select it. */
function selectQuickAddOption(select, id, name) {
    const value = String(id);
    let option = [...select.options].find(o => o.value === value);
    if (!option) {
        option = document.createElement('option');
        option.value = value;
        option.textContent = name;
        const before = [...select.options].find(o =>
            o.value !== '' && (o.value === QUICK_ADD_VALUE || o.textContent.localeCompare(name, undefined, { sensitivity: 'base' }) > 0));
        select.insertBefore(option, before || null);
    }
    select.value = value;
}

/**
 * Show the pop-up form. Resolves with the saved entry, or null if cancelled.
 * @returns {Promise<{id: number, name: string}|null>}
 */
function openQuickAddForm(options) {
    return new Promise(resolve => {
        const dialog = document.createElement('dialog');
        dialog.className = 'grace-modal quick-add';
        const fields = options.fields.map((field, index) => {
            const id = `quickAdd_${field.name}`;
            const attrs = `id="${id}" name="${escapeHtml(field.name)}"`
                + (field.required ? ' required' : '')
                + (field.autocomplete ? ` autocomplete="${escapeHtml(field.autocomplete)}"` : '')
                + (index === 0 ? ' autofocus' : '');
            const input = field.type === 'textarea'
                ? `<textarea ${attrs} rows="2"></textarea>`
                : `<input type="${escapeHtml(field.type || 'text')}" ${attrs}>`;
            return `<label for="${id}">${escapeHtml(field.label)}</label>${input}`;
        }).join('');
        dialog.innerHTML = `
            <article>
                <h3>${escapeHtml(options.title)}</h3>
                <form>
                    ${fields}
                    <p class="quick-add-error" role="alert" hidden></p>
                    <footer>
                        <button type="button" class="secondary" data-quick-add-cancel>Cancel</button>
                        <button type="submit">${escapeHtml(options.submitLabel)}</button>
                    </footer>
                </form>
            </article>`;
        document.body.appendChild(dialog);

        const form = dialog.querySelector('form');
        const error = dialog.querySelector('.quick-add-error');
        const submit = form.querySelector('button[type="submit"]');
        let finished = false;

        const close = (saved) => {
            if (finished) return;
            finished = true;
            dialog.close();
            dialog.remove();
            resolve(saved);
        };
        const showError = (message) => {
            error.textContent = message;
            error.hidden = false;
        };

        dialog.querySelector('[data-quick-add-cancel]').addEventListener('click', () => close(null));
        dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(null); });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!form.reportValidity()) return;
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            error.hidden = true;

            fetch(options.endpoint, { method: 'POST', body: new FormData(form) })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.duplicate) {
                        showToast(data.duplicate ? `${data.message} It's selected for you.` : data.message,
                            data.duplicate ? 'info' : 'success', 6000);
                        close({ id: data.id, name: data.name });
                    } else {
                        showError(data.message || 'That could not be saved.');
                    }
                })
                .catch(() => showError('That could not be saved. Check your connection and try again.'))
                .finally(() => {
                    submit.disabled = false;
                    submit.removeAttribute('aria-busy');
                });
        });

        dialog.showModal();
    });
}

/** "+ Add new genetics…" for a genetics drop-down. */
function enableQuickAddGenetics(select) {
    enableQuickAdd(select, {
        optionLabel: '+ Add new genetics…',
        title: 'Add a new genetics',
        submitLabel: 'Add genetics',
        endpoint: 'handle_quick_add_genetics.php',
        fields: [{ name: 'geneticsName', label: 'Genetics name', type: 'text', required: true, autocomplete: 'off' }]
    });
}
