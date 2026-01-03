/**
 * Certificate Form Skeleton Loader
 * Unified skeleton loading for all certificate entry forms
 */

document.addEventListener('DOMContentLoaded', function() {
    const formColumn = document.querySelector('.form-column');
    if (!formColumn) return;

    // Add class to prevent scrollbars
    document.body.classList.add('skeleton-loading');
    formColumn.classList.add('skeleton-loading-active');

    // Find ALL input fields, selects, and textareas in the form
    const allInputs = formColumn.querySelectorAll('input[type="text"], input[type="date"], input[type="datetime-local"], select, textarea');

    // Hide all inputs and create skeleton for each
    allInputs.forEach(input => {
        // Hide the real input using visibility instead of position absolute
        input.style.visibility = 'hidden';
        input.style.height = '0';
        input.style.margin = '0';
        input.style.padding = '0';
        input.style.border = 'none';

        // Create skeleton placeholder
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton skeleton-input';

        // Insert skeleton before the input
        input.parentNode.insertBefore(skeleton, input);
    });

    // Also add skeleton to labels
    const allLabels = formColumn.querySelectorAll('label');
    allLabels.forEach(label => {
        label.style.opacity = '0';

        const labelSkeleton = document.createElement('div');
        labelSkeleton.className = 'skeleton skeleton-label';
        label.parentNode.insertBefore(labelSkeleton, label);
    });

    // Add skeleton to section titles
    const sectionTitles = formColumn.querySelectorAll('.section-title');
    sectionTitles.forEach(title => {
        title.style.opacity = '0';

        const titleSkeleton = document.createElement('div');
        titleSkeleton.className = 'skeleton skeleton-section-title';
        titleSkeleton.style.marginBottom = '12px';
        title.parentNode.insertBefore(titleSkeleton, title);
    });

    // Add skeleton to help text
    const helpTexts = formColumn.querySelectorAll('.help-text');
    helpTexts.forEach(helpText => {
        helpText.style.opacity = '0';

        const helpSkeleton = document.createElement('div');
        helpSkeleton.className = 'skeleton skeleton-help-text';
        helpText.parentNode.insertBefore(helpSkeleton, helpText);
    });

    // Add skeleton to PDF column
    const pdfColumn = document.querySelector('.pdf-column');
    if (pdfColumn) {
        // Hide PDF preview title
        const pdfTitle = pdfColumn.querySelector('.pdf-preview-title');
        if (pdfTitle) {
            pdfTitle.style.opacity = '0';
            const pdfTitleSkeleton = document.createElement('div');
            pdfTitleSkeleton.className = 'skeleton skeleton-pdf-header';
            pdfTitle.parentNode.insertBefore(pdfTitleSkeleton, pdfTitle);
        }

        // Hide PDF upload/preview area
        const pdfUploadArea = pdfColumn.querySelector('.pdf-upload-area, .pdf-preview-container');
        if (pdfUploadArea) {
            pdfUploadArea.style.opacity = '0';
            const pdfAreaSkeleton = document.createElement('div');
            pdfAreaSkeleton.className = 'skeleton skeleton-pdf-area';
            pdfUploadArea.parentNode.insertBefore(pdfAreaSkeleton, pdfUploadArea);
        }

        // Hide PDF labels in upload section
        const pdfLabels = pdfColumn.querySelectorAll('label');
        pdfLabels.forEach(label => {
            label.style.opacity = '0';
            const labelSkeleton = document.createElement('div');
            labelSkeleton.className = 'skeleton skeleton-label';
            label.parentNode.insertBefore(labelSkeleton, label);
        });

        // Hide PDF help texts
        const pdfHelpTexts = pdfColumn.querySelectorAll('.help-text');
        pdfHelpTexts.forEach(helpText => {
            helpText.style.opacity = '0';
            const helpSkeleton = document.createElement('div');
            helpSkeleton.className = 'skeleton skeleton-help-text';
            helpText.parentNode.insertBefore(helpSkeleton, helpText);
        });

        // Hide toggle PDF button
        const togglePdfBtn = pdfColumn.querySelector('#togglePdfBtn, .toggle-pdf-btn');
        if (togglePdfBtn) {
            togglePdfBtn.style.opacity = '0';
            const btnSkeleton = document.createElement('div');
            btnSkeleton.className = 'skeleton skeleton-toggle-btn';
            togglePdfBtn.parentNode.insertBefore(btnSkeleton, togglePdfBtn);
        }

        // Hide file input and scan button in upload-scanner-container
        const uploadContainer = pdfColumn.querySelector('.upload-scanner-container');
        if (uploadContainer) {
            const fileInput = uploadContainer.querySelector('input[type="file"]');
            const scanBtn = uploadContainer.querySelector('.btn-scan, #scanDocumentBtn');

            if (fileInput) {
                fileInput.style.opacity = '0';
                const fileSkeleton = document.createElement('div');
                fileSkeleton.className = 'skeleton skeleton-button';
                fileSkeleton.style.flex = '1';
                fileInput.parentNode.insertBefore(fileSkeleton, fileInput);
            }

            if (scanBtn) {
                scanBtn.style.opacity = '0';
                const scanSkeleton = document.createElement('div');
                scanSkeleton.className = 'skeleton skeleton-button';
                scanBtn.parentNode.insertBefore(scanSkeleton, scanBtn);
            }
        }
    }

    // Remove all skeletons and show real content after delay
    setTimeout(() => {
        // Remove scrollbar prevention classes
        document.body.classList.remove('skeleton-loading');
        formColumn.classList.remove('skeleton-loading-active');

        // Remove all skeleton elements
        const skeletons = document.querySelectorAll('.skeleton');
        skeletons.forEach(skeleton => skeleton.remove());

        // Show all real inputs with fade effect
        allInputs.forEach(input => {
            input.style.visibility = 'visible';
            input.style.height = '';
            input.style.margin = '';
            input.style.padding = '';
            input.style.border = '';
            input.style.opacity = '0';
            input.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                input.style.opacity = '1';
            }, 10);
        });

        // Show all labels with fade effect
        allLabels.forEach(label => {
            label.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                label.style.opacity = '1';
            }, 10);
        });

        // Show all section titles with fade effect
        sectionTitles.forEach(title => {
            title.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                title.style.opacity = '1';
            }, 10);
        });

        // Show all help texts with fade effect
        helpTexts.forEach(helpText => {
            helpText.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                helpText.style.opacity = '1';
            }, 10);
        });

        // Show PDF column elements with fade effect
        if (pdfColumn) {
            const pdfTitle = pdfColumn.querySelector('.pdf-preview-title');
            const pdfUploadArea = pdfColumn.querySelector('.pdf-upload-area, .pdf-preview-container');
            const pdfLabels = pdfColumn.querySelectorAll('label');
            const pdfHelpTexts = pdfColumn.querySelectorAll('.help-text');
            const togglePdfBtn = pdfColumn.querySelector('#togglePdfBtn, .toggle-pdf-btn');
            const uploadContainer = pdfColumn.querySelector('.upload-scanner-container');

            if (pdfTitle) {
                pdfTitle.style.transition = 'opacity 0.3s ease';
                setTimeout(() => { pdfTitle.style.opacity = '1'; }, 10);
            }
            if (pdfUploadArea) {
                pdfUploadArea.style.transition = 'opacity 0.3s ease';
                setTimeout(() => { pdfUploadArea.style.opacity = '1'; }, 10);
            }
            pdfLabels.forEach(label => {
                label.style.transition = 'opacity 0.3s ease';
                setTimeout(() => { label.style.opacity = '1'; }, 10);
            });
            pdfHelpTexts.forEach(helpText => {
                helpText.style.transition = 'opacity 0.3s ease';
                setTimeout(() => { helpText.style.opacity = '1'; }, 10);
            });
            if (togglePdfBtn) {
                togglePdfBtn.style.transition = 'opacity 0.3s ease';
                setTimeout(() => { togglePdfBtn.style.opacity = '1'; }, 10);
            }
            if (uploadContainer) {
                const fileInput = uploadContainer.querySelector('input[type="file"]');
                const scanBtn = uploadContainer.querySelector('.btn-scan, #scanDocumentBtn');
                if (fileInput) {
                    fileInput.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => { fileInput.style.opacity = '1'; }, 10);
                }
                if (scanBtn) {
                    scanBtn.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => { scanBtn.style.opacity = '1'; }, 10);
                }
            }
        }
    }, 400);
});
