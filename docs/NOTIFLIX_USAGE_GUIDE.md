# Notiflix Usage Guide

This guide explains how to use Notiflix for notifications, confirmations, loading states, and reports throughout the iScan application.

## Table of Contents
1. [Setup](#setup)
2. [Notifications](#notifications)
3. [Confirmations](#confirmations)
4. [Loading States](#loading-states)
5. [Reports](#reports)
6. [Examples](#examples)

---

## Setup

### Include Notiflix in Your Page

Add these lines to the `<head>` section of your PHP page:

```html
<!-- Notiflix - Modern Notification Library -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
<script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js"></script>

<!-- Notiflix Configuration -->
<script src="../assets/js/notiflix-config.js"></script>
```

---

## Notifications

### Simple Notifications

```javascript
// Success notification
notifySuccess('Record saved successfully!');

// Error notification
notifyError('Failed to save record');

// Warning notification
notifyWarning('Please fill all required fields');

// Info notification
notifyInfo('Record is being processed');
```

### Custom Timeout

```javascript
// Show for 6 seconds instead of default 4
notifySuccess('Operation completed!', 6000);
```

### Direct Notiflix API

```javascript
Notiflix.Notify.success('Success message');
Notiflix.Notify.failure('Error message');
Notiflix.Notify.warning('Warning message');
Notiflix.Notify.info('Info message');
```

---

## Confirmations

### Basic Confirmation

```javascript
confirmAction(
    'Confirm Action',
    'Are you sure you want to proceed?',
    function() {
        // OK callback
        console.log('User confirmed');
    },
    function() {
        // Cancel callback (optional)
        console.log('User cancelled');
    }
);
```

### Delete Confirmation

```javascript
confirmDelete(
    'Are you sure you want to delete this record? This action cannot be undone.',
    function() {
        // Proceed with deletion
        deleteTheRecord();
    }
);
```

### Custom Confirmation Options

```javascript
Notiflix.Confirm.show(
    'Custom Title',
    'Custom message here',
    'Yes, Do It',
    'Cancel',
    function okCb() {
        // User clicked OK
    },
    function cancelCb() {
        // User clicked Cancel
    },
    {
        width: '400px',
        borderRadius: '12px',
        titleColor: '#3B82F6',
        okButtonBackground: '#10B981',
        cancelButtonBackground: '#EF4444',
    }
);
```

---

## Loading States

### Show Loading Indicator

```javascript
// Show loading
showLoading('Processing...');

// Perform async operation
fetch('/api/endpoint')
    .then(response => response.json())
    .then(data => {
        // Hide loading
        hideLoading();
        notifySuccess('Done!');
    })
    .catch(error => {
        hideLoading();
        notifyError('Failed!');
    });
```

### Different Loading Styles

```javascript
// Circle (default)
Notiflix.Loading.circle('Loading...');

// Dots
Notiflix.Loading.dots('Loading...');

// Pulse
Notiflix.Loading.pulse('Loading...');

// Custom
Notiflix.Loading.custom('Loading...', {
    svgColor: '#10B981',
});

// Remove loading
Notiflix.Loading.remove();
```

---

## Reports

Reports are for more detailed messages with titles and longer content.

### Success Report

```javascript
reportSuccess(
    'Record Saved',
    'Your birth certificate record has been successfully saved to the database.',
    'Close'
);
```

### Error Report

```javascript
reportError(
    'Save Failed',
    'An error occurred while saving the record. Please check your input and try again.',
    'Close'
);
```

### Custom Report with Callback

```javascript
Notiflix.Report.success(
    'Upload Complete',
    'All 25 records have been successfully uploaded and processed.',
    'View Records',
    function() {
        window.location.href = '/records';
    }
);
```

---

## Examples

### Example 1: Form Submission with Validation

```javascript
function submitForm() {
    // Validate
    if (!validateForm()) {
        notifyWarning('Please fill all required fields');
        return;
    }

    // Show loading
    showLoading('Saving record...');

    // Submit
    fetch('/api/save_record', {
        method: 'POST',
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();

        if (data.success) {
            reportSuccess(
                'Record Saved',
                'The record has been successfully saved.',
                'Continue',
                function() {
                    window.location.href = '/records';
                }
            );
        } else {
            notifyError(data.message || 'Failed to save record');
        }
    })
    .catch(error => {
        hideLoading();
        reportError(
            'Network Error',
            'Could not connect to the server. Please check your connection.',
            'Retry'
        );
    });
}
```

### Example 2: Delete Record with Confirmation

```javascript
function deleteRecord(id) {
    confirmDelete(
        'Are you sure you want to delete this record? This action cannot be undone.',
        function() {
            // User confirmed
            showLoading('Deleting record...');

            fetch(`/api/delete_record/${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();

                if (data.success) {
                    notifySuccess('Record deleted successfully');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    notifyError(data.message || 'Failed to delete record');
                }
            })
            .catch(error => {
                hideLoading();
                notifyError('An error occurred while deleting the record');
            });
        }
    );
}
```

### Example 3: Batch Upload with Progress

```javascript
async function uploadBatch(files) {
    showLoading(`Uploading 0/${files.length} files...`);

    for (let i = 0; i < files.length; i++) {
        // Update loading message
        Notiflix.Loading.change(`Uploading ${i + 1}/${files.length} files...`);

        await uploadSingleFile(files[i]);
    }

    hideLoading();

    reportSuccess(
        'Upload Complete',
        `Successfully uploaded ${files.length} files!`,
        'View Files'
    );
}
```

### Example 4: Multiple Confirmations

```javascript
function processRecords() {
    Notiflix.Confirm.show(
        'Process Records',
        'This will process all pending records. Continue?',
        'Yes',
        'No',
        function() {
            // First confirmation accepted
            Notiflix.Confirm.show(
                'Final Confirmation',
                'This action cannot be undone. Are you absolutely sure?',
                'Proceed',
                'Cancel',
                function() {
                    // Second confirmation accepted
                    showLoading('Processing records...');
                    // Do the actual processing...
                },
                function() {
                    notifyInfo('Operation cancelled');
                }
            );
        }
    );
}
```

### Example 5: Ajax with Error Handling

```javascript
function saveData(data) {
    showLoading('Saving...');

    fetch('/api/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        hideLoading();

        if (result.success) {
            notifySuccess(result.message || 'Saved successfully');
        } else {
            notifyError(result.message || 'Save failed');
        }
    })
    .catch(error => {
        hideLoading();

        if (error.message.includes('HTTP error')) {
            reportError(
                'Server Error',
                'The server encountered an error. Please try again later.',
                'Close'
            );
        } else if (error.message.includes('NetworkError')) {
            reportError(
                'Connection Error',
                'Could not connect to the server. Please check your internet connection.',
                'Retry',
                function() {
                    saveData(data); // Retry
                }
            );
        } else {
            notifyError('An unexpected error occurred');
        }
    });
}
```

---

## Migration Guide

### Replacing Native Alerts

**Before:**
```javascript
alert('Record saved successfully!');
```

**After:**
```javascript
notifySuccess('Record saved successfully!');
```

### Replacing Native Confirms

**Before:**
```javascript
if (confirm('Delete this record?')) {
    deleteRecord();
}
```

**After:**
```javascript
confirmDelete(
    'Are you sure you want to delete this record?',
    function() {
        deleteRecord();
    }
);
```

### Replacing Custom Alert Divs

**Before:**
```javascript
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
}
```

**After:**
```javascript
function showAlert(type, message) {
    switch(type) {
        case 'success':
            notifySuccess(message);
            break;
        case 'error':
        case 'danger':
            notifyError(message);
            break;
        case 'warning':
            notifyWarning(message);
            break;
        default:
            notifyInfo(message);
    }
}
```

---

## Best Practices

1. **Use appropriate notification types**
   - Success: For successful operations
   - Error: For failures and errors
   - Warning: For validation issues and warnings
   - Info: For general information

2. **Keep messages concise**
   - Notifications: 1-2 short sentences
   - Reports: Can be longer with more detail

3. **Always hide loading states**
   - Use try/catch or finally blocks
   - Ensure hideLoading() is called in all paths

4. **Provide clear action buttons**
   - Use descriptive button text
   - "Delete" instead of "OK" for deletions
   - "Retry" for recoverable errors

5. **Use callbacks effectively**
   - Redirect after successful operations
   - Retry failed operations
   - Close modals after confirmations

---

## Customization

You can customize Notiflix globally in `/assets/js/notiflix-config.js` or per-instance:

```javascript
Notiflix.Notify.success('Message', {
    timeout: 6000,
    fontSize: '16px',
    position: 'center-top',
});
```

See the full Notiflix documentation at: https://notiflix.github.io/
