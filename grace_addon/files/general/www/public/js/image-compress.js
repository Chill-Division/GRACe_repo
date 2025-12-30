/**
 * Compresses an image file if it's over the size limit
 * @param {File} file - The image file to compress
 * @param {number} maxSizeBytes - Maximum file size in bytes (default: 1MB)
 * @param {number} maxWidth - Maximum width in pixels (default: 1920)
 * @param {number} maxHeight - Maximum height in pixels (default: 1920)
 * @returns {Promise<File>} - Compressed file as a Blob/File
 */
async function compressImage(file, maxSizeBytes = 1024 * 1024, maxWidth = 1920, maxHeight = 1920) {
    if (!file.type.match(/^image\//)) {
        return file;
    }

    if (file.size <= maxSizeBytes) {
        return file;
    }

    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = new Image();
            
            img.onload = function() {
                let width = img.width;
                let height = img.height;
                
                if (width > maxWidth || height > maxHeight) {
                    const ratio = Math.min(maxWidth / width, maxHeight / height);
                    width = width * ratio;
                    height = height * ratio;
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');

                ctx.drawImage(img, 0, 0, width, height);
                
                let quality = 0.9;
                let attempts = 0;
                const maxAttempts = 15;
                let currentWidth = width;
                let currentHeight = height;
                
                function tryCompress() {
                    if (attempts > 10 && currentWidth > 800 && currentHeight > 800) {
                        currentWidth = Math.floor(currentWidth * 0.8);
                        currentHeight = Math.floor(currentHeight * 0.8);
                        canvas.width = currentWidth;
                        canvas.height = currentHeight;
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, currentWidth, currentHeight);
                    }
                    
                    canvas.toBlob(function(blob) {
                        if (!blob) {
                            reject(new Error('Failed to compress image'));
                            return;
                        }

                        if (blob.size <= maxSizeBytes || attempts >= maxAttempts) {
                            const compressedFile = new File([blob], file.name, {
                                type: file.type,
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        } else {
                            quality = Math.max(0.1, quality - 0.05);
                            attempts++;
                            tryCompress();
                        }
                    }, file.type, quality);
                }
                
                tryCompress();
            };
            
            img.onerror = function() {
                reject(new Error('Failed to load image'));
            };
            
            img.src = e.target.result;
        };
        
        reader.onerror = function() {
            reject(new Error('Failed to read file'));
        };
        
        reader.readAsDataURL(file);
    });
}

/**
 * Formats file size for display
 * @param {number} bytes - File size in bytes
 * @returns {string} - Formatted file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

