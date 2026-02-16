/**
 * documents.js
 * 
 * Called from:
 * - company_licenses.php: For License uploads (includes expiry/acknowledgment logic).
 * - sops.php: For SOP uploads.
 * - offtake_agreements.php: For Offtake Agreement uploads.
 * - police_vet_check_records.php: For Police Vet Check Record uploads.
 * - chain_of_custody_documents.php: For Chain of Custody uploads.
 * 
 * Why:
 * Shared logic for file listing, sorting, uploading (with image compression), 
 * and optional expiry/acknowledgment features across all document management pages.
 */

/**
 * Initialize the document manager for a specific page.
 * @param {Object} options Configuration options
 * @param {string} options.category The document category (e.g., 'licenses', 'sops')
 * @param {boolean} options.hasExpiry Whether the documents have expiry dates (default: false)
 * @param {boolean} options.hasAcknowledgment Whether the documents require acknowledgment (default: false)
 */
function initDocumentManager(options) {
    const { category, hasExpiry = false, hasAcknowledgment = false } = options;
    const sortOrderSelect = $('#sortOrder');
    const fileListBody = $('#fileList');
    const uploadForm = $('#uploadForm');
    const sortContainer = $('#sortContainer');

    // Load files function
    function loadFiles() {
        const order = sortOrderSelect.val();
        $.get('fetch_files.php', { category: category, order: order }, function (files) {
            fileListBody.empty();
            if (files.length === 0) {
                // Determine column count based on features
                let colSpan = 3; // Name, Date, Download
                if (hasExpiry) colSpan++;
                if (hasAcknowledgment) colSpan++;

                fileListBody.append(`<tr><td colspan="${colSpan}">No records found.</td></tr>`);
                sortContainer.hide();
            } else {
                sortContainer.show();
                const today = new Date();
                const warningDate = new Date();
                warningDate.setDate(today.getDate() + 3);

                files.forEach(file => {
                    let rowStyle = '';
                    let expiryCell = '';
                    let actionCell = '';

                    // Expiry Logic
                    if (hasExpiry) {
                        let expiryDate = file.expiry_date ? new Date(file.expiry_date) : null;
                        let isAck = file.acknowledged == 1;
                        let showAck = false;

                        if (expiryDate) {
                            if (expiryDate <= warningDate) {
                                rowStyle = 'style="background-color: rgba(217, 53, 38, 0.1);"'; // Light red hint
                                if (!isAck) {
                                    showAck = true;
                                }
                            }
                        }
                        expiryCell = `<td>${file.expiry_date || '-'}</td>`;

                        // Acknowledgment Logic (Only if hasExpiry + hasAcknowledgment check)
                        if (hasAcknowledgment) {
                            let ackButton = showAck
                                ? `<button class="secondary outline" onclick="acknowledgeAlert(${file.id})">Acknowledge Alert</button>`
                                : (isAck ? '<small>Acknowledged</small>' : '');
                            actionCell = `<td>${ackButton}</td>`;
                        }
                    }

                    fileListBody.append(`
                        <tr ${rowStyle}>
                            <td>${file.original_filename}</td>
                            <td>${file.upload_date}</td>
                            ${expiryCell}
                            <td><a href="download.php?category=${category}&file=${encodeURIComponent(file.unique_filename)}" download><i class="fa-solid fa-download"></i> Download</a></td>
                            ${actionCell}
                        </tr>
                    `);
                });
            }
        }, 'json');
    }

    // Sort Handler
    sortOrderSelect.change(loadFiles);

    // Initial Load
    loadFiles();

    // Upload Handler
    uploadForm.submit(async function (e) {
        e.preventDefault();
        const form = this;
        const fileInput = form.querySelector('input[type="file"]');
        const expiryInput = form.querySelector('input[name="expiry_date"]');
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;

        if (!fileInput.files || !fileInput.files[0]) {
            alert('Please select a file to upload');
            return;
        }

        let file = fileInput.files[0];
        const originalSize = file.size;

        submitButton.disabled = true;
        submitButton.textContent = 'Processing...';

        try {
            if (file.type.match(/^image\//)) {
                if (file.size > 1024 * 1024) {
                    submitButton.textContent = 'Compressing image...';
                    if (typeof compressImage === 'function') {
                        file = await compressImage(file, 1024 * 1024);
                        console.log(`Image compressed from ${formatFileSize(originalSize)} to ${formatFileSize(file.size)}`);
                    } else {
                        console.warn('compressImage function not found, skipping compression.');
                    }
                }
            }

            const formData = new FormData();
            formData.append('file', file, file.name);
            formData.append('category', category);

            if (hasExpiry && expiryInput && expiryInput.value) {
                formData.append('expiry_date', expiryInput.value);
            }

            submitButton.textContent = 'Uploading...';

            $.ajax({
                url: 'upload.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('File uploaded successfully');
                        form.reset();
                        loadFiles();
                    } else {
                        alert('Upload failed: ' + (result.message || 'Unknown error'));
                    }
                },
                error: function (xhr, status, error) {
                    let errorMsg = 'Upload failed';
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMsg = response.message || errorMsg;
                        } catch (e) {
                            errorMsg = xhr.responseText || errorMsg;
                        }
                    }
                    alert('Upload error: ' + errorMsg);
                },
                complete: function () {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            });
        } catch (error) {
            alert('Error processing file: ' + error.message);
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    });

    // Expose acknowledge function globally if needed (since it was called via onclick string)
    // A clearer way would be to use event delegation, but for now we'll match existing behavior
    // or attach it to window.
    window.acknowledgeAlert = function (id) {
        if (confirm('Acknowledge this expiry alert? It will disappear from the top banner.')) {
            $.post('acknowledge_license.php', { id: id }, function (res) {
                const data = JSON.parse(res);
                if (data.success) {
                    loadFiles(); // Reload to update UI
                    location.reload(); // Reload to update banner
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            });
        }
    };
}
