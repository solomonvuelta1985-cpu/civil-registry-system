/**
 * Record Preview Modal
 * Displays record details with PDF preview side-by-side
 */

class RecordPreviewModal {
    constructor() {
        this.modal = null;
        this.backdrop = null;
        this.currentRecordId = null;
        this.currentRecordType = null;
        this.currentRecord = null;
        this.pdfDoc = null;
        this.currentPage = 1;
        this.totalPages = 0;
        this.scale = 1.5;
        this.canvas = null;
        this.ctx = null;

        this.init();
    }

    init() {
        this.createModalStructure();
        this.attachEventListeners();
    }

    createModalStructure() {
        // Create backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'record-modal-backdrop';
        document.body.appendChild(this.backdrop);

        // Create modal
        this.modal = document.createElement('div');
        this.modal.className = 'record-modal';
        this.modal.innerHTML = `
            <div class="record-modal-dialog">
                <!-- Header -->
                <div class="record-modal-header">
                    <div>
                        <h2 class="record-modal-title">
                            <i data-lucide="file-text"></i>
                            <span id="modalRecordTitle">Record Preview</span>
                        </h2>
                        <p class="record-modal-subtitle" id="modalRecordSubtitle"></p>
                    </div>
                    <button type="button" class="record-modal-close" id="closeModalBtn" title="Close (ESC)">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="record-modal-body">
                    <!-- Left Panel: Record Details -->
                    <div class="record-details-panel" id="recordDetailsPanel">
                        <div class="pdf-loading">
                            <i class="fas fa-spinner"></i>
                            <p>Loading record details...</p>
                        </div>
                    </div>

                    <!-- Right Panel: PDF Preview -->
                    <div class="record-pdf-panel">
                        <div class="pdf-preview-header">
                            <div class="pdf-preview-title">
                                <i data-lucide="file-text"></i>
                                <span>Official Document Preview</span>
                            </div>
                            <div class="pdf-controls">
                                <button type="button" class="pdf-control-btn" id="pdfZoomOut" title="Zoom Out">
                                    <i data-lucide="zoom-out"></i>
                                </button>
                                <span class="pdf-zoom-display" id="pdfZoomDisplay">150%</span>
                                <button type="button" class="pdf-control-btn" id="pdfZoomIn" title="Zoom In">
                                    <i data-lucide="zoom-in"></i>
                                </button>

                                <div class="pdf-divider"></div>

                                <button type="button" class="pdf-control-btn" id="pdfPrevPage" title="Previous Page">
                                    <i data-lucide="chevron-left"></i>
                                </button>
                                <span class="pdf-page-info">
                                    <span id="pdfCurrentPage">1</span> of <span id="pdfTotalPages">1</span>
                                </span>
                                <button type="button" class="pdf-control-btn" id="pdfNextPage" title="Next Page">
                                    <i data-lucide="chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="pdf-preview-container" id="pdfPreviewContainer">
                            <div class="pdf-canvas-wrapper">
                                <canvas id="recordPdfCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="record-modal-footer">
                    <div class="record-modal-footer-left">
                        <button type="button" class="modal-btn modal-btn-outline" id="modalCloseBtn">
                            <i data-lucide="x"></i>
                            Close
                        </button>
                    </div>
                    <div class="record-modal-footer-right" id="modalActionButtons">
                        <!-- Dynamic action buttons will be inserted here -->
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);

        // Get canvas context
        this.canvas = document.getElementById('recordPdfCanvas');
        this.ctx = this.canvas.getContext('2d');

        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    attachEventListeners() {
        // Close modal events
        this.backdrop.addEventListener('click', () => this.close());
        document.getElementById('closeModalBtn').addEventListener('click', () => this.close());
        document.getElementById('modalCloseBtn').addEventListener('click', () => this.close());

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (!this.modal.classList.contains('show')) return;

            switch(e.key) {
                case 'Escape':
                    this.close();
                    break;
                case 'e':
                case 'E':
                    if (!e.ctrlKey && !e.metaKey) {
                        this.editRecord();
                    }
                    break;
                case 'p':
                case 'P':
                    if (!e.ctrlKey && !e.metaKey) {
                        e.preventDefault();
                        this.printRecord();
                    }
                    break;
                case 'd':
                case 'D':
                    if (!e.ctrlKey && !e.metaKey) {
                        e.preventDefault();
                        this.downloadRecord();
                    }
                    break;
            }
        });

        // PDF controls
        document.getElementById('pdfZoomIn').addEventListener('click', () => this.zoomIn());
        document.getElementById('pdfZoomOut').addEventListener('click', () => this.zoomOut());
        document.getElementById('pdfPrevPage').addEventListener('click', () => this.previousPage());
        document.getElementById('pdfNextPage').addEventListener('click', () => this.nextPage());
    }

    async open(recordId, recordType) {
        this.currentRecordId = recordId;
        this.currentRecordType = recordType;

        // Show modal
        this.backdrop.classList.add('show');
        this.modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Load record data
        await this.loadRecordData();
    }

    close() {
        this.backdrop.classList.remove('show');
        this.modal.classList.remove('show');
        document.body.style.overflow = '';

        // Reset PDF
        this.pdfDoc = null;
        this.currentPage = 1;
        this.totalPages = 0;
        this.scale = 1.5;
    }

    async loadRecordData() {
        const detailsPanel = document.getElementById('recordDetailsPanel');
        detailsPanel.innerHTML = `
            <div class="pdf-loading">
                <i class="fas fa-spinner"></i>
                <p>Loading record details...</p>
            </div>
        `;

        try {
            const response = await fetch(`../api/record_details.php?id=${this.currentRecordId}&type=${this.currentRecordType}`);
            const data = await response.json();

            if (data.success) {
                this.currentRecord = data.record;
                this.renderRecordDetails(data.record);
                this.renderActionButtons(data.record);

                // Load PDF if available
                if (data.record.pdf_filename) {
                    await this.loadPDF(`../uploads/${data.record.pdf_filename}`);
                } else {
                    this.showPDFError('No PDF available for this record');
                }
            } else {
                this.showError(data.message || 'Failed to load record details');
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure(data.message || 'Failed to load record details');
                }
            }
        } catch (error) {
            console.error('Error loading record:', error);
            this.showError('An error occurred while loading the record');
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Notify.failure('An error occurred while loading the record');
            }
        }
    }

    renderRecordDetails(record) {
        const detailsPanel = document.getElementById('recordDetailsPanel');
        let html = '';

        // Update modal title with status indicator
        const titleMap = {
            'birth': 'Birth Certificate',
            'marriage': 'Marriage Certificate',
            'death': 'Death Certificate',
            'marriage_license': 'Marriage License Application'
        };

        const statusClass = record.status === 'Active' ? 'active' : 'pending';
        const statusIcon = record.status === 'Active' ? 'check-circle' : 'clock';

        document.getElementById('modalRecordTitle').innerHTML = `
            <i data-lucide="file-text"></i>
            <span>${titleMap[this.currentRecordType] || 'Record Preview'}</span>
        `;

        document.getElementById('modalRecordSubtitle').innerHTML = `
            Registry No. ${record.registry_no || 'N/A'}
            <span class="header-status-badge ${statusClass}">
                <i data-lucide="${statusIcon}"></i> ${record.status || 'Active'}
            </span>
        `;

        // Re-initialize Lucide icons in header
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Render based on record type
        if (this.currentRecordType === 'birth') {
            html = this.renderBirthDetails(record);
        } else if (this.currentRecordType === 'marriage') {
            html = this.renderMarriageDetails(record);
        } else if (this.currentRecordType === 'death') {
            html = this.renderDeathDetails(record);
        } else if (this.currentRecordType === 'marriage_license') {
            html = this.renderMarriageLicenseDetails(record);
        }

        detailsPanel.innerHTML = html;

        // Reinitialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    renderBirthDetails(record) {
        const childFullName = this.getFullName(record.child_first_name, record.child_middle_name, record.child_last_name);

        return `
            <!-- Child Information - Prominent -->
            <div class="record-details-section primary-section">
                <div class="record-section-title">
                    <i data-lucide="baby"></i>
                    Child Information
                </div>
                <div class="record-detail-row highlight-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value highlight">${childFullName}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Birth</span>
                    <span class="record-detail-value">${this.formatDate(record.child_date_of_birth)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Birth</span>
                    <span class="record-detail-value">${this.formatValue(record.child_place_of_birth)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Sex</span>
                    <span class="record-detail-value">${this.formatValue(record.child_sex)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Type of Birth</span>
                    <span class="record-detail-value">${this.formatValue(record.type_of_birth)}</span>
                </div>
            </div>

            <!-- Father Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user"></i>
                    Father Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.father_first_name, record.father_middle_name, record.father_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Citizenship</span>
                    <span class="record-detail-value">${this.formatValue(record.father_citizenship)}</span>
                </div>
            </div>

            <!-- Mother Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user"></i>
                    Mother Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.mother_first_name, record.mother_middle_name, record.mother_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Citizenship</span>
                    <span class="record-detail-value">${this.formatValue(record.mother_citizenship)}</span>
                </div>
            </div>

            <!-- Registration Details -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="calendar"></i>
                    Registration Details
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Registry No.</span>
                    <span class="record-detail-value highlight">${this.formatValue(record.registry_no)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Registration</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_registration)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Status</span>
                    <span class="record-status-badge active">
                        <i class="fas fa-circle"></i>
                        ${this.escapeHtml(record.status || 'Active')}
                    </span>
                </div>
            </div>
        `;
    }

    renderMarriageDetails(record) {
        return `
            <!-- Primary Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="info"></i>
                    Primary Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Registry No.</span>
                    <span class="record-detail-value">${this.escapeHtml(record.registry_no || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Status</span>
                    <span class="record-status-badge active">
                        <i class="fas fa-circle"></i>
                        ${this.escapeHtml(record.status || 'Active')}
                    </span>
                </div>
            </div>

            <!-- Husband Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user"></i>
                    Husband Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.husband_first_name, record.husband_middle_name, record.husband_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Birth</span>
                    <span class="record-detail-value">${this.escapeHtml(record.husband_place_of_birth || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Citizenship</span>
                    <span class="record-detail-value">${this.escapeHtml(record.husband_citizenship || 'N/A')}</span>
                </div>
            </div>

            <!-- Wife Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user"></i>
                    Wife Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.wife_first_name, record.wife_middle_name, record.wife_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Birth</span>
                    <span class="record-detail-value">${this.escapeHtml(record.wife_place_of_birth || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Citizenship</span>
                    <span class="record-detail-value">${this.escapeHtml(record.wife_citizenship || 'N/A')}</span>
                </div>
            </div>

            <!-- Marriage Details -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="heart"></i>
                    Marriage Details
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Marriage</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_marriage)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Marriage</span>
                    <span class="record-detail-value">${this.escapeHtml(record.place_of_marriage || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Registration</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_registration)}</span>
                </div>
            </div>
        `;
    }

    renderDeathDetails(record) {
        return `
            <!-- Primary Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="info"></i>
                    Primary Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Registry No.</span>
                    <span class="record-detail-value">${this.escapeHtml(record.registry_no || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Status</span>
                    <span class="record-status-badge active">
                        <i class="fas fa-circle"></i>
                        ${this.escapeHtml(record.status || 'Active')}
                    </span>
                </div>
            </div>

            <!-- Deceased Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user-x"></i>
                    Deceased Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.deceased_first_name, record.deceased_middle_name, record.deceased_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Sex</span>
                    <span class="record-detail-value">${this.escapeHtml(record.sex || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Birth</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_birth)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Death</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_death)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Age</span>
                    <span class="record-detail-value">${this.escapeHtml(record.age || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Death</span>
                    <span class="record-detail-value">${this.escapeHtml(record.place_of_death || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Cause of Death</span>
                    <span class="record-detail-value">${this.escapeHtml(record.cause_of_death || 'N/A')}</span>
                </div>
            </div>

            <!-- Registration Details -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="calendar"></i>
                    Registration Details
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Registration</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_registration)}</span>
                </div>
            </div>
        `;
    }

    renderMarriageLicenseDetails(record) {
        return `
            <!-- Primary Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="info"></i>
                    Primary Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Registry No.</span>
                    <span class="record-detail-value">${this.escapeHtml(record.registry_no || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Status</span>
                    <span class="record-status-badge active">
                        <i class="fas fa-circle"></i>
                        ${this.escapeHtml(record.status || 'Active')}
                    </span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Date of Application</span>
                    <span class="record-detail-value">${this.formatDate(record.date_of_application)}</span>
                </div>
            </div>

            <!-- Groom Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user"></i>
                    Groom Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.groom_first_name, record.groom_middle_name, record.groom_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Birth</span>
                    <span class="record-detail-value">${this.escapeHtml(record.groom_place_of_birth || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Citizenship</span>
                    <span class="record-detail-value">${this.escapeHtml(record.groom_citizenship || 'N/A')}</span>
                </div>
            </div>

            <!-- Bride Information -->
            <div class="record-details-section">
                <div class="record-section-title">
                    <i data-lucide="user"></i>
                    Bride Information
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Full Name</span>
                    <span class="record-detail-value">${this.getFullName(record.bride_first_name, record.bride_middle_name, record.bride_last_name)}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Place of Birth</span>
                    <span class="record-detail-value">${this.escapeHtml(record.bride_place_of_birth || 'N/A')}</span>
                </div>
                <div class="record-detail-row">
                    <span class="record-detail-label">Citizenship</span>
                    <span class="record-detail-value">${this.escapeHtml(record.bride_citizenship || 'N/A')}</span>
                </div>
            </div>
        `;
    }

    renderActionButtons(record) {
        const actionButtons = document.getElementById('modalActionButtons');
        let html = '';

        // Edit button (if user has permission)
        html += `
            <button type="button" class="modal-btn modal-btn-primary" onclick="recordPreviewModal.editRecord()" title="Keyboard shortcut: E">
                <i data-lucide="edit"></i>
                <span>Edit</span>
                <span class="btn-shortcut">E</span>
            </button>
        `;

        // Print button
        if (record.pdf_filename) {
            html += `
                <button type="button" class="modal-btn modal-btn-success" onclick="recordPreviewModal.printRecord()" title="Keyboard shortcut: P">
                    <i data-lucide="printer"></i>
                    <span>Print</span>
                    <span class="btn-shortcut">P</span>
                </button>
            `;

            // Download button
            html += `
                <button type="button" class="modal-btn modal-btn-primary" onclick="recordPreviewModal.downloadRecord()" title="Keyboard shortcut: D">
                    <i data-lucide="download"></i>
                    <span>Download</span>
                    <span class="btn-shortcut">D</span>
                </button>
            `;
        }

        // Delete button (if user has permission)
        html += `
            <button type="button" class="modal-btn modal-btn-danger" onclick="recordPreviewModal.deleteRecord()">
                <i data-lucide="trash-2"></i>
                <span>Delete</span>
            </button>
        `;

        actionButtons.innerHTML = html;

        // Reinitialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    async loadPDF(url) {
        const container = document.getElementById('pdfPreviewContainer');
        container.innerHTML = `
            <div class="pdf-loading">
                <i class="fas fa-spinner"></i>
                <p>Loading PDF...</p>
            </div>
        `;

        try {
            // Load PDF using PDF.js
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js library not loaded');
            }

            const loadingTask = pdfjsLib.getDocument(url);
            this.pdfDoc = await loadingTask.promise;
            this.totalPages = this.pdfDoc.numPages;

            // Update page display
            document.getElementById('pdfTotalPages').textContent = this.totalPages;

            // Render first page
            await this.renderPage(1);

            // Show canvas wrapper
            container.innerHTML = '<div class="pdf-canvas-wrapper"><canvas id="recordPdfCanvas"></canvas></div>';
            this.canvas = document.getElementById('recordPdfCanvas');
            this.ctx = this.canvas.getContext('2d');

            await this.renderPage(1);

        } catch (error) {
            console.error('Error loading PDF:', error);
            this.showPDFError('Failed to load PDF document');
        }
    }

    async renderPage(pageNum) {
        if (!this.pdfDoc) return;

        try {
            const page = await this.pdfDoc.getPage(pageNum);

            // Get the container dimensions
            const container = document.getElementById('pdfPreviewContainer');
            const containerWidth = container.clientWidth - 48; // Account for padding

            // Get viewport at scale 1
            const viewport = page.getViewport({ scale: 1.0 });

            // Calculate scale to fit width while maintaining aspect ratio
            const scaleToFit = containerWidth / viewport.width;
            const actualScale = this.scale * scaleToFit;

            // Get final viewport with calculated scale
            const scaledViewport = page.getViewport({ scale: actualScale });

            this.canvas.height = scaledViewport.height;
            this.canvas.width = scaledViewport.width;

            const renderContext = {
                canvasContext: this.ctx,
                viewport: scaledViewport
            };

            await page.render(renderContext).promise;

            this.currentPage = pageNum;
            document.getElementById('pdfCurrentPage').textContent = pageNum;

            // Update button states
            document.getElementById('pdfPrevPage').disabled = (pageNum <= 1);
            document.getElementById('pdfNextPage').disabled = (pageNum >= this.totalPages);

        } catch (error) {
            console.error('Error rendering page:', error);
        }
    }

    async previousPage() {
        if (this.currentPage <= 1) return;
        await this.renderPage(this.currentPage - 1);
    }

    async nextPage() {
        if (this.currentPage >= this.totalPages) return;
        await this.renderPage(this.currentPage + 1);
    }

    async zoomIn() {
        this.scale = Math.min(this.scale + 0.25, 3.0);
        document.getElementById('pdfZoomDisplay').textContent = Math.round(this.scale * 100) + '%';
        await this.renderPage(this.currentPage);
    }

    async zoomOut() {
        this.scale = Math.max(this.scale - 0.25, 0.5);
        document.getElementById('pdfZoomDisplay').textContent = Math.round(this.scale * 100) + '%';
        await this.renderPage(this.currentPage);
    }

    showError(message) {
        const detailsPanel = document.getElementById('recordDetailsPanel');
        detailsPanel.innerHTML = `
            <div class="pdf-error">
                <i class="fas fa-exclamation-circle"></i>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    }

    showPDFError(message) {
        const container = document.getElementById('pdfPreviewContainer');
        container.innerHTML = `
            <div class="pdf-error">
                <i class="fas fa-file-excel"></i>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    }

    // Action Methods
    editRecord() {
        const entryFormMap = {
            'birth': 'certificate_of_live_birth.php',
            'marriage': 'certificate_of_marriage.php',
            'death': 'certificate_of_death.php',
            'marriage_license': 'application_for_marriage_license.php'
        };

        const recordTypeNames = {
            'birth': 'Birth Record',
            'marriage': 'Marriage Record',
            'death': 'Death Record',
            'marriage_license': 'Marriage License'
        };

        const formPage = entryFormMap[this.currentRecordType];
        const recordName = recordTypeNames[this.currentRecordType] || 'Record';
        const recordId = this.currentRecordId;
        const record = this.currentRecord;

        if (!formPage) return;

        // Build dialog title
        const dialogTitle = `Edit ${recordName}`;

        // Build message with structured details - using HTML for proper line breaks
        let message = `Are you sure you want to edit this record?<br><br>`;
        if (record) {
            const details = this.getRecordSummary(record);
            if (details) {
                message += details;
            } else {
                message = `Are you sure you want to edit this ${recordName.toLowerCase()}?`;
            }
        } else {
            message = `Are you sure you want to edit this ${recordName.toLowerCase()}?`;
        }

        // Close the modal first to prevent z-index issues with Notiflix
        this.close();

        // Small delay to ensure modal close animation completes
        setTimeout(() => {
            if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
                Notiflix.Confirm.show(
                    dialogTitle,
                    message,
                    'Cancel',
                    'Proceed to Edit',
                    () => {
                        // User cancelled - do nothing
                        console.log('Edit cancelled by user');
                    },
                    () => {
                        // User confirmed - navigate to edit page
                        window.location.href = `${formPage}?id=${recordId}`;
                    },
                    {
                        width: '500px',
                        borderRadius: '12px',
                        backgroundColor: '#FFFFFF',
                        titleColor: '#111827',
                        titleFontSize: '20px',
                        titleMaxLength: 50,
                        messageColor: '#1F2937',
                        messageFontSize: '15px',
                        messageMaxLength: 600,
                        plainText: false,
                        okButtonColor: '#374151',
                        okButtonBackground: '#F3F4F6',
                        cancelButtonColor: '#FFFFFF',
                        cancelButtonBackground: '#3B82F6',
                        buttonsFontSize: '15px',
                        buttonsMaxLength: 50,
                        buttonsBorderRadius: '60px',
                        cssAnimationStyle: 'zoom',
                        cssAnimationDuration: 250,
                        distance: '24px',
                        backOverlayColor: 'rgba(0,0,0,0.6)',
                    }
                );
            } else {
                // Fallback to native confirm
                console.warn('Notiflix not loaded, using native confirm dialog');
                if (confirm(message)) {
                    window.location.href = `${formPage}?id=${recordId}`;
                }
            }
        }, 100);
    }

    printRecord() {
        if (this.currentRecord && this.currentRecord.pdf_filename) {
            const pdfUrl = `../uploads/${this.currentRecord.pdf_filename}`;
            window.open(pdfUrl, '_blank');
        }
    }

    downloadRecord() {
        if (this.currentRecord && this.currentRecord.pdf_filename) {
            const link = document.createElement('a');
            link.href = `../uploads/${this.currentRecord.pdf_filename}`;
            link.download = this.currentRecord.pdf_filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    deleteRecord() {
        const recordId = this.currentRecordId;
        const record = this.currentRecord;

        // Build dialog title based on record type
        const recordTypeNames = {
            'birth': 'Birth Record',
            'marriage': 'Marriage Record',
            'death': 'Death Record',
            'marriage_license': 'Marriage License'
        };
        const recordName = recordTypeNames[this.currentRecordType] || 'Record';
        const dialogTitle = `Delete ${recordName}`;

        // Build message with structured details - using HTML for proper line breaks
        let message = `Are you sure you want to delete this record?<br><br>`;
        if (record) {
            const details = this.getRecordSummary(record);
            if (details) {
                message += details;
                message += `<br><br><span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;
            } else {
                message = `Are you sure you want to delete this record?<br><br><span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;
            }
        } else {
            message = `Are you sure you want to delete this record?<br><br><span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;
        }

        // Close the modal first to prevent z-index issues with Notiflix
        this.close();

        // Small delay to ensure modal close animation completes
        setTimeout(() => {
            if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
                Notiflix.Confirm.show(
                    dialogTitle,
                    message,
                    'Cancel',
                    'Delete Permanently',
                    () => {
                        // User cancelled - do nothing
                        console.log('Delete cancelled by user');
                    },
                    () => {
                        // Call the existing deleteRecord function from records_viewer.php
                        if (typeof deleteRecord === 'function') {
                            deleteRecord(recordId, record);
                        }
                    },
                    {
                        width: '500px',
                        borderRadius: '12px',
                        backgroundColor: '#FFFFFF',
                        titleColor: '#111827',
                        titleFontSize: '20px',
                        titleMaxLength: 50,
                        messageColor: '#1F2937',
                        messageFontSize: '15px',
                        messageMaxLength: 600,
                        plainText: false,
                        okButtonColor: '#374151',
                        okButtonBackground: '#F3F4F6',
                        cancelButtonColor: '#FFFFFF',
                        cancelButtonBackground: '#EF4444',
                        buttonsFontSize: '15px',
                        buttonsMaxLength: 50,
                        buttonsBorderRadius: '60px',
                        cssAnimationStyle: 'zoom',
                        cssAnimationDuration: 250,
                        distance: '24px',
                        backOverlayColor: 'rgba(0,0,0,0.6)',
                    }
                );
            } else {
                // Fallback to native confirm if Notiflix not loaded
                console.warn('Notiflix not loaded, using native confirm dialog');
                if (confirm(message)) {
                    // Call the existing deleteRecord function from records_viewer.php
                    if (typeof deleteRecord === 'function') {
                        deleteRecord(recordId, record);
                    }
                }
            }
        }, 100);
    }

    // Utility Methods
    capitalizeNames(nameParts) {
        const filtered = nameParts.filter(n => n && n.trim());
        if (filtered.length === 0) return '';

        return filtered.map(name => {
            return name.split(' ').map(word => {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            }).join(' ');
        }).join(' ');
    }

    formatDateFull(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }

    getFullName(first, middle, last) {
        const parts = [first, middle, last].filter(p => p && p.trim());
        if (parts.length === 0) {
            return '<span class="empty-value">N/A</span>';
        }
        return this.escapeHtml(parts.join(' '));
    }

    formatValue(value) {
        if (!value || value.trim() === '') {
            return '<span class="empty-value">N/A</span>';
        }
        return this.escapeHtml(value);
    }

    formatDate(dateString) {
        if (!dateString) return '<span class="empty-value">N/A</span>';
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }

    formatDateTime(dateTimeString) {
        if (!dateTimeString) return '<span class="empty-value">N/A</span>';
        const date = new Date(dateTimeString);
        return date.toLocaleString();
    }

    escapeHtml(text) {
        if (!text) return 'N/A';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getRecordSummary(record) {
        let details = '';

        if (record.registry_no) {
            details += `<strong>Registry No:</strong> ${record.registry_no}<br>`;
        }

        switch(this.currentRecordType) {
            case 'birth':
                const childName = this.capitalizeNames([record.child_first_name, record.child_middle_name, record.child_last_name]);
                if (childName) details += `<strong>Child:</strong> ${childName}<br>`;
                if (record.child_date_of_birth) details += `<strong>Date of Birth:</strong> ${this.formatDateFull(record.child_date_of_birth)}`;
                break;

            case 'marriage':
                const husbandName = this.capitalizeNames([record.husband_first_name, record.husband_middle_name, record.husband_last_name]);
                const wifeName = this.capitalizeNames([record.wife_first_name, record.wife_middle_name, record.wife_last_name]);
                if (husbandName) details += `<strong>Husband:</strong> ${husbandName}<br>`;
                if (wifeName) details += `<strong>Wife:</strong> ${wifeName}<br>`;
                if (record.date_of_marriage) details += `<strong>Marriage Date:</strong> ${this.formatDateFull(record.date_of_marriage)}`;
                break;

            case 'death':
                const deceasedName = this.capitalizeNames([record.deceased_first_name, record.deceased_middle_name, record.deceased_last_name]);
                if (deceasedName) details += `<strong>Deceased:</strong> ${deceasedName}<br>`;
                if (record.date_of_death) details += `<strong>Date of Death:</strong> ${this.formatDateFull(record.date_of_death)}<br>`;
                if (record.age) details += `<strong>Age:</strong> ${record.age}`;
                break;

            case 'marriage_license':
                const groomName = this.capitalizeNames([record.groom_first_name, record.groom_middle_name, record.groom_last_name]);
                const brideName = this.capitalizeNames([record.bride_first_name, record.bride_middle_name, record.bride_last_name]);
                if (groomName) details += `<strong>Groom:</strong> ${groomName}<br>`;
                if (brideName) details += `<strong>Bride:</strong> ${brideName}<br>`;
                if (record.date_of_application) details += `<strong>Application Date:</strong> ${this.formatDateFull(record.date_of_application)}`;
                break;
        }

        return details.trim();
    }

    formatDatePlain(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }
}

// Initialize modal on page load
let recordPreviewModal;
document.addEventListener('DOMContentLoaded', function() {
    recordPreviewModal = new RecordPreviewModal();
});
