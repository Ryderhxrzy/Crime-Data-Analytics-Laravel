/**
 * Data Decryption Module
 * Handles OTP-based decryption of sensitive crime data
 * Features:
 * - OTP request and verification
 * - 6-digit OTP input handling
 * - Timer management for OTP expiry
 * - Session-based persistent decryption
 * - Auto-decryption if session still valid
 */

class DataDecryptionManager {
    private modal: HTMLElement | null;
    private decryptBtn: HTMLElement | null;
    private closeBtn: HTMLElement | null;
    private sendOtpBtn: HTMLElement | null;
    private verifyOtpBtn: HTMLElement | null;
    private resendOtpBtn: HTMLElement | null;
    private backBtn: HTMLElement | null;

    private step1Container: HTMLElement | null;
    private step2Container: HTMLElement | null;

    private otpInputs: HTMLInputElement[] = [];
    private otpTimerInterval: NodeJS.Timeout | null = null;
    private resendTimerInterval: NodeJS.Timeout | null = null;

    private otpCode: string = '';
    private selectedIncidentId: number | null = null;
    private otpExpiryTime: number = 0;
    private resendCooldownTime: number = 0;

    private jwtToken: string = '';
    private isDecryptionSessionValid: boolean = false;
    private autoDecryptInProgress: boolean = false;
    private decryptedIncidents: Set<number> = new Set();

    constructor() {
        this.initializeElements();
        this.setupEventListeners();
        this.getJwtToken();
        this.checkDecryptionSessionValidity();
    }

    /**
     * Initialize all DOM elements
     */
    private initializeElements(): void {
        this.modal = document.getElementById('decryptionModal');
        this.decryptBtn = document.getElementById('decryptDataBtn');
        this.closeBtn = document.getElementById('closeDecryptionModal');
        this.sendOtpBtn = document.getElementById('sendOtpBtn');
        this.verifyOtpBtn = document.getElementById('verifyOtpBtn');
        this.resendOtpBtn = document.getElementById('resendOtpBtn');
        this.backBtn = document.getElementById('backToStep1');

        this.step1Container = document.getElementById('decryptionStep1');
        this.step2Container = document.getElementById('decryptionStep2');

        // Get all OTP input fields
        this.otpInputs = Array.from(
            document.querySelectorAll('#decryptionModal .otp-input')
        );
    }

    /**
     * Check if decryption session is still valid
     */
    private async checkDecryptionSessionValidity(): Promise<void> {
        try {
            const response = await fetch('/decrypt-data/status', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.jwtToken ? `Bearer ${this.jwtToken}` : '',
                },
                credentials: 'include',
            });

            const data = await response.json();
            this.isDecryptionSessionValid = data.decryption_allowed === true;

            console.log('Decryption session valid:', this.isDecryptionSessionValid);

            // If session is valid, auto-decrypt all encrypted data in table
            // Wait for table to be rendered first
            if (this.isDecryptionSessionValid) {
                this.waitForTableAndDecrypt();
            }
        } catch (error) {
            console.warn('Could not check decryption session validity:', error);
            this.isDecryptionSessionValid = false;
        }
    }

    /**
     * Wait for table to be rendered before attempting decryption
     */
    private waitForTableAndDecrypt(): void {
        let retries = 0;
        const maxRetries = 20; // Try for up to 2 seconds

        const checkAndDecrypt = () => {
            const tbody = document.getElementById('crimesTableBody');
            const hasRows = tbody && tbody.querySelectorAll('[data-incident-id]').length > 0;

            if (hasRows) {
                console.log('[Decryption] Table loaded, starting auto-decryption...');
                this.autoDecryptTableData();
            } else if (retries < maxRetries) {
                retries++;
                setTimeout(checkAndDecrypt, 100); // Retry every 100ms
            } else {
                console.log('[Decryption] Table not found or empty after retries');
            }
        };

        checkAndDecrypt();
    }

    /**
     * Public method to trigger auto-decryption when table is re-rendered
     * Called after pagination or table updates
     */
    public async retryAutoDecryption(): Promise<void> {
        // Check session validity first
        await this.checkDecryptionSessionValidity();
    }

    /**
     * Auto-decrypt table data if session is valid
     * Prevents duplicate requests and rate limiting
     * Tries cached data first, then decrypts if needed
     */
    private async autoDecryptTableData(): Promise<void> {
        // Prevent multiple concurrent auto-decrypt calls
        if (this.autoDecryptInProgress) {
            console.log('[Decryption] Auto-decryption already in progress, skipping...');
            return;
        }

        this.autoDecryptInProgress = true;

        try {
            // Get all incident rows - look for data-incident-id attribute
            const incidentRows = document.querySelectorAll('[data-incident-id]');

            // Process requests with throttling to avoid rate limit (60 req/min = 1 req/sec)
            for (const row of incidentRows) {
                const incidentId = row.getAttribute('data-incident-id');
                if (incidentId && !this.decryptedIncidents.has(parseInt(incidentId))) {
                    // Add to set to prevent duplicate requests
                    this.decryptedIncidents.add(parseInt(incidentId));

                    // Show loading indicators in columns
                    this.showLoadingInColumns(parseInt(incidentId));

                    try {
                        // First, try to get cached data from Redis
                        const cachedData = await this.getCachedDecryptedData(incidentId);
                        if (cachedData) {
                            console.log(`[Decryption] Using cached data for incident ${incidentId}`);
                            this.updateTableWithDecryptedData(cachedData, parseInt(incidentId));
                            // Add small delay to maintain throttle behavior
                            await new Promise(resolve => setTimeout(resolve, 100));
                            continue;
                        }

                        // If no cached data, decrypt on-demand
                        const response = await fetch('/decrypt-data/verify-otp', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': this.getCsrfToken(),
                                'Authorization': this.jwtToken ? `Bearer ${this.jwtToken}` : '',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                otp: 'session_valid', // Pseudo OTP - backend will check session
                                incident_id: incidentId,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.updateTableWithDecryptedData(data.data, parseInt(incidentId));
                            console.log(`[Decryption] Successfully decrypted incident ${incidentId}`);
                        } else if (response.status === 410) {
                            // Session expired
                            this.isDecryptionSessionValid = false;
                            console.log('[Decryption] Session expired, will need new OTP');
                            break;
                        }
                    } catch (error) {
                        console.warn(`[Decryption] Failed to decrypt incident ${incidentId}:`, error);
                        // Remove loading indicator on error
                        this.removeLoadingFromColumns(parseInt(incidentId));
                    }

                    // Add throttle delay - cached items are fast, new decryption respects rate limit
                    await new Promise(resolve => setTimeout(resolve, 200));
                }
            }
        } catch (error) {
            console.warn('[Decryption] Auto-decryption failed:', error);
        } finally {
            this.autoDecryptInProgress = false;
        }
    }

    /**
     * Show loading spinner in columns while decrypting
     */
    private showLoadingInColumns(incidentId: number): void {
        const personsCells = document.querySelectorAll(`[data-expand-target="${incidentId}-persons"]`);
        const evidenceCells = document.querySelectorAll(`[data-expand-target="${incidentId}-evidence"]`);

        const loadingHTML = `<div class="flex items-center gap-2"><i class="fas fa-spinner fa-spin text-blue-500"></i><span class="text-xs text-gray-600">Decrypting...</span></div>`;

        personsCells.forEach(cell => {
            cell.innerHTML = loadingHTML;
        });

        evidenceCells.forEach(cell => {
            cell.innerHTML = loadingHTML;
        });
    }

    /**
     * Remove loading indicator from columns
     */
    private removeLoadingFromColumns(incidentId: number): void {
        const personsCells = document.querySelectorAll(`[data-expand-target="${incidentId}-persons"]`);
        const evidenceCells = document.querySelectorAll(`[data-expand-target="${incidentId}-evidence"]`);

        personsCells.forEach(cell => {
            if (cell.innerHTML.includes('fa-spinner')) {
                cell.innerHTML = '<span class="text-gray-500 text-xs">None</span>';
            }
        });

        evidenceCells.forEach(cell => {
            if (cell.innerHTML.includes('fa-spinner')) {
                cell.innerHTML = '<span class="text-gray-500 text-xs">None</span>';
            }
        });
    }

    /**
     * Try to get cached decrypted data from Redis
     */
    private async getCachedDecryptedData(incidentId: string): Promise<any | null> {
        try {
            const response = await fetch(`/decrypt-data/get-cached/${incidentId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.jwtToken ? `Bearer ${this.jwtToken}` : '',
                },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data) {
                    return data.data;
                }
            }
        } catch (error) {
            console.log(`[Decryption] No cached data for incident ${incidentId}`);
        }
        return null;
    }

    /**
     * Handle Show More button click - expand items inline
     */
    private handleShowMore(incidentId: number, dataType: 'persons' | 'evidence'): void {
        console.log(`[Decryption] handleShowMore called: incidentId=${incidentId}, dataType=${dataType}`);

        const cellSelector = `[data-expand-target="${incidentId}-${dataType}"]`;
        const cells = document.querySelectorAll(cellSelector);
        console.log(`[Decryption] Found ${cells.length} cells for selector: ${cellSelector}`);

        cells.forEach((cell, index) => {
            console.log(`[Decryption] Processing cell ${index}:`, cell);

            const incidentRow = cell.closest('tr');
            console.log(`[Decryption] Found row:`, incidentRow);

            if (!incidentRow) {
                console.warn(`[Decryption] No row found for cell`);
                return;
            }

            const button = incidentRow.querySelector(`.show-more-button[data-target="${dataType}"]`) as HTMLButtonElement;
            console.log(`[Decryption] Found button:`, button);

            if (!button) {
                console.warn(`[Decryption] No button found in row for dataType=${dataType}`);
                return;
            }

            const isExpanded = button.getAttribute('data-expanded') === 'true';
            const cachedDataAttr = incidentRow.getAttribute(`data-${dataType}-cached`);
            const totalItems = parseInt(button.getAttribute('data-total') || '0');

            console.log(`[Decryption] State: isExpanded=${isExpanded}, totalItems=${totalItems}`);
            console.log(`[Decryption] Cached data attribute exists:`, !!cachedDataAttr);
            if (cachedDataAttr) {
                console.log(`[Decryption] Cached data (first 100 chars):`, cachedDataAttr.substring(0, 100));
            }

            // Try to parse cached data if it exists
            let items = null;
            if (cachedDataAttr) {
                try {
                    items = JSON.parse(cachedDataAttr);
                    console.log(`[Decryption] Successfully parsed ${items?.length || 0} items`);
                } catch (e) {
                    console.warn(`[Decryption] Failed to parse cached ${dataType} data:`, e, 'Raw:', cachedDataAttr);
                    return;
                }
            }

            if (!isExpanded && items) {
                // Show all items
                console.log(`[Decryption] Showing all items (${items.length})`);
                button.setAttribute('data-expanded', 'true');
                button.textContent = 'Show less';
                this.renderAllItems(cell, items, dataType, incidentId);
            } else if (isExpanded && items) {
                // Show only first item
                console.log(`[Decryption] Showing first item only`);
                button.setAttribute('data-expanded', 'false');
                button.textContent = `Show more (${totalItems - 1} more)`;
                if (items.length > 0) {
                    this.renderFirstItem(cell, items[0], dataType, incidentId);
                }
            } else {
                // No cached data found
                console.warn(`[Decryption] No cached data found. items=${items}, isExpanded=${isExpanded}`);
            }
        });
    }

    /**
     * Render only the first item
     */
    private renderFirstItem(cell: Element, item: any, dataType: 'persons' | 'evidence', incidentId: number): void {
        if (dataType === 'persons') {
            cell.innerHTML = `
                <span class="inline-block bg-green-200 text-green-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">✓ ${(item.role || 'Person').toUpperCase()}</span>
                <div class="ml-1 text-gray-900 font-medium text-xs">
                    <div><span class="text-gray-700">Name:</span> <strong>${item.first_name || ''}${item.middle_name ? ' ' + item.middle_name : ''} ${item.last_name || ''}</strong></div>
                    <div><span class="text-gray-700">Contact:</span> <strong>${item.contact_number || 'N/A'}</strong></div>
                    <div><span class="text-gray-700">Other:</span> <strong>${item.other_info || 'N/A'}</strong></div>
                </div>
            `;
        } else {
            cell.innerHTML = `
                <span class="inline-block bg-green-200 text-green-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">✓ ${item.type || 'Evidence'}</span>
                <div class="ml-1 text-gray-900 font-medium text-xs">
                    <div><span class="text-gray-700">Desc:</span> <strong>${item.description || 'N/A'}</strong></div>
                    <div><span class="text-gray-700">Link:</span>
                        ${item.evidence_link ? `<a href="${item.evidence_link}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 font-semibold"><i class="fas fa-external-link-alt mr-1"></i>View</a>` : '<span class="text-gray-500">N/A</span>'}
                    </div>
                </div>
            `;
        }
    }

    /**
     * Render all items
     */
    private renderAllItems(cell: Element, items: any[], dataType: 'persons' | 'evidence', incidentId: number): void {
        console.log(`[Decryption] renderAllItems called: dataType=${dataType}, itemCount=${items.length}, cell=`, cell);

        if (dataType === 'persons') {
            const html = items.map((person: any) => `
                <div class="mb-3 pb-3 border-b border-gray-100 last:border-b-0">
                    <span class="inline-block bg-green-200 text-green-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">✓ ${(person.role || 'Person').toUpperCase()}</span>
                    <div class="ml-1 text-gray-900 font-medium text-xs">
                        <div><span class="text-gray-700">Name:</span> <strong>${person.first_name || ''}${person.middle_name ? ' ' + person.middle_name : ''} ${person.last_name || ''}</strong></div>
                        <div><span class="text-gray-700">Contact:</span> <strong>${person.contact_number || 'N/A'}</strong></div>
                        <div><span class="text-gray-700">Other:</span> <strong>${person.other_info || 'N/A'}</strong></div>
                    </div>
                </div>
            `).join('');
            cell.innerHTML = html;
            console.log(`[Decryption] Rendered ${items.length} persons items`);
        } else {
            const html = items.map((evidence: any) => `
                <div class="mb-3 pb-3 border-b border-gray-100 last:border-b-0">
                    <span class="inline-block bg-green-200 text-green-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">✓ ${evidence.type || 'Evidence'}</span>
                    <div class="ml-1 text-gray-900 font-medium text-xs">
                        <div><span class="text-gray-700">Desc:</span> <strong>${evidence.description || 'N/A'}</strong></div>
                        <div><span class="text-gray-700">Link:</span>
                            ${evidence.evidence_link ? `<a href="${evidence.evidence_link}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 font-semibold"><i class="fas fa-external-link-alt mr-1"></i>View</a>` : '<span class="text-gray-500">N/A</span>'}
                        </div>
                    </div>
                </div>
            `).join('');
            cell.innerHTML = html;
            console.log(`[Decryption] Rendered ${items.length} evidence items`);
        }
    }

    /**
     * Setup all event listeners
     */
    private setupEventListeners(): void {
        // Open modal
        this.decryptBtn?.addEventListener('click', () => this.openModal());

        // Close modal
        this.closeBtn?.addEventListener('click', () => this.closeModal());

        // Send OTP
        this.sendOtpBtn?.addEventListener('click', () => this.sendOtp());

        // Verify OTP
        this.verifyOtpBtn?.addEventListener('click', () => this.verifyOtp());

        // Resend OTP
        this.resendOtpBtn?.addEventListener('click', () => this.resendOtp());

        // Back to step 1
        this.backBtn?.addEventListener('click', () => this.goToStep1());

        // OTP input handling
        this.otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => this.handleOtpInput(e, index));
            input.addEventListener('keydown', (e) => this.handleOtpKeydown(e, index));
            input.addEventListener('paste', (e) => this.handleOtpPaste(e));
        });

        // Close modal on background click
        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });

        // Delegate Show More button clicks - expand items inline
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            const button = target.closest('.show-more-button') as HTMLButtonElement;
            if (button) {
                const incidentId = button.getAttribute('data-incident-id');
                const dataType = button.getAttribute('data-target');
                if (incidentId && dataType) {
                    this.handleShowMore(parseInt(incidentId), dataType);
                }
            }
        });

        // Listen for table re-render events (pagination, filtering, etc.)
        document.addEventListener('crimePageTableRendered', () => {
            console.log('[Decryption] Table re-rendered, triggering auto-decryption...');

            // Get current incidents in the table
            const incidentRows = document.querySelectorAll('[data-incident-id]');
            const currentIncidentIds = new Set<number>();

            incidentRows.forEach(row => {
                const incidentId = parseInt(row.getAttribute('data-incident-id') || '0');
                if (incidentId) {
                    currentIncidentIds.add(incidentId);
                }
            });

            // If table shows different incidents than what we've decrypted,
            // it means new data has loaded (pagination, filtering, or data updates)
            // Clear tracking so we can decrypt all data including newly added persons/evidence
            let shouldReset = false;

            // Check if any previously decrypted incidents are no longer visible
            // or if there are new incidents visible
            if (this.decryptedIncidents.size !== currentIncidentIds.size) {
                shouldReset = true;
            } else {
                // Check if the incident IDs have changed
                for (const id of this.decryptedIncidents) {
                    if (!currentIncidentIds.has(id)) {
                        shouldReset = true;
                        break;
                    }
                }
            }

            // Reset tracking if table data has changed
            if (shouldReset) {
                console.log('[Decryption] Table data changed, resetting decryption tracking...');
                this.decryptedIncidents.clear();
            }

            // Trigger auto-decryption for all visible incidents immediately
            // Use small delay to ensure table DOM is fully rendered
            setTimeout(() => {
                this.autoDecryptTableData();
            }, 100);
        });
    }


    /**
     * Get JWT token from session storage
     */
    private getJwtToken(): void {
        this.jwtToken = sessionStorage.getItem('jwt_token') || '';
    }

    /**
     * Open decryption modal
     */
    private openModal(): void {
        if (this.modal) {
            this.modal.classList.remove('hidden');
            this.goToStep1();
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Close decryption modal
     */
    private closeModal(): void {
        if (this.modal) {
            this.modal.classList.add('hidden');
            document.body.style.overflow = '';
            this.resetModal();
        }
    }

    /**
     * Reset modal to initial state
     */
    private resetModal(): void {
        this.clearOtpInputs();
        this.clearErrorMessages();
        this.clearSuccessMessages();
        this.stopOtpTimer();
        this.stopResendTimer();
        this.selectedIncidentId = null;
        this.otpCode = '';
        // Re-enable buttons
        if (this.sendOtpBtn) {
            (this.sendOtpBtn as HTMLButtonElement).disabled = false;
        }
        if (this.verifyOtpBtn) {
            (this.verifyOtpBtn as HTMLButtonElement).disabled = true;
        }
    }

    /**
     * Go to step 1 (Send OTP)
     */
    private goToStep1(): void {
        this.step1Container?.classList.remove('hidden');
        this.step2Container?.classList.add('hidden');
        this.clearErrorMessages();
        this.clearOtpInputs();
    }

    /**
     * Go to step 2 (Verify OTP)
     */
    private goToStep2(): void {
        this.step1Container?.classList.add('hidden');
        this.step2Container?.classList.remove('hidden');
        this.clearErrorMessages();
        this.otpInputs[0]?.focus();
        this.startOtpTimer();
    }

    /**
     * Send OTP to user's email
     */
    private async sendOtp(): Promise<void> {
        try {
            // Disable send button with visual blocking
            if (this.sendOtpBtn) {
                const btn = this.sendOtpBtn as HTMLButtonElement;
                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            this.setLoadingState('otpLoadingState', true);
            this.clearErrorMessages();

            // Get incident ID from the first row
            const firstIncidentRow = document.querySelector('[data-incident-id]');
            const incidentId = firstIncidentRow?.getAttribute('data-incident-id') || '0';

            const response = await fetch('/decrypt-data/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Authorization': this.jwtToken ? `Bearer ${this.jwtToken}` : '',
                },
                credentials: 'include',
                body: JSON.stringify({
                    incident_id: incidentId,
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                this.showErrorMessage('otpErrorMessage', 'otpErrorText',
                    data.message || 'Failed to send OTP. Please try again.');

                if (response.status === 429) {
                    const retryAfter = data.retry_after || 60;
                    this.showErrorMessage('otpErrorMessage', 'otpErrorText',
                        `Too many requests. Please wait ${retryAfter} seconds.`);
                }
                // Re-enable button on error
                if (this.sendOtpBtn) {
                    const btn = this.sendOtpBtn as HTMLButtonElement;
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
                return;
            }

            // OTP sent successfully, go to step 2
            this.otpExpiryTime = data.expires_in || 300; // Default 5 minutes
            this.goToStep2();

            // Show success notification
            this.showNotification('OTP sent to your email', 'success');

        } catch (error) {
            console.error('Error sending OTP:', error);
            this.showErrorMessage('otpErrorMessage', 'otpErrorText',
                'An error occurred. Please try again later.');
            // Re-enable button on error
            if (this.sendOtpBtn) {
                const btn = this.sendOtpBtn as HTMLButtonElement;
                btn.disabled = false;
                btn.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        } finally {
            this.setLoadingState('otpLoadingState', false);
        }
    }

    /**
     * Verify OTP and decrypt data
     */
    private async verifyOtp(): Promise<void> {
        try {
            const otp = this.getOtpCode();

            if (otp.length !== 6) {
                this.showErrorMessage('verifyErrorMessage', 'verifyErrorText',
                    'Please enter all 6 digits of the OTP.');
                return;
            }

            // Disable verify button with visual blocking
            if (this.verifyOtpBtn) {
                const btn = this.verifyOtpBtn as HTMLButtonElement;
                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            // Disable close button
            if (this.closeBtn) {
                this.closeBtn.style.pointerEvents = 'none';
                this.closeBtn.style.opacity = '0.5';
            }

            this.setLoadingState('verifyLoadingState', true);
            this.clearErrorMessages();

            // Get the first incident ID from the table
            const firstIncidentRow = document.querySelector('[data-incident-id]');
            const incidentId = firstIncidentRow?.getAttribute('data-incident-id') || '1';

            const response = await fetch('/decrypt-data/verify-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Authorization': this.jwtToken ? `Bearer ${this.jwtToken}` : '',
                },
                credentials: 'include',
                body: JSON.stringify({
                    otp: otp,
                    incident_id: incidentId,
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                this.showErrorMessage('verifyErrorMessage', 'verifyErrorText',
                    data.message || 'Failed to verify OTP. Please try again.');

                // Clear OTP inputs on error
                this.clearOtpInputs();
                this.otpInputs[0]?.focus();

                // Re-enable verify button on error
                if (this.verifyOtpBtn) {
                    const btn = this.verifyOtpBtn as HTMLButtonElement;
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
                // Re-enable close button
                if (this.closeBtn) {
                    this.closeBtn.style.pointerEvents = 'auto';
                    this.closeBtn.style.opacity = '1';
                }
                return;
            }

            // OTP verified successfully
            this.showSuccessMessage('verifySuccessMessage', 'verifySuccessText',
                'OTP verified! Decrypting data...');

            // Mark session as decrypted
            this.isDecryptionSessionValid = true;
            sessionStorage.setItem('decryption_session_valid', 'true');

            // Update table with decrypted data
            setTimeout(() => {
                // Track that this incident has been decrypted
                this.decryptedIncidents.add(parseInt(incidentId));
                this.updateTableWithDecryptedData(data.data, parseInt(incidentId));

                // Show desktop notification using NotificationManager
                (window as any).NotificationManager?.showGenericNotification(
                    'Data Decryption Successful',
                    'Your sensitive data has been decrypted and is now visible in the table.',
                    'success'
                );

                // Close modal immediately
                this.closeModal();
            }, 1000);

        } catch (error) {
            console.error('Error verifying OTP:', error);
            this.showErrorMessage('verifyErrorMessage', 'verifyErrorText',
                'An error occurred. Please try again.');

            // Re-enable verify button on error
            if (this.verifyOtpBtn) {
                const btn = this.verifyOtpBtn as HTMLButtonElement;
                btn.disabled = false;
                btn.classList.remove('opacity-60', 'cursor-not-allowed');
            }
            // Re-enable close button
            if (this.closeBtn) {
                this.closeBtn.style.pointerEvents = 'auto';
                this.closeBtn.style.opacity = '1';
            }
        } finally {
            this.setLoadingState('verifyLoadingState', false);
        }
    }

    /**
     * Resend OTP
     */
    private async resendOtp(): Promise<void> {
        try {
            if (this.resendOtpBtn) {
                (this.resendOtpBtn as HTMLButtonElement).disabled = true;
            }
            this.clearErrorMessages();

            // Get incident ID
            const firstIncidentRow = document.querySelector('[data-incident-id]');
            const incidentId = firstIncidentRow?.getAttribute('data-incident-id') || '0';

            const response = await fetch('/decrypt-data/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken(),
                    'Authorization': this.jwtToken ? `Bearer ${this.jwtToken}` : '',
                },
                credentials: 'include',
                body: JSON.stringify({
                    incident_id: incidentId,
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                this.showErrorMessage('verifyErrorMessage', 'verifyErrorText',
                    data.message || 'Failed to resend OTP.');
                if (this.resendOtpBtn) {
                    (this.resendOtpBtn as HTMLButtonElement).disabled = false;
                }
                return;
            }

            // Reset OTP inputs and timer
            this.clearOtpInputs();
            this.otpExpiryTime = data.expires_in || 300;
            this.startOtpTimer();
            this.startResendCooldown();

            // Show notification
            this.showNotification('OTP resent to your email', 'success');

        } catch (error) {
            console.error('Error resending OTP:', error);
            this.showErrorMessage('verifyErrorMessage', 'verifyErrorText',
                'Failed to resend OTP. Please try again.');
            if (this.resendOtpBtn) {
                (this.resendOtpBtn as HTMLButtonElement).disabled = false;
            }
        }
    }

    /**
     * Handle OTP input field
     */
    private handleOtpInput(e: Event, index: number): void {
        const input = e.target as HTMLInputElement;
        const value = input.value;

        // Only allow digits
        if (!/^\d?$/.test(value)) {
            input.value = '';
            return;
        }

        // Move to next field if digit entered
        if (value && index < this.otpInputs.length - 1) {
            this.otpInputs[index + 1].focus();
        }

        // Enable/disable verify button based on all fields filled
        this.updateVerifyButtonState();
    }

    /**
     * Handle OTP input keydown events
     */
    private handleOtpKeydown(e: KeyboardEvent, index: number): void {
        const input = e.target as HTMLInputElement;

        // Backspace - move to previous field
        if (e.key === 'Backspace' && !input.value && index > 0) {
            this.otpInputs[index - 1].focus();
        }

        // Arrow keys
        if (e.key === 'ArrowLeft' && index > 0) {
            e.preventDefault();
            this.otpInputs[index - 1].focus();
        }
        if (e.key === 'ArrowRight' && index < this.otpInputs.length - 1) {
            e.preventDefault();
            this.otpInputs[index + 1].focus();
        }

        // Enter - verify OTP
        if (e.key === 'Enter') {
            e.preventDefault();
            this.verifyOtp();
        }
    }

    /**
     * Handle paste event for OTP
     */
    private handleOtpPaste(e: ClipboardEvent): void {
        e.preventDefault();
        const pastedData = e.clipboardData?.getData('text') || '';
        const digits = pastedData.replace(/\D/g, '').split('').slice(0, 6);

        this.otpInputs.forEach((input, index) => {
            input.value = digits[index] || '';
        });

        this.updateVerifyButtonState();
    }

    /**
     * Get OTP code from input fields
     */
    private getOtpCode(): string {
        return this.otpInputs.map(input => input.value).join('');
    }

    /**
     * Clear OTP input fields
     */
    private clearOtpInputs(): void {
        this.otpInputs.forEach(input => {
            input.value = '';
        });
        this.updateVerifyButtonState();
    }

    /**
     * Update verify button state
     */
    private updateVerifyButtonState(): void {
        const isComplete = this.otpInputs.every(input => input.value !== '');
        if (this.verifyOtpBtn) {
            const btn = this.verifyOtpBtn as HTMLButtonElement;
            btn.disabled = !isComplete;
            if (!isComplete) {
                btn.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                btn.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        }
    }

    /**
     * Start OTP expiry timer
     */
    private startOtpTimer(): void {
        this.stopOtpTimer();
        let remainingSeconds = this.otpExpiryTime;
        const timerDisplay = document.getElementById('otpTimer');

        const updateTimer = () => {
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            const timeStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timerDisplay) {
                timerDisplay.textContent = `OTP expires in: ${timeStr}`;
            }

            if (remainingSeconds <= 0) {
                this.stopOtpTimer();
                if (timerDisplay) {
                    timerDisplay.textContent = 'OTP Expired - Request a new one';
                }
                this.clearOtpInputs();
            }

            remainingSeconds--;
        };

        updateTimer(); // Call immediately
        this.otpTimerInterval = setInterval(updateTimer, 1000);
    }

    /**
     * Stop OTP timer
     */
    private stopOtpTimer(): void {
        if (this.otpTimerInterval) {
            clearInterval(this.otpTimerInterval);
            this.otpTimerInterval = null;
        }
    }

    /**
     * Start resend cooldown timer
     */
    private startResendCooldown(): void {
        this.stopResendTimer();
        let remainingSeconds = 30;
        const resendBtn = this.resendOtpBtn;
        const timerDisplay = document.getElementById('resendTimer');

        const updateTimer = () => {
            if (timerDisplay) {
                timerDisplay.textContent = remainingSeconds > 0 ? `(${remainingSeconds}s)` : '';
            }

            if (resendBtn) {
                (resendBtn as HTMLButtonElement).disabled = remainingSeconds > 0;
            }

            remainingSeconds--;
        };

        updateTimer();
        this.resendTimerInterval = setInterval(updateTimer, 1000);
    }

    /**
     * Stop resend timer
     */
    private stopResendTimer(): void {
        if (this.resendTimerInterval) {
            clearInterval(this.resendTimerInterval);
            this.resendTimerInterval = null;
        }
    }

    /**
     * Show error message
     */
    private showErrorMessage(containerId: string, textId: string, message: string): void {
        const container = document.getElementById(containerId);
        const textElement = document.getElementById(textId);

        if (container && textElement) {
            textElement.textContent = message;
            container.classList.remove('hidden');
        }
    }

    /**
     * Clear error messages
     */
    private clearErrorMessages(): void {
        document.getElementById('otpErrorMessage')?.classList.add('hidden');
        document.getElementById('verifyErrorMessage')?.classList.add('hidden');
    }

    /**
     * Show success message
     */
    private showSuccessMessage(containerId: string, textId: string, message: string): void {
        const container = document.getElementById(containerId);
        const textElement = document.getElementById(textId);

        if (container && textElement) {
            textElement.textContent = message;
            container.classList.remove('hidden');
        }
    }

    /**
     * Clear success messages
     */
    private clearSuccessMessages(): void {
        document.getElementById('verifySuccessMessage')?.classList.add('hidden');
    }

    /**
     * Set loading state
     */
    private setLoadingState(elementId: string, isLoading: boolean): void {
        const element = document.getElementById(elementId);
        if (element) {
            if (isLoading) {
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        }
    }

    /**
     * Get CSRF token from meta tag
     */
    private getCsrfToken(): string {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return token || '';
    }

    /**
     * Update table cells with decrypted data
     * Displays only first item with Show More button
     */
    private updateTableWithDecryptedData(data: any, incidentId: number): void {
        // Update persons involved in table - show only FIRST item
        if (data.persons_involved && data.persons_involved.length > 0) {
            const personsCells = document.querySelectorAll(`[data-expand-target="${incidentId}-persons"]`);
            personsCells.forEach((cell) => {
                const firstPerson = data.persons_involved[0];
                const totalPersons = data.persons_involved.length;

                // Store all persons data for Show More functionality
                const row = cell.closest('tr');
                if (row) {
                    row.setAttribute('data-persons-cached', JSON.stringify(data.persons_involved));
                }

                // Show only first person
                this.renderFirstItem(cell, firstPerson, 'persons', incidentId);
            });

            // Update or add the Show More button for persons (if multiple items)
            const personRows = document.querySelectorAll(`tr:has([data-expand-target="${incidentId}-persons"])`);
            personRows.forEach((row) => {
                if (data.persons_involved.length > 1) {
                    let existingButton = row.querySelector(`.show-more-button[data-target="persons"]`) as HTMLButtonElement;

                    if (!existingButton) {
                        const personCell = row.querySelector(`[data-expand-target="${incidentId}-persons"]`);
                        if (personCell && personCell.parentElement) {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'show-more-button text-xs text-blue-600 hover:text-blue-800 font-semibold cursor-pointer';
                            button.setAttribute('data-incident-id', incidentId.toString());
                            button.setAttribute('data-target', 'persons');
                            button.setAttribute('data-expanded', 'false');
                            button.setAttribute('data-total', data.persons_involved.length.toString());
                            button.textContent = `Show more (${data.persons_involved.length - 1} more)`;
                            personCell.parentElement.appendChild(button);
                            console.log(`[Decryption] Created Show More button for persons in incident ${incidentId}`);
                        }
                    } else {
                        // Update existing button
                        existingButton.setAttribute('data-total', data.persons_involved.length.toString());
                        existingButton.setAttribute('data-expanded', 'false'); // Initialize to not expanded
                        existingButton.textContent = `Show more (${data.persons_involved.length - 1} more)`;
                        console.log(`[Decryption] Updated existing Show More button for persons in incident ${incidentId}`);
                    }
                }
            });
        }

        // Update evidence items in table - show only FIRST item
        if (data.evidence_items && data.evidence_items.length > 0) {
            const evidenceCells = document.querySelectorAll(`[data-expand-target="${incidentId}-evidence"]`);
            evidenceCells.forEach((cell) => {
                const firstEvidence = data.evidence_items[0];
                const totalEvidence = data.evidence_items.length;

                // Store all evidence data for Show More functionality
                const row = cell.closest('tr');
                if (row) {
                    row.setAttribute('data-evidence-cached', JSON.stringify(data.evidence_items));
                }

                // Show only first evidence
                this.renderFirstItem(cell, firstEvidence, 'evidence', incidentId);
            });

            // Update or add the Show More button for evidence (if multiple items)
            const evidenceRows = document.querySelectorAll(`tr:has([data-expand-target="${incidentId}-evidence"])`);
            evidenceRows.forEach((row) => {
                if (data.evidence_items.length > 1) {
                    let existingButton = row.querySelector(`.show-more-button[data-target="evidence"]`) as HTMLButtonElement;

                    if (!existingButton) {
                        const evidenceCell = row.querySelector(`[data-expand-target="${incidentId}-evidence"]`);
                        if (evidenceCell && evidenceCell.parentElement) {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'show-more-button text-xs text-blue-600 hover:text-blue-800 font-semibold cursor-pointer';
                            button.setAttribute('data-incident-id', incidentId.toString());
                            button.setAttribute('data-target', 'evidence');
                            button.setAttribute('data-expanded', 'false');
                            button.setAttribute('data-total', data.evidence_items.length.toString());
                            button.textContent = `Show more (${data.evidence_items.length - 1} more)`;
                            evidenceCell.parentElement.appendChild(button);
                            console.log(`[Decryption] Created Show More button for evidence in incident ${incidentId}`);
                        }
                    } else {
                        // Update existing button
                        existingButton.setAttribute('data-total', data.evidence_items.length.toString());
                        existingButton.setAttribute('data-expanded', 'false'); // Initialize to not expanded
                        existingButton.textContent = `Show more (${data.evidence_items.length - 1} more)`;
                        console.log(`[Decryption] Updated existing Show More button for evidence in incident ${incidentId}`);
                    }
                }
            });
        }

        console.log('Table updated with decrypted data for incident', incidentId);
    }

    /**
     * Show notification
     */
    private showNotification(message: string, type: 'success' | 'error' | 'warning' = 'info'): void {
        const notification = document.createElement('div');
        const icons: Record<string, string> = {
            success: 'fas fa-check-circle text-green-600',
            error: 'fas fa-exclamation-circle text-red-600',
            warning: 'fas fa-exclamation-triangle text-yellow-600',
        };

        notification.className = `fixed top-4 right-4 bg-white rounded-lg shadow-lg p-4 z-[9999] flex items-center gap-3 animate-slide-in`;
        notification.innerHTML = `
            <i class="${icons[type]}"></i>
            <span class="text-gray-700">${message}</span>
            <button class="ml-2 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(notification);

        // Close button
        notification.querySelector('button')?.addEventListener('click', () => {
            notification.remove();
        });

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new DataDecryptionManager();
});

