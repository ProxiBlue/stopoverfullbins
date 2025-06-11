// Initialize Google Places Autocomplete
function initAutocomplete() {
    const addressInput = document.getElementById('address');
    if (addressInput) {
        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
            componentRestrictions: { country: 'au' }, // Restrict to Australia
            fields: ['address_components', 'formatted_address'],
            types: ['address']
        });

        // When the user selects an address from the dropdown, populate the address field
        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (place.formatted_address) {
                addressInput.value = place.formatted_address;
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('reportForm');
    const fileInput = document.getElementById('images');
    const fileUploadButton = document.querySelector('.file-upload-button');
    const fileCount = document.getElementById('file-count');
    const previewContainer = document.getElementById('image-preview-container');
    const messageField = document.getElementById('message');
    const notification = document.getElementById('notification');
    const sendingOverlay = document.getElementById('sending-overlay');

    // Array to store selected files
    let selectedFiles = [];
    // Array to store uploaded files
    let uploadedFiles = [];
    // Array to store pending uploads
    let pendingUploads = [];

    // Set default message
    if (!messageField.value) {
        messageField.value = DEFAULT_MESSAGE;
    }

    // Handle file upload button click
    fileUploadButton.addEventListener('click', function() {
        fileInput.click();
    });

    // Add direct click handler to submit button as a backup
    document.getElementById('submitBtn').addEventListener('click', function(e) {
        console.log('Submit button clicked directly');
        // Check if files are selected
        if (selectedFiles.length === 0) {
            console.log('No files selected (direct button check)');
            e.preventDefault();
            showNotification('Please upload at least one image', 'error', true);
            // Highlight the file input area to draw attention
            document.querySelector('.file-upload-container').classList.add('highlight-required');
            // Show the file error message
            const errorMsg = document.getElementById('file-error-message');
            errorMsg.classList.remove('hidden');
            // Scroll to the file input if it's not in view
            document.getElementById('images').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
    });

    // Handle file selection and preview
    fileInput.addEventListener('change', function() {
        // Remove highlight if it exists
        document.querySelector('.file-upload-container').classList.remove('highlight-required');

        // Hide the file error message
        document.getElementById('file-error-message').classList.add('hidden');

        // Hide any persistent notifications
        if (!notification.classList.contains('hidden')) {
            notification.classList.add('hidden');
        }

        // Add newly selected files to our array
        const newFiles = Array.from(fileInput.files);

        // Add new files to our selectedFiles array
        newFiles.forEach(file => {
            // Check if we already have 5 files
            if (selectedFiles.length >= 5) {
                showNotification('Maximum 5 images allowed', 'error');
                return;
            }

            // Check if file is an image
            if (!file.type.match('image.*')) {
                return;
            }

            // Add file to selectedFiles array
            selectedFiles.push(file);

            // Upload file immediately
            const fileFormData = new FormData();
            fileFormData.append('images', file);
            fileFormData.append('file_upload_only', 'true');

            // Add to pending uploads
            const uploadPromise = uploadFile(file, fileFormData);
            pendingUploads.push(uploadPromise);

            uploadPromise
                .then(result => {
                    if (result.success && result.filename) {
                        uploadedFiles.push(result.filename);
                    }
                    // Remove from pending uploads
                    const index = pendingUploads.indexOf(uploadPromise);
                    if (index !== -1) {
                        pendingUploads.splice(index, 1);
                    }
                })
                .catch(error => {
                    console.error('Error uploading file:', error);
                    // Remove from pending uploads
                    const index = pendingUploads.indexOf(uploadPromise);
                    if (index !== -1) {
                        pendingUploads.splice(index, 1);
                    }
                });
        });

        // Limit to 5 files
        if (selectedFiles.length > 5) {
            selectedFiles = selectedFiles.slice(0, 5);
        }

        // Update the file input with our selectedFiles
        updateFileInputWithSelectedFiles();

        // Update UI
        updateFileCount();
        displayImagePreviews();
    });

    // Update the file input with our selected files
    function updateFileInputWithSelectedFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => {
            dt.items.add(file);
        });
        fileInput.files = dt.files;
    }

    // Update file count display
    function updateFileCount() {
        const numFiles = selectedFiles.length;
        fileCount.textContent = numFiles === 1 
            ? '1 file selected' 
            : `${numFiles} files selected`;

        // Warn if too many files
        if (numFiles > 5) {
            showNotification('Please select a maximum of 5 images', 'error');
        }
    }

    // Display image previews
    function displayImagePreviews() {
        previewContainer.innerHTML = '';

        const maxFiles = 5;

        // Only preview up to 5 files
        for (let i = 0; i < Math.min(selectedFiles.length, maxFiles); i++) {
            const file = selectedFiles[i];

            // Only process image files
            if (!file.type.match('image.*')) {
                continue;
            }

            const reader = new FileReader();

            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.className = 'image-preview';

                const img = document.createElement('img');
                img.src = e.target.result;

                const removeButton = document.createElement('div');
                removeButton.className = 'remove-image';
                removeButton.innerHTML = '×';
                removeButton.dataset.index = i;
                removeButton.addEventListener('click', function() {
                    removeImage(this.dataset.index);
                });

                previewDiv.appendChild(img);
                previewDiv.appendChild(removeButton);
                previewContainer.appendChild(previewDiv);
            };

            reader.readAsDataURL(file);
        }
    }

    // Remove image from selection
    function removeImage(index) {
        // Remove the file from our selectedFiles array
        const removedFile = selectedFiles[index];
        selectedFiles.splice(index, 1);

        // Always try to remove the corresponding upload progress indicator
        if (removedFile) {
            const progressItem = document.getElementById(`progress-${removedFile.name.replace(/[^a-zA-Z0-9]/g, '-')}`);
            if (progressItem) {
                progressItem.remove();
            }
        }

        // Check if there's a pending upload for this file and remove it
        if (removedFile && pendingUploads.length > 0) {
            // We can't directly identify which promise belongs to which file,
            // but we can mark the upload as canceled in the UI
            const progressItem = document.getElementById(`progress-${removedFile.name.replace(/[^a-zA-Z0-9]/g, '-')}`);
            if (progressItem) {
                const statusText = progressItem.querySelector('.status');
                if (statusText) {
                    statusText.textContent = 'Upload canceled';
                    progressItem.classList.add('canceled');
                }
            }
        }

        // Also remove from uploadedFiles array if it exists
        if (removedFile && uploadedFiles.length > 0) {
            // Find the corresponding uploaded file
            const uploadedIndex = uploadedFiles.findIndex(filename => 
                filename.includes(removedFile.name.replace(/[^a-zA-Z0-9]/g, '-')));

            if (uploadedIndex !== -1) {
                const filename = uploadedFiles[uploadedIndex];

                // Remove from uploadedFiles array
                uploadedFiles.splice(uploadedIndex, 1);

                // Send request to delete file from server
                const formData = new FormData();
                formData.append('filename', filename);

                fetch('server/delete_file.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        console.log('File deleted from server:', filename);
                    } else {
                        console.error('Failed to delete file from server:', result.message);
                    }
                })
                .catch(error => {
                    console.error('Error deleting file from server:', error);
                });
            }
        }

        // Update the file input with our selectedFiles
        updateFileInputWithSelectedFiles();

        // Update UI
        updateFileCount();
        displayImagePreviews();
    }

    // Show notification
    function showNotification(message, type, persistent = false) {
        console.log('Showing notification:', message, 'Type:', type, 'Persistent:', persistent);
        notification.textContent = message;
        notification.className = `notification ${type}`;

        // Remove hidden class to show notification
        setTimeout(() => {
            notification.classList.remove('hidden');
            console.log('Notification visible:', !notification.classList.contains('hidden'));
        }, 10);

        // Hide notification after 3 seconds unless it's persistent
        if (!persistent) {
            setTimeout(() => {
                notification.classList.add('hidden');
                console.log('Notification hidden after timeout');
            }, 3000);
        } else {
            console.log('Notification will remain visible (persistent)');
        }
    }

    // Show sending overlay
    function showSendingOverlay() {
        sendingOverlay.classList.remove('hidden');
    }

    // Hide sending overlay
    function hideSendingOverlay() {
        sendingOverlay.classList.add('hidden');
    }

    // Create progress element for a file
    function createProgressElement(file) {
        const progressItem = document.createElement('div');
        progressItem.className = 'upload-progress-item';
        progressItem.id = `progress-${file.name.replace(/[^a-zA-Z0-9]/g, '-')}`;

        const fileName = document.createElement('span');
        fileName.className = 'file-name';
        fileName.textContent = file.name;

        const progressBarContainer = document.createElement('div');
        progressBarContainer.className = 'progress-bar-container';

        const progressBar = document.createElement('div');
        progressBar.className = 'progress-bar';

        const status = document.createElement('span');
        status.className = 'status';
        status.textContent = 'Waiting...';

        progressBarContainer.appendChild(progressBar);
        progressItem.appendChild(fileName);
        progressItem.appendChild(progressBarContainer);
        progressItem.appendChild(status);

        return progressItem;
    }

    // Upload a single file with progress tracking
    function uploadFile(file, formData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const progressContainer = document.getElementById('upload-progress-container');

            // Create progress element if it doesn't exist
            let progressItem = document.getElementById(`progress-${file.name.replace(/[^a-zA-Z0-9]/g, '-')}`);
            if (!progressItem) {
                progressItem = createProgressElement(file);
                progressContainer.appendChild(progressItem);
            }

            const progressBar = progressItem.querySelector('.progress-bar');
            const statusText = progressItem.querySelector('.status');

            // Update progress
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    statusText.textContent = `Uploading: ${percentComplete}%`;
                }
            });

            // Handle response
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    progressItem.classList.add('complete');
                    statusText.textContent = 'Upload complete';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve({ success: true, filename: file.name });
                    }
                } else {
                    progressItem.classList.add('error');
                    statusText.textContent = 'Upload failed';
                    reject(new Error('Upload failed'));
                }
            };

            // Handle errors
            xhr.onerror = function() {
                progressItem.classList.add('error');
                statusText.textContent = 'Upload failed';
                reject(new Error('Network error'));
            };

            // Set up and send request
            xhr.open('POST', 'server/submit.php', true);
            xhr.send(formData);
        });
    }

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validate form
        const email = document.getElementById('email').value;
        const address = document.getElementById('address').value;
        const message = messageField.value;

        console.log('Form submitted. Selected files:', selectedFiles.length);

        if (!email) {
            showNotification('Please enter your email address', 'error');
            return;
        }

        if (!address) {
            showNotification('Please enter an address', 'error');
            return;
        }

        if (!message) {
            showNotification('Please enter a message', 'error');
            return;
        }

        if (selectedFiles.length === 0) {
            console.log('No files selected, showing validation feedback');
            showNotification('Please upload at least one image', 'error', true);
            // Highlight the file input area to draw attention
            document.querySelector('.file-upload-container').classList.add('highlight-required');
            // Show the file error message
            const errorMsg = document.getElementById('file-error-message');
            errorMsg.classList.remove('hidden');
            console.log('Error message visibility:', !errorMsg.classList.contains('hidden'));
            // Scroll to the file input if it's not in view
            document.getElementById('images').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (selectedFiles.length > 5) {
            showNotification('Please select a maximum of 5 images', 'error');
            return;
        }

        // Get submit button reference
        const submitBtn = document.getElementById('submitBtn');
        const originalBtnText = submitBtn.textContent;

        try {
            // Show loading state
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;
            // Show sending overlay
            showSendingOverlay();

            // Check if there are pending uploads
            if (pendingUploads.length > 0) {
                showNotification('Please wait for all files to finish uploading...', 'info');

                try {
                    // Wait for all pending uploads to complete
                    await Promise.all(pendingUploads);
                } catch (error) {
                    console.error('Error waiting for uploads to complete:', error);
                    showNotification('Error uploading files. Please try again.', 'error');

                    // Reset button state
                    submitBtn.textContent = originalBtnText;
                    submitBtn.disabled = false;
                    // Hide sending overlay
                    hideSendingOverlay();
                    return;
                }
            }

            // Submit the form with references to already uploaded files
            const formData = new FormData();
            formData.append('email', email);
            formData.append('address', address);
            formData.append('message', message);

            // Add uploaded file references
            uploadedFiles.forEach(filename => {
                formData.append('uploaded_files[]', filename);
            });

            // Send form data to server
            const response = await fetch('server/submit.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            // Reset button state
            submitBtn.textContent = originalBtnText;
            submitBtn.disabled = false;
            // Hide sending overlay
            hideSendingOverlay();

            if (result.success) {
                showNotification(result.message || 'Your report has been submitted. Please check your email to verify your submission.', 'success');
                form.reset();
                previewContainer.innerHTML = '';
                fileCount.textContent = '0 files selected';

                // Clear our arrays
                selectedFiles = [];
                uploadedFiles = [];

                // Reset the file input
                updateFileInputWithSelectedFiles();

                // Clear upload progress indicators
                document.getElementById('upload-progress-container').innerHTML = '';

                // Reset the message field
                messageField.value = DEFAULT_MESSAGE;
            } else {
                showNotification(result.message || 'An error occurred. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');

            // Reset button state
            submitBtn.textContent = originalBtnText;
            submitBtn.disabled = false;
            // Hide sending overlay
            hideSendingOverlay();
        }
    });
});
