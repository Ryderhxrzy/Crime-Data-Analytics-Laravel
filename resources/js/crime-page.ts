interface Person {
    id: number;
    person_type: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    contact_number: string;
    other_info: string;
}

interface EvidenceItem {
    id: number;
    evidence_type: string;
    description: string;
    evidence_link: string;
}

interface CrimeIncident {
    id: number;
    incident_code: string;
    incident_title: string;
    incident_description?: string;
    incident_date: string;
    incident_time?: string;
    status: string;
    clearance_status: string;
    latitude?: number;
    longitude?: number;
    address_details?: string;
    victim_count?: number;
    suspect_count?: number;
    modus_operandi?: string;
    weather_condition?: string;
    assigned_officer?: string;
    persons_involved_count?: number;
    persons_involved_types?: string[];
    persons_involved?: Person[];
    evidence_count?: number;
    evidence_types?: string[];
    evidence?: EvidenceItem[];
    category: {
        id: number;
        category_name: string;
        color_code?: string;
        icon?: string;
    };
    barangay: {
        id: number;
        barangay_name: string;
    };
}

interface CrimeStats {
    total: number;
    live: number;
    underInvestigation: number;
    cleared: number;
}

interface FilterState {
    search: string;
    category: string;
    status: string;
    barangay: string;
    date: string;
    clearance: string;
}

interface CrimeIncidentResponse {
    incidents: CrimeIncident[];
    categories: Array<{id: number, category_name: string}>;
    barangays: Array<{id: number, barangay_name: string}>;
}

declare global {
    interface Window {
        L: any;
        NotificationManager: typeof NotificationManager;
    }
}

class CrimePageManager {
    private map: any = null;
    private markers: any[] = [];
    private incidents: CrimeIncident[] = [];
    private filteredIncidents: CrimeIncident[] = [];
    private currentPage: number = 1;
    private pageSize: number = 10;
    private filters: FilterState = {
        search: '',
        category: '',
        status: '',
        barangay: '',
        date: '',
        clearance: ''
    };

    constructor() {
        try {
            this.initializeElements();
            this.initializeEventListeners();
            this.loadInitialData();
            this.initializeRealtimeListeners();
        } catch (error) {
            console.error('Error initializing CrimePageManager:', error);
        }
    }

    private initializeElements(): void {
        // Filter elements
        const searchInput = document.getElementById('searchInput') as HTMLInputElement;
        const tableSearchInput = document.getElementById('tableSearchInput') as HTMLInputElement;
        const categoryFilter = document.getElementById('categoryFilter') as HTMLSelectElement;
        const statusFilter = document.getElementById('statusFilter') as HTMLSelectElement;
        const barangayFilter = document.getElementById('barangayFilter') as HTMLSelectElement;
        const dateFilter = document.getElementById('dateFilter') as HTMLInputElement;
        const pageSizeSelect = document.getElementById('pageSize') as HTMLSelectElement;
        const tablePageSizeSelect = document.getElementById('tablePageSize') as HTMLSelectElement;
        const caseStatusFilter = document.getElementById('caseStatusFilter') as HTMLSelectElement;
        const clearanceStatusFilter = document.getElementById('clearanceStatusFilter') as HTMLSelectElement;

        // Button elements
        const addIncidentBtn = document.getElementById('addIncidentBtn');
        const exportBtn = document.getElementById('exportBtn');
        const closeModalBtn = document.getElementById('closeModal');
        const modalOverlay = document.getElementById('incidentModal');

        // Add Incident Modal elements
        const addIncidentModal = document.getElementById('addIncidentModal') as HTMLElement;
        const closeAddModalBtn = document.getElementById('closeAddModal') as HTMLElement;
        const cancelAddIncidentBtn = document.getElementById('cancelAddIncident') as HTMLElement;
        const addIncidentForm = document.getElementById('addIncidentForm') as HTMLFormElement;

        // Store references
        (window as any).crimePage = {
            searchInput,
            tableSearchInput,
            categoryFilter,
            statusFilter,
            barangayFilter,
            dateFilter,
            pageSizeSelect,
            tablePageSizeSelect,
            caseStatusFilter,
            clearanceStatusFilter,
            addIncidentBtn,
            exportBtn,
            closeModalBtn,
            modalOverlay,
            addIncidentModal,
            closeAddModalBtn,
            cancelAddIncidentBtn,
            addIncidentForm
        };
    }

    private initializeMap(): void {
        // Initialize Leaflet map centered on Quezon City
        this.map = window.L.map('crimeMap').setView([14.6760, 121.0437], 11);

        // Add tile layer
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);

        // Add geocoder control
        const geocoder = (window.L.Control as any).geocoder({
            defaultMarkGeocode: false,
            placeholder: 'Search location...',
            errorMessage: 'Nothing found.',
            showResultIcons: true,
            suggestMinLength: 2,
            suggestTimeout: 250,
            queryMinLength: 1
        }).addTo(this.map);
    }

    private initializeEventListeners(): void {
        const elements = (window as any).crimePage;

        // Filter listeners
        elements.searchInput?.addEventListener('input', (e: Event) => {
            this.filters.search = (e.target as HTMLInputElement).value;
            this.applyFilters();
        });

        elements.tableSearchInput?.addEventListener('input', (e: Event) => {
            this.filters.search = (e.target as HTMLInputElement).value;
            this.applyFilters();
        });

        elements.categoryFilter?.addEventListener('change', (e: Event) => {
            this.filters.category = (e.target as HTMLSelectElement).value;
            this.applyFilters();
        });

        elements.statusFilter?.addEventListener('change', (e: Event) => {
            this.filters.status = (e.target as HTMLSelectElement).value;
            this.applyFilters();
        });

        elements.barangayFilter?.addEventListener('change', (e: Event) => {
            this.filters.barangay = (e.target as HTMLSelectElement).value;
            this.applyFilters();
        });

        elements.dateFilter?.addEventListener('change', (e: Event) => {
            this.filters.date = (e.target as HTMLInputElement).value;
            this.applyFilters();
        });

        elements.caseStatusFilter?.addEventListener('change', (e: Event) => {
            this.filters.status = (e.target as HTMLSelectElement).value;
            this.applyFilters();
        });

        elements.clearanceStatusFilter?.addEventListener('change', (e: Event) => {
            this.filters.clearance = (e.target as HTMLSelectElement).value;
            this.applyFilters();
        });

        elements.tablePageSizeSelect?.addEventListener('change', (e: Event) => {
            this.pageSize = parseInt((e.target as HTMLSelectElement).value);
            this.currentPage = 1;
            this.renderTable();
        });

        // Button listeners
        elements.addIncidentBtn?.addEventListener('click', () => {
            this.showAddIncidentModal();
        });

        elements.exportBtn?.addEventListener('click', () => {
            this.exportData();
        });

        elements.closeModalBtn?.addEventListener('click', () => {
            this.closeModal();
        });

        elements.modalOverlay?.addEventListener('click', (e: Event) => {
            if (e.target === elements.modalOverlay) {
                this.closeModal();
            }
        });

        // Add Incident Modal listeners
        elements.closeAddModalBtn?.addEventListener('click', () => {
            this.closeAddIncidentModal();
        });

        elements.cancelAddIncidentBtn?.addEventListener('click', () => {
            this.closeAddIncidentModal();
        });

        elements.addIncidentForm?.addEventListener('submit', (e: Event) => {
            e.preventDefault();
            this.submitIncidentForm();
        });

        elements.addIncidentModal?.addEventListener('click', (e: Event) => {
            if (e.target === elements.addIncidentModal) {
                this.closeAddIncidentModal();
            }
        });

        // View Location Modal listeners
        const closeViewLocationBtn = document.getElementById('closeViewLocationBtn');
        const closeViewLocationModalBtn = document.getElementById('closeViewLocationModal');
        const viewLocationModal = document.getElementById('viewLocationModal');

        closeViewLocationBtn?.addEventListener('click', () => {
            this.closeViewLocationModal();
        });

        closeViewLocationModalBtn?.addEventListener('click', () => {
            this.closeViewLocationModal();
        });

        viewLocationModal?.addEventListener('click', (e: Event) => {
            if (e.target === viewLocationModal) {
                this.closeViewLocationModal();
            }
        });

        // Sidebar toggle for mobile
        this.setupSidebarToggle();

        // Table checkbox listeners (added after table renders)
        this.setupTableCheckboxListeners();
    }

    private setupTableCheckboxListeners(): void {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox') as HTMLInputElement;
        const tbody = document.getElementById('crimesTableBody');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e: Event) => {
                const isChecked = (e.target as HTMLInputElement).checked;
                const rowCheckboxes = tbody?.querySelectorAll('input[type="checkbox"][data-incident-id]') as NodeListOf<HTMLInputElement>;

                if (rowCheckboxes) {
                    rowCheckboxes.forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                }

                console.log(isChecked ? `Selected all ${rowCheckboxes?.length || 0} incidents` : 'Deselected all incidents');
            });
        }

        // Setup "see more" button listeners
        const seeMoreButtons = document.querySelectorAll('.see-more-button');
        seeMoreButtons.forEach((button: Element) => {
            button.addEventListener('click', (e: Event) => {
                e.stopPropagation();
                const targetColumn = (button as HTMLElement).getAttribute('data-target');
                const incidentId = (button as HTMLElement).getAttribute('data-incident-id');
                this.expandColumn(incidentId, targetColumn);
            });
        });

        // Setup "read more" button listeners
        const readMoreButtons = document.querySelectorAll('.read-more-btn');
        readMoreButtons.forEach((button: Element) => {
            button.addEventListener('click', (e: Event) => {
                e.stopPropagation();
                const targetId = (button as HTMLElement).getAttribute('data-target');
                const textElement = document.getElementById(targetId) as HTMLElement;

                if (!textElement) return;

                const isExpanded = textElement.getAttribute('data-expanded') === 'true';
                const fullText = textElement.getAttribute('data-full') || '';
                const truncatedText = textElement.getAttribute('data-truncated') || '';

                if (isExpanded) {
                    // Collapse
                    textElement.textContent = truncatedText + '...';
                    textElement.setAttribute('data-expanded', 'false');
                    (button as HTMLElement).textContent = 'Read more';
                } else {
                    // Expand
                    textElement.textContent = fullText;
                    textElement.setAttribute('data-expanded', 'true');
                    (button as HTMLElement).textContent = 'Read less';
                }
            });
        });
    }

    private expandColumn(incidentId: string | null, targetColumn: string | null): void {
        if (!incidentId || !targetColumn) return;

        const incident = this.incidents.find(i => i.id === parseInt(incidentId));
        if (!incident) return;

        const columnElement = document.querySelector(`[data-expand-target="${incidentId}-${targetColumn}"]`);
        const seeMoreButton = document.querySelector(`[data-incident-id="${incidentId}"][data-target="${targetColumn}"]`);

        if (!columnElement || !seeMoreButton) return;

        // Check if already expanded
        const isExpanded = columnElement.getAttribute('data-expanded') === 'true';

        if (isExpanded) {
            // Collapse back to first item only
            let collapsedHTML = '';
            if (targetColumn === 'persons' && incident.persons_involved && incident.persons_involved.length > 0) {
                const firstPerson = incident.persons_involved[0];
                collapsedHTML = `
                    <span class="inline-block bg-purple-200 text-purple-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${firstPerson.person_type.toUpperCase()}</span>
                    <div class="ml-1 text-xs">
                        <div><span class="font-medium text-gray-700">Name:</span> <span class="blur-text-badge">${firstPerson.first_name}</span></div>
                        <div><span class="font-medium text-gray-700">Contact:</span> <span class="blur-text-badge">${firstPerson.contact_number}</span></div>
                        <div><span class="font-medium text-gray-700">Other:</span> <span class="blur-text-badge">${firstPerson.other_info}</span></div>
                    </div>
                `;
            } else if (targetColumn === 'evidence' && incident.evidence && incident.evidence.length > 0) {
                const firstEvidence = incident.evidence[0];
                collapsedHTML = `
                    <span class="inline-block bg-orange-200 text-orange-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${firstEvidence.evidence_type}</span>
                    <div class="ml-1 text-xs">
                        <div><span class="font-medium text-gray-700">Desc:</span> <span class="blur-text-badge">${firstEvidence.description}</span></div>
                        <div><span class="font-medium text-gray-700">Link:</span> <span class="blur-text-badge">${firstEvidence.evidence_link}</span></div>
                    </div>
                `;
            }

            columnElement.innerHTML = collapsedHTML;
            columnElement.setAttribute('data-expanded', 'false');
            (seeMoreButton as HTMLElement).textContent = `See more (${targetColumn === 'persons' ? incident.persons_involved!.length - 1 : incident.evidence!.length - 1} more)`;
            console.log(`Collapsed ${targetColumn} for incident ${incidentId}`);
        } else {
            // Expand to show all items
            let expandedHTML = '';

            if (targetColumn === 'persons') {
                if (incident.persons_involved && incident.persons_involved.length > 0) {
                    expandedHTML = incident.persons_involved.map((person: any) => `
                        <div class="text-xs mb-2 pb-2 border-b border-gray-200 last:border-b-0">
                            <span class="inline-block bg-purple-200 text-purple-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${person.person_type.toUpperCase()}</span>
                            <div class="ml-1">
                                <div><span class="font-medium text-gray-700">Name:</span> <span class="blur-text-badge">${person.first_name}</span></div>
                                <div><span class="font-medium text-gray-700">Contact:</span> <span class="blur-text-badge">${person.contact_number}</span></div>
                                <div><span class="font-medium text-gray-700">Other:</span> <span class="blur-text-badge">${person.other_info}</span></div>
                            </div>
                        </div>
                    `).join('');
                }
            } else if (targetColumn === 'evidence') {
                if (incident.evidence && incident.evidence.length > 0) {
                    expandedHTML = incident.evidence.map((item: any) => `
                        <div class="text-xs mb-2 pb-2 border-b border-gray-200 last:border-b-0">
                            <span class="inline-block bg-orange-200 text-orange-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${item.evidence_type}</span>
                            <div class="ml-1">
                                <div><span class="font-medium text-gray-700">Desc:</span> <span class="blur-text-badge">${item.description}</span></div>
                                <div><span class="font-medium text-gray-700">Link:</span> <span class="blur-text-badge">${item.evidence_link}</span></div>
                            </div>
                        </div>
                    `).join('');
                }
            }

            columnElement.innerHTML = expandedHTML;
            columnElement.setAttribute('data-expanded', 'true');
            (seeMoreButton as HTMLElement).textContent = 'See less';
            console.log(`Expanded ${targetColumn} for incident ${incidentId}`);
        }
    }

    private setupSidebarToggle(): void {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('aside');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle?.addEventListener('click', () => {
            sidebar?.classList.toggle('-translate-x-full');
            sidebarOverlay?.classList.toggle('hidden');
        });

        sidebarOverlay?.addEventListener('click', () => {
            sidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('hidden');
        });

        const sidebarLinks = sidebar?.querySelectorAll('a, button');
        sidebarLinks?.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    sidebar?.classList.add('-translate-x-full');
                    sidebarOverlay?.classList.add('hidden');
                }
            });
        });
    }

    private showSkeletonLoaders(): void {
        // Show skeleton rows
        try {
            for (let i = 1; i <= 5; i++) {
                const skeleton = document.getElementById(`skeletonRow${i}`);
                if (skeleton && skeleton instanceof HTMLElement) {
                    skeleton.style.display = '';
                }
            }
        } catch (error) {
            console.error('Error showing skeleton loaders:', error);
        }
    }

    private hideSkeletonLoaders(): void {
        // Hide skeleton rows
        try {
            for (let i = 1; i <= 5; i++) {
                const skeleton = document.getElementById(`skeletonRow${i}`);
                if (skeleton && skeleton instanceof HTMLElement) {
                    skeleton.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error hiding skeleton loaders:', error);
        }
    }

    private async loadInitialData(): Promise<void> {
        try {
            // Show skeleton loaders
            this.showSkeletonLoaders();

            const response = await fetch('/api/crimes');
            const data: CrimeIncidentResponse = await response.json();

            console.log('📊 API Response received:', data);

            this.incidents = data.incidents || [];
            console.log('📋 Incidents loaded:', this.incidents.length);
            console.log('👥 First incident:', this.incidents[0]);

            this.updateCategories(data.categories || []);
            this.updateBarangays(data.barangays || []);
            this.applyFilters();
            this.updateStats();
        } catch (error) {
            console.error('Error loading crime data:', error);
            this.hideSkeletonLoaders();
            this.showError('Failed to load crime data');
        }
    }

    private applyFilters(): void {
        // Show skeleton loaders during filtering
        this.showSkeletonLoaders();

        // Simulate slight delay for better UX (skeleton visible for at least 300ms)
        setTimeout(() => {
            this.filteredIncidents = this.incidents.filter(incident => {
                // Search filter - check both search inputs
                const searchTerm = this.filters.search.toLowerCase();
                const mainSearchMatch = incident.incident_title.toLowerCase().includes(searchTerm);
                const mainCodeMatch = incident.incident_code.toLowerCase().includes(searchTerm);
                const tableSearchMatch = incident.incident_title.toLowerCase().includes(this.filters.search.toLowerCase());
                const tableCodeMatch = incident.incident_code.toLowerCase().includes(this.filters.search.toLowerCase());

                if (searchTerm && !(mainSearchMatch || mainCodeMatch || tableSearchMatch)) {
                    return false;
                }

                // Category filter
                if (this.filters.category && incident.category?.category_name !== this.filters.category) {
                    return false;
                }

                // Status filter
                if (this.filters.status && incident.status !== this.filters.status) {
                    return false;
                }

                // Barangay filter
                if (this.filters.barangay && incident.barangay?.barangay_name !== this.filters.barangay) {
                    return false;
                }

                // Date filter
                if (this.filters.date) {
                    const incidentDate = new Date(incident.incident_date).toISOString().split('T')[0];
                    if (incidentDate !== this.filters.date) {
                        return false;
                    }
                }

                // Case Status filter (mapping page has these values)
                if (this.filters.status && incident.status !== this.filters.status) {
                    const validStatuses = ['reported', 'under_investigation', 'solved', 'closed', 'archived'];
                    if (!validStatuses.includes(incident.status)) {
                        return false;
                    }
                }

                // Clearance Status filter (mapping page has these values)
                if (this.filters.clearance && incident.clearance_status !== this.filters.clearance) {
                    const validClearanceStatuses = ['cleared', 'uncleared'];
                    if (!validClearanceStatuses.includes(incident.clearance_status)) {
                        return false;
                    }
                }

                return true;
            });

            this.currentPage = 1;
            this.renderTable();
        }, 300);
    }

    private updateStats(): void {
        const stats: CrimeStats = {
            total: this.incidents.length,
            live: this.incidents.filter(i => i.status === 'live').length,
            underInvestigation: this.incidents.filter(i => i.status === 'under investigation').length,
            cleared: this.incidents.filter(i => i.status === 'cleared').length
        };

        this.updateStatCard('totalCount', stats.total);
        this.updateStatCard('liveCount', stats.live);
        this.updateStatCard('investigationCount', stats.underInvestigation);
        this.updateStatCard('clearedCount', stats.cleared);
    }

    private updateStatCard(elementId: string, value: number): void {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value.toString();
        }
    }

    private updateCategories(categories: Array<{id: number, category_name: string}>): void {
        const categoryFilter = (window as any).crimePage.categoryFilter;
        const modalCrimeCategory = document.getElementById('modalCrimeCategory') as HTMLSelectElement;

        if (categoryFilter) {
            // Clear existing options except the first one
            while (categoryFilter.children.length > 1) {
                categoryFilter.removeChild(categoryFilter.lastChild!);
            }

            // Add new options to filter
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.category_name;
                option.textContent = category.category_name;
                categoryFilter.appendChild(option);
            });
        }

        // Also populate modal crime category dropdown
        if (modalCrimeCategory) {
            while (modalCrimeCategory.children.length > 1) {
                modalCrimeCategory.removeChild(modalCrimeCategory.lastChild!);
            }

            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.category_name;
                modalCrimeCategory.appendChild(option);
            });
        }
    }

    private updateBarangays(barangays: Array<{id: number, barangay_name: string}>): void {
        const barangayFilter = (window as any).crimePage.barangayFilter;
        const modalBarangay = document.getElementById('modalBarangay') as HTMLSelectElement;

        if (barangayFilter) {
            // Clear existing options except the first one
            while (barangayFilter.children.length > 1) {
                barangayFilter.removeChild(barangayFilter.lastChild!);
            }

            // Add new options to filter
            barangays.forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay.barangay_name;
                option.textContent = barangay.barangay_name;
                barangayFilter.appendChild(option);
            });
        }

        // Also populate modal barangay dropdown
        if (modalBarangay) {
            while (modalBarangay.children.length > 1) {
                modalBarangay.removeChild(modalBarangay.lastChild!);
            }

            barangays.forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay.id;
                option.textContent = barangay.barangay_name;
                modalBarangay.appendChild(option);
            });
        }
    }

    private renderTable(): void {
        const tbody = document.getElementById('crimesTableBody');
        if (!tbody || !(tbody instanceof HTMLElement)) return;

        const startIndex = (this.currentPage - 1) * this.pageSize;
        const endIndex = startIndex + this.pageSize;
        const pageIncidents = this.filteredIncidents.slice(startIndex, endIndex);

        // Hide skeleton loaders
        this.hideSkeletonLoaders();

        if (pageIncidents.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-search text-4xl mb-4 block text-gray-300"></i>
                        <p class="text-lg font-medium">No incidents found</p>
                        <p class="text-sm">Try adjusting your filters</p>
                    </td>
                </tr>
            `;
            return;
        }
        tbody.innerHTML = '';
        pageIncidents.forEach(incident => {
            const statusBadge = this.getStatusBadge(incident.status);
            const clearanceBadge = this.getClearanceBadge(incident.clearance_status);

            // Build location column (address, barangay, lat/long)
            const hasCoordinates = incident.latitude && incident.longitude;
            const locationHTML = `
                <div class="text-xs space-y-1">
                    <div><span class="font-medium text-gray-700">Barangay:</span> ${incident.barangay?.barangay_name || 'N/A'}</div>
                    <div><span class="font-medium text-gray-700">Address:</span> ${incident.address_details || 'N/A'}</div>
                    <div class="flex gap-2">
                        <span><span class="font-medium text-gray-700">Lat:</span> <span class="font-mono">${incident.latitude || 'N/A'}</span></span>
                        <span><span class="font-medium text-gray-700">Lng:</span> <span class="font-mono">${incident.longitude || 'N/A'}</span></span>
                    </div>
                    ${hasCoordinates ? `
                        <div class="mt-2">
                            <button class="view-map-btn px-3 py-1 bg-alertara-600 text-white text-xs rounded hover:bg-alertara-700 transition-colors"
                                    data-lat="${incident.latitude}"
                                    data-lng="${incident.longitude}"
                                    data-title="${incident.incident_title}"
                                    data-barangay="${incident.barangay?.barangay_name || 'Unknown'}">
                                <i class="fas fa-map-location-dot mr-1"></i>View Map
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;

            // Build incident details column (description, MO, weather, officer) with read more/less
            const description = incident.incident_description || 'N/A';
            const modus = incident.modus_operandi || 'N/A';
            const descExceeds = description.length > 50;
            const moExceeds = modus.length > 40;

            const detailsHTML = `
                <div class="text-xs space-y-1">
                    <div>
                        <span class="font-medium text-gray-700">Description:</span>
                        <div class="text-gray-600 mt-0.5">
                            <span id="desc-text-${incident.id}" data-full="${description}" data-truncated="${description.substring(0, 50)}">${description.substring(0, 50)}${descExceeds ? '...' : ''}</span>
                            ${descExceeds ? `<button class="read-more-btn text-blue-600 hover:text-blue-800 text-xs ml-1 font-semibold" data-incident-id="${incident.id}" data-target="desc-text-${incident.id}">Read more</button>` : ''}
                        </div>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">M.O.:</span>
                        <div class="text-gray-600 mt-0.5">
                            <span id="mo-text-${incident.id}" data-full="${modus}" data-truncated="${modus.substring(0, 40)}">${modus.substring(0, 40)}${moExceeds ? '...' : ''}</span>
                            ${moExceeds ? `<button class="read-more-btn text-blue-600 hover:text-blue-800 text-xs ml-1 font-semibold" data-incident-id="${incident.id}" data-target="mo-text-${incident.id}">Read more</button>` : ''}
                        </div>
                    </div>
                    <div><span class="font-medium text-gray-700">Weather:</span> ${incident.weather_condition || 'N/A'}</div>
                    <div><span class="font-medium text-gray-700">Officer:</span> ${incident.assigned_officer || 'N/A'}</div>
                </div>
            `;

            // Build statistics column (victim count, suspect count, status, clearance)
            const statsHTML = `
                <div class="text-xs space-y-1">
                    <div><span class="font-medium text-gray-700">Victims:</span> ${incident.victim_count || 0}</div>
                    <div><span class="font-medium text-gray-700">Suspects:</span> ${incident.suspect_count || 0}</div>
                    <div class="mt-1 pt-1 border-t border-gray-200">${statusBadge}</div>
                    <div class="mt-1">${clearanceBadge}</div>
                </div>
            `;

            // Display persons involved - show only first with Show More button
            let personsTableHTML = '<span class="text-gray-500 text-xs">None</span>';
            if (incident.persons_involved && incident.persons_involved.length > 0) {
                const firstPerson = incident.persons_involved[0];
                const totalPersons = incident.persons_involved.length;

                personsTableHTML = `
                    <div data-expand-target="${incident.id}-persons" class="text-xs mb-2 pb-2">
                        <span class="inline-block bg-purple-200 text-purple-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${firstPerson.person_type.toUpperCase()}</span>
                        <div class="ml-1">
                            <div><span class="font-medium text-gray-700">Name:</span> <span class="blur-text-badge">${firstPerson.first_name}</span></div>
                            <div><span class="font-medium text-gray-700">Contact:</span> <span class="blur-text-badge">${firstPerson.contact_number}</span></div>
                            <div><span class="font-medium text-gray-700">Other:</span> <span class="blur-text-badge">${firstPerson.other_info}</span></div>
                        </div>
                    </div>
                    ${totalPersons > 1 ? `<button class="show-more-button text-xs text-blue-600 hover:text-blue-800 font-semibold" data-incident-id="${incident.id}" data-target="persons">Show more (${totalPersons - 1} more)</button>` : ''}
                `;
            }

            // Display evidence details - show only first with Show More button
            let evidenceTableHTML = '<span class="text-gray-500 text-xs">None</span>';
            if (incident.evidence && incident.evidence.length > 0) {
                const firstEvidence = incident.evidence[0];
                const totalEvidence = incident.evidence.length;

                evidenceTableHTML = `
                    <div data-expand-target="${incident.id}-evidence" class="text-xs mb-2 pb-2">
                        <span class="inline-block bg-orange-200 text-orange-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${firstEvidence.evidence_type}</span>
                        <div class="ml-1">
                            <div><span class="font-medium text-gray-700">Desc:</span> <span class="blur-text-badge">${firstEvidence.description}</span></div>
                            <div><span class="font-medium text-gray-700">Link:</span>
                                ${firstEvidence.evidence_link ? `<span class="blur-text-badge text-xs">${firstEvidence.evidence_link}</span>` : '<span class="text-gray-500 text-xs">N/A</span>'}
                            </div>
                        </div>
                    </div>
                    ${totalEvidence > 1 ? `<button class="show-more-button text-xs text-blue-600 hover:text-blue-800 font-semibold" data-incident-id="${incident.id}" data-target="evidence">Show more (${totalEvidence - 1} more)</button>` : ''}
                `;
            }

            // Main row
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors border border-gray-200';

            row.innerHTML = `
                <td class="px-3 py-3 text-sm border-r border-gray-200">
                    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 cursor-pointer" data-incident-id="${incident.id}">
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-900 border-r border-gray-200">
                    ${incident.incident_code}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 max-w-xs">
                    <div class="font-medium">${incident.incident_title}</div>
                    <div class="text-xs text-gray-500">${incident.category?.category_name || 'Unknown'}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 min-w-40">
                    ${locationHTML}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                    ${this.formatDate(incident.incident_date)}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 min-w-48">
                    ${detailsHTML}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 min-w-40">
                    ${statsHTML}
                </td>
                <td class="px-4 py-3 text-xs border-r border-gray-200 min-w-40 max-h-32 overflow-y-auto">
                    ${personsTableHTML}
                </td>
                <td class="px-4 py-3 text-xs border-r border-gray-200 min-w-40 max-h-32 overflow-y-auto">
                    ${evidenceTableHTML}
                </td>
                <td class="px-4 py-3 text-sm border-gray-200">
                    <div class="flex gap-1">
                        <button onclick="event.stopPropagation(); crimePageManager.viewIncident(${incident.id})"
                            class="px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="event.stopPropagation(); crimePageManager.editIncident(${incident.id})"
                            class="px-2 py-1 text-xs bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="event.stopPropagation(); crimePageManager.deleteIncident(${incident.id})"
                            class="px-2 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition-colors" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

        // Setup checkbox and expand listeners for the newly rendered table
        this.setupTableCheckboxListeners();
        this.setupViewMapListeners();
        this.updatePagination();

        // Trigger auto-decryption for newly rendered table if session is valid
        this.triggerTableAutoDecryption();
    }

    /**
     * Trigger auto-decryption after table render
     */
    private triggerTableAutoDecryption(): void {
        // Access the DataDecryptionManager instance to trigger auto-decryption
        setTimeout(() => {
            // Find and call the retryAutoDecryption method on the DataDecryptionManager
            // The manager is initialized globally in data-decryption.ts
            const scripts = document.querySelectorAll('script');
            if (typeof (window as any).DataDecryptionManager !== 'undefined') {
                // If the manager exposes a way to trigger auto-decrypt, call it
                // This is a safe approach to trigger decryption across page renders
                const event = new CustomEvent('crimePageTableRendered');
                document.dispatchEvent(event);
            }
        }, 100);
    }

    private setupViewMapListeners(): void {
        const viewMapButtons = document.querySelectorAll('.view-map-btn');
        viewMapButtons.forEach(button => {
            button.addEventListener('click', (e: Event) => {
                e.preventDefault();
                e.stopPropagation();

                const btn = button as HTMLElement;
                const lat = parseFloat(btn.getAttribute('data-lat') || '0');
                const lng = parseFloat(btn.getAttribute('data-lng') || '0');
                const title = btn.getAttribute('data-title') || 'Crime Location';
                const barangay = btn.getAttribute('data-barangay') || 'Unknown';

                this.openViewLocationMap(lat, lng, title, barangay);
            });
        });
    }

    private openViewLocationMap(latitude: number, longitude: number, title: string, barangay: string): void {
        const modal = document.getElementById('viewLocationModal');
        if (!modal) return;

        // Update modal content
        const titleEl = document.getElementById('viewLocationTitle');
        const subtitleEl = document.getElementById('viewLocationSubtitle');
        const latEl = document.getElementById('viewLocationLat');
        const lngEl = document.getElementById('viewLocationLng');
        const barangayEl = document.getElementById('viewLocationBarangay');

        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = `Barangay: ${barangay}`;
        if (latEl) latEl.textContent = latitude.toFixed(6);
        if (lngEl) lngEl.textContent = longitude.toFixed(6);
        if (barangayEl) barangayEl.textContent = barangay;

        // Show modal
        modal.classList.remove('hidden');

        // Initialize or update map
        setTimeout(() => {
            this.initializeViewLocationMap(latitude, longitude);
        }, 100);
    }

    private viewLocationMap: any = null;

    private initializeViewLocationMap(latitude: number, longitude: number): void {
        const mapContainer = document.getElementById('viewLocationMap');
        if (!mapContainer) return;

        // Destroy existing map if it exists
        if (this.viewLocationMap) {
            this.viewLocationMap.remove();
            this.viewLocationMap = null;
        }

        // Initialize Leaflet map
        this.viewLocationMap = (window as any).L.map('viewLocationMap').setView([latitude, longitude], 15);

        // Add tile layer
        (window as any).L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(this.viewLocationMap);

        // Add marker at the specific location
        const marker = (window as any).L.marker([latitude, longitude]).addTo(this.viewLocationMap);
        marker.bindPopup('<strong>Crime Location</strong><br/>Latitude: ' + latitude.toFixed(6) + '<br/>Longitude: ' + longitude.toFixed(6));
        marker.openPopup();

        // Optionally add a circle around the location
        (window as any).L.circle([latitude, longitude], {
            color: '#274d4c',
            fillColor: '#95d5d0',
            fillOpacity: 0.2,
            radius: 50 // 50 meters radius
        }).addTo(this.viewLocationMap);

        // Invalidate size to ensure proper rendering
        setTimeout(() => {
            if (this.viewLocationMap) {
                this.viewLocationMap.invalidateSize();
            }
        }, 50);
    }

    private getStatusBadge(status: string): string {
        const statusConfig = {
            reported: 'bg-red-100 text-red-800',
            under_investigation: 'bg-yellow-100 text-yellow-800',
            solved: 'bg-green-100 text-green-800',
            closed: 'bg-blue-100 text-blue-800',
            archived: 'bg-gray-100 text-gray-800'
        };
        const className = statusConfig[status as keyof typeof statusConfig] || 'bg-blue-100 text-blue-800';
        return `<span class="inline-block px-2 py-1 text-xs font-semibold rounded ${className}">${this.capitalizeFirst(status)}</span>`;
    }

    private getClearanceBadge(clearanceStatus: string): string {
        const className = clearanceStatus === 'cleared' 
            ? 'bg-green-100 text-green-800' 
            : 'bg-red-100 text-red-800';
        return `<span class="inline-block px-2 py-1 text-xs font-semibold rounded ${className}">${this.capitalizeFirst(clearanceStatus)}</span>`;
    }

    private capitalizeFirst(str: string): string {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    private formatDate(dateString: string): string {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        } catch {
            return 'N/A';
        }
    }

    private renderRecentIncidents(): void {
        const container = document.getElementById('recentIncidents');
        if (!container) return;

        const recentIncidents = this.filteredIncidents.slice(0, 5);
        
        if (recentIncidents.length === 0) {
            container.innerHTML = `
                <div class="p-4 text-center text-gray-500">
                    <i class="fas fa-inbox text-2xl mb-2 block text-gray-300"></i>
                    <p class="text-sm">No recent incidents</p>
                </div>
            `;
            return;
        }

        container.innerHTML = recentIncidents.map(incident => `
            <div class="p-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors" 
                 onclick="crimePageManager.viewIncident(${incident.id})">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-${this.getCategoryColor(incident)}-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas ${this.getCategoryIcon(incident)} text-${this.getCategoryColor(incident)}-600 text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium text-gray-900 truncate">${incident.incident_title}</h4>
                        <p class="text-xs text-gray-600">${incident.barangay?.barangay_name || 'Unknown'}</p>
                        <div class="flex gap-2 mt-1">
                            ${this.getStatusBadge(incident.status)}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    private getCategoryColor(incident: CrimeIncident): string {
        return incident.category?.color_code?.replace('#', '') || 'alertara';
    }

    private getCategoryIcon(incident: CrimeIncident): string {
        return incident.category?.icon || 'fa-exclamation-circle';
    }

    private updatePagination(): void {
        const container = document.getElementById('pagination');
        const showingStart = document.getElementById('showingStart');
        const showingEnd = document.getElementById('showingEnd');
        const totalRecords = document.getElementById('totalRecords');

        if (!container || !showingStart || !showingEnd || !totalRecords) return;

        const totalItems = this.filteredIncidents.length;
        const startIndex = (this.currentPage - 1) * this.pageSize + 1;
        const endIndex = Math.min(this.currentPage * this.pageSize, totalItems);
        const totalPages = Math.ceil(totalItems / this.pageSize);

        showingStart.textContent = startIndex.toString();
        showingEnd.textContent = endIndex.toString();
        totalRecords.textContent = totalItems.toString();

        // Generate pagination buttons
        let paginationHTML = '';

        // First button
        if (this.currentPage > 1) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(1)"
                        class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors" title="First page">
                    <i class="fas fa-step-backward"></i>
                </button>
            `;
        }

        // Previous button
        if (this.currentPage > 1) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${this.currentPage - 1})"
                        class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
        }

        // Page numbers with ellipsis
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, this.currentPage + 2);

        // Add first page if not in range
        if (startPage > 1) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(1)" class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors">
                    1
                </button>
            `;

            // Add ellipsis if there's a gap
            if (startPage > 2) {
                paginationHTML += `<span class="px-2 py-1 text-sm text-gray-500">...</span>`;
            }
        }

        // Add page numbers around current page
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === this.currentPage;
            const className = isActive
                ? 'px-3 py-1 text-sm bg-alertara-600 text-white border-alertara-600 rounded'
                : 'px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors';

            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${i})" class="${className}">
                    ${i}
                </button>
            `;
        }

        // Add last page if not in range
        if (endPage < totalPages) {
            // Add ellipsis if there's a gap
            if (endPage < totalPages - 1) {
                paginationHTML += `<span class="px-2 py-1 text-sm text-gray-500">...</span>`;
            }

            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${totalPages})" class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors">
                    ${totalPages}
                </button>
            `;
        }

        // Next button
        if (this.currentPage < totalPages) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${this.currentPage + 1})"
                        class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }

        // Last button
        if (this.currentPage < totalPages) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${totalPages})"
                        class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors rounded-r" title="Last page">
                    <i class="fas fa-step-forward"></i>
                </button>
            `;
        }

        container.innerHTML = paginationHTML;
    }

    private viewIncident(id: number): void {
        try {
            const incident = this.incidents.find(i => i.id === id);
            if (!incident) return;

            // Log the view action to audit logs
            this.logIncidentView(id);

            // Create modal HTML with comprehensive incident data
            const modal = document.createElement('div');
            modal.id = 'viewIncidentModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto';

            // Build persons HTML
            let personsHTML = '<p class="text-gray-500 text-sm italic">No persons involved</p>';
            if (incident.persons_involved && incident.persons_involved.length > 0) {
                personsHTML = incident.persons_involved.map((person: any) => `
                    <div class="p-4 bg-purple-50 rounded-lg border border-purple-200 mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block bg-purple-200 text-purple-900 px-2 py-1 rounded text-xs font-semibold">${person.person_type.toUpperCase()}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="font-medium text-gray-700">First Name:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${person.first_name}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Middle Name:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${person.middle_name}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Last Name:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${person.last_name}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Contact Number:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${person.contact_number}</div>
                            </div>
                            <div class="col-span-2">
                                <label class="font-medium text-gray-700">Additional Info:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${person.other_info}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // Build evidence HTML
            let evidenceHTML = '<p class="text-gray-500 text-sm italic">No evidence recorded</p>';
            if (incident.evidence && incident.evidence.length > 0) {
                evidenceHTML = incident.evidence.map((item: any) => `
                    <div class="p-4 bg-orange-50 rounded-lg border border-orange-200 mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block bg-orange-200 text-orange-900 px-2 py-1 rounded text-xs font-semibold">${item.evidence_type}</span>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <label class="font-medium text-gray-700">Description:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${item.description}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Evidence Link:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${item.evidence_link}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            modal.innerHTML = `
                <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-alertara-50 to-alertara-100 sticky top-0">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">${incident.incident_code}</h2>
                            <p class="text-sm text-gray-600 mt-1">${incident.incident_title}</p>
                        </div>
                        <button onclick="document.getElementById('viewIncidentModal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Basic Info -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle text-blue-600"></i>
                                Basic Information
                            </h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Code</label>
                                    <p class="text-gray-900 mt-1">${incident.incident_code}</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Category</label>
                                    <p class="text-gray-900 mt-1">${incident.category?.category_name || 'N/A'}</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Date</label>
                                    <p class="text-gray-900 mt-1">${this.formatDate(incident.incident_date)}</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Status</label>
                                    <div class="mt-1">${this.getStatusBadge(incident.status)}</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Clearance</label>
                                    <div class="mt-1">${this.getClearanceBadge(incident.clearance_status)}</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Time</label>
                                    <p class="text-gray-900 mt-1">${incident.incident_time || 'N/A'}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-file-alt text-blue-600"></i>
                                Description & Details
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg space-y-4">
                                <div>
                                    <label class="font-medium text-gray-700">Description:</label>
                                    <p class="text-gray-600 mt-2">${incident.incident_description || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-700">Modus Operandi:</label>
                                    <p class="text-gray-600 mt-2">${incident.modus_operandi || 'N/A'}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="font-medium text-gray-700">Weather Condition:</label>
                                        <p class="text-gray-600 mt-2">${incident.weather_condition || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="font-medium text-gray-700">Assigned Officer:</label>
                                        <p class="text-gray-600 mt-2">${incident.assigned_officer || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-red-600"></i>
                                Location
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg space-y-3">
                                <div>
                                    <label class="font-medium text-gray-700">Barangay:</label>
                                    <p class="text-gray-600 mt-1">${incident.barangay?.barangay_name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-700">Address:</label>
                                    <p class="text-gray-600 mt-1">${incident.address_details || 'N/A'}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="font-medium text-gray-700">Latitude:</label>
                                        <p class="text-gray-600 font-mono mt-1">${incident.latitude || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="font-medium text-gray-700">Longitude:</label>
                                        <p class="text-gray-600 font-mono mt-1">${incident.longitude || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-bar text-green-600"></i>
                                Statistics
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                    <label class="text-sm font-medium text-blue-700">Victim Count</label>
                                    <p class="text-3xl font-bold text-blue-900 mt-1">${incident.victim_count || 0}</p>
                                </div>
                                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                    <label class="text-sm font-medium text-red-700">Suspect Count</label>
                                    <p class="text-3xl font-bold text-red-900 mt-1">${incident.suspect_count || 0}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Persons Involved -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-users text-purple-600"></i>
                                Complainant/Persons Involved (${incident.persons_involved_count || 0})
                            </h3>
                            ${personsHTML}
                        </div>

                        <!-- Evidence -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-fingerprint text-orange-600"></i>
                                Evidence (${incident.evidence_count || 0})
                            </h3>
                            ${evidenceHTML}
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            // Close modal when clicking outside
            modal.addEventListener('click', (e: Event) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        } catch (error) {
            console.error('Error viewing incident:', error);
            this.showError('Error loading incident details');
        }
    }

    private editIncident(id: number): void {
        window.location.href = `/crime-incident/${id}/edit`;
    }

    private deleteIncident(id: number): void {
        if (confirm('Are you sure you want to delete this incident?')) {
            // Implement delete functionality
            console.log('Delete incident:', id);
        }
    }

    public async showDetailsModal(id: number): Promise<void> {
        try {
            const response = await fetch(`/api/crime-incident/${id}/details`);
            const data = await response.json();

            if (!data.success) {
                this.showError('Failed to load incident details');
                return;
            }

            this.displayDetailsModal(data);
        } catch (error) {
            console.error('Error fetching incident details:', error);
            this.showError('Error loading incident details');
        }
    }

    private displayDetailsModal(data: any): void {
        // Create modal HTML
        const modal = document.createElement('div');
        modal.id = 'detailsModal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';

        const incident = data.incident;
        const persons = data.persons_involved || [];
        const evidence = data.evidence || [];

        let personsHTML = '';
        if (persons.length === 0) {
            personsHTML = '<p class="text-gray-500 text-sm italic">No persons involved recorded</p>';
        } else {
            personsHTML = persons.map((person: any) => `
                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 mb-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <span class="text-xs font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded">${person.person_type}</span>
                            <div class="mt-2 text-sm text-gray-700">
                                <div class="blur-text font-medium" title="Encrypted - Decryption coming soon">██████████ ██████</div>
                                <div class="blur-text text-xs text-gray-600 mt-1" title="Encrypted - Decryption coming soon">██████████████</div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        let evidenceHTML = '';
        if (evidence.length === 0) {
            evidenceHTML = '<p class="text-gray-500 text-sm italic">No evidence recorded</p>';
        } else {
            evidenceHTML = evidence.map((item: any) => `
                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 mb-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <span class="text-xs font-medium bg-green-100 text-green-800 px-2 py-1 rounded">${item.evidence_type}</span>
                            <div class="mt-2 text-sm text-gray-700">
                                <div class="blur-text" title="Encrypted - Decryption coming soon">████████████████████</div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-alertara-50 to-alertara-100">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">${incident.incident_code}</h2>
                        <p class="text-sm text-gray-600 mt-1">${incident.incident_title}</p>
                    </div>
                    <button onclick="document.getElementById('detailsModal').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Incident Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Incident Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Category</p>
                                <p class="text-sm text-gray-900">${incident.category.category_name}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Location</p>
                                <p class="text-sm text-gray-900">${incident.barangay.barangay_name}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Date</p>
                                <p class="text-sm text-gray-900">${this.formatDate(incident.incident_date)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Status</p>
                                <p class="text-sm">${this.getStatusBadge(incident.status)}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Persons Involved -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i class="fas fa-users text-purple-600"></i>
                            Persons Involved (${persons.length})
                        </h3>
                        <div class="space-y-2">
                            ${personsHTML}
                        </div>
                        <p class="text-xs text-gray-500 mt-3 italic">⚠️ Sensitive information is encrypted and blurred for security. Decryption will be available in a future update.</p>
                    </div>

                    <!-- Evidence -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i class="fas fa-fingerprint text-orange-600"></i>
                            Evidence (${evidence.length})
                        </h3>
                        <div class="space-y-2">
                            ${evidenceHTML}
                        </div>
                        <p class="text-xs text-gray-500 mt-3 italic">⚠️ Sensitive information is encrypted and blurred for security. Decryption will be available in a future update.</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
                    <button onclick="document.getElementById('detailsModal').remove()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close modal on overlay click
        modal.addEventListener('click', (e: Event) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    private exportData(): void {
        const csvContent = this.generateCSV();
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `crime-incidents-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    private generateCSV(): string {
        const headers = ['Code', 'Title', 'Category', 'Barangay', 'Date', 'Status', 'Clearance'];
        const rows = this.filteredIncidents.map(incident => [
            incident.incident_code,
            incident.incident_title,
            incident.category?.category_name || 'Unknown',
            incident.barangay?.barangay_name || 'Unknown',
            this.formatDate(incident.incident_date),
            incident.status,
            incident.clearance_status
        ]);

        return [headers, ...rows]
            .map(row => row.map(cell => `"${cell}"`).join(','))
            .join('\n');
    }

    private openModal(): void {
        const modal = document.getElementById('incidentModal');
        if (modal && modal instanceof HTMLElement) {
            modal.classList.remove('hidden');
            if (document.body && document.body.style) {
                document.body.style.overflow = 'hidden';
            }
        }
    }

    private closeModal(): void {
        const modal = document.getElementById('incidentModal');
        if (modal && modal instanceof HTMLElement) {
            modal.classList.add('hidden');
            if (document.body && document.body.style) {
                document.body.style.overflow = 'auto';
            }
        }
    }

    private showAddIncidentModal(): void {
        const modal = document.getElementById('addIncidentModal');
        if (modal && modal instanceof HTMLElement) {
            modal.classList.remove('hidden');
            if (document.body && document.body.style) {
                document.body.style.overflow = 'hidden';
            }
            this.loadCategoriesIntoModal();
        }
    }

    private closeAddIncidentModal(): void {
        const modal = document.getElementById('addIncidentModal');
        if (modal && modal instanceof HTMLElement) {
            modal.classList.add('hidden');
            if (document.body && document.body.style) {
                document.body.style.overflow = 'auto';
            }
            this.resetAddIncidentForm();
        }
    }

    private closeViewLocationModal(): void {
        const modal = document.getElementById('viewLocationModal');
        if (modal && modal instanceof HTMLElement) {
            modal.classList.add('hidden');
            // Destroy the map when closing
            if (this.viewLocationMap) {
                this.viewLocationMap.remove();
                this.viewLocationMap = null;
            }
        }
    }

    private loadCategoriesIntoModal(): void {
        const categorySelect = document.getElementById('modalCrimeCategory') as HTMLSelectElement;
        if (categorySelect && this.incidents.length > 0) {
            // Extract unique categories from incidents
            const uniqueCategories = [...new Map(
                this.incidents
                    .filter(incident => incident.category && incident.category.id)
                    .map(incident => [incident.category.id, incident.category])
            ).values()];
            
            const categoryOptions = uniqueCategories.map(cat => 
                `<option value="${cat.id}">${cat.category_name}</option>`
            );
            
            categorySelect.innerHTML = '<option value="">Select a category...</option>' + categoryOptions.join('');
        }
    }

    private resetAddIncidentForm(): void {
        const form = document.getElementById('addIncidentForm') as HTMLFormElement;
        if (form) {
            form.reset();
        }
    }

    private async submitIncidentForm(): Promise<void> {
        const form = document.getElementById('addIncidentForm') as HTMLFormElement;
        const submitBtn = form?.querySelector('button[type="submit"]') as HTMLButtonElement;
        if (!form || !submitBtn) return;

        // Disable button and show loading
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        const formData = new FormData(form);

        try {
            // Get persons_involved and evidence_items from global variables (set in HTML)
            const personsInvolvedList = (window as any).personsInvolvedList || [];
            const evidenceList = (window as any).evidenceList || [];

            // Convert persons involved to proper format
            const persons_involved = personsInvolvedList.map((person: any) => ({
                person_type: person.type,
                first_name: person.firstName,
                middle_name: person.middleName || '',
                last_name: person.lastName,
                contact_number: person.contactNumber || '',
                other_info: person.otherInfo || ''
            }));

            // Convert evidence to proper format
            const evidence_items = evidenceList.map((evidence: any) => ({
                evidence_type: evidence.type,
                description: evidence.description || '',
                evidence_link: evidence.link || ''
            }));

            // Create JSON payload
            const payload = {
                incident_title: formData.get('incident_title'),
                incident_description: formData.get('incident_description'),
                crime_category_id: formData.get('crime_category_id'),
                barangay_id: formData.get('barangay_id'),
                incident_date: formData.get('incident_date'),
                incident_time: formData.get('incident_time'),
                latitude: formData.get('latitude'),
                longitude: formData.get('longitude'),
                address_details: formData.get('address_details'),
                victim_count: formData.get('victim_count') || 0,
                suspect_count: formData.get('suspect_count') || 0,
                modus_operandi: formData.get('modus_operandi'),
                weather_condition: formData.get('weather_condition'),
                assigned_officer: formData.get('assigned_officer'),
                status: formData.get('status'),
                clearance_status: formData.get('clearance_status'),
                clearance_date: formData.get('clearance_date'),
                persons_involved: persons_involved,
                evidence_items: evidence_items
            };

            const response = await fetch('/crime-incident', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            // Check content-type before parsing
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const error = await response.text();
                console.error('❌ Non-JSON response:', error);
                this.showError('Server error: Expected JSON response but got HTML. Check browser console.');
                return;
            }

            const result = await response.json();

            if (response.ok) {
                console.log('✅ Incident saved successfully:', result);

                // Show success message immediately
                this.showSuccess('✅ Incident saved successfully!');

                // Close modal
                this.closeAddIncidentModal();

                // Don't show notification here - let WebSocket broadcast handle it
                // The server will broadcast the event, and our WebSocket listener will show the detailed notification
                // This prevents duplicate notifications

                console.log('⏳ Waiting for real-time broadcast to update the table...');
            } else {
                console.error('❌ Error response:', result);
                this.showError('Failed to create incident: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Error creating incident:', error);
            this.showError('An error occurred while creating the incident');
        } finally {
            // Restore button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    private showSuccess(message: string): void {
        // Simple success notification
        const successDiv = document.createElement('div');
        successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        successDiv.textContent = message;
        document.body.appendChild(successDiv);

        setTimeout(() => {
            document.body.removeChild(successDiv);
        }, 3000);
    }

    private initializeRealtimeListeners(): void {
        console.log('🔍 Initializing real-time listeners for crime incidents...');
        
        // Request notification permission if not already granted
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('🔔 Notification permission:', permission);
                if (permission === 'granted') {
                    console.log('✅ Browser notifications enabled');
                } else {
                    console.log('⚠️ Browser notifications denied, will use custom notifications');
                }
            }).catch(error => {
                console.error('❌ Error requesting notification permission:', error);
            });
        }
        
        // Check if Echo is available
        if (typeof (window as any).Echo !== 'undefined' && (window as any).Echo) {
            console.log('🔌 Echo available - Setting up real-time listeners...');
            
            // Add connection debugging
            (window as any).Echo.connector.pusher.connection.bind('connected', function() {
                console.log('✅ Pusher connected successfully');
            });
            
            (window as any).Echo.connector.pusher.connection.bind('disconnected', function() {
                console.log('❌ Pusher disconnected');
            });
            
            (window as any).Echo.connector.pusher.connection.bind('error', function(err: any) {
                console.error('❌ Pusher connection error:', err);
            });
            
            // Listen for crime incident events
            const channel = (window as any).Echo.channel('crime-incidents');
            
            channel.subscribed(function() {
                console.log('✅ Subscribed to crime-incidents channel');
            });
            
            channel.listen('.incident.created', (e: any) => {
                console.log('🆕 New incident created:', e);
                this.handleNewIncident(e);
            });
            
            channel.listen('.incident.updated', (e: any) => {
                console.log('📝 Incident updated:', e);
                this.handleUpdatedIncident(e);
            });
            
            channel.listen('.incident.deleted', (e: any) => {
                console.log('🗑️ Incident deleted:', e);
                this.handleDeletedIncident(e);
            });
            
            console.log('✅ Real-time listeners setup complete');
        } else {
            console.warn('⚠️ Echo not available - real-time features disabled');
        }
    }
    
    private handleNewIncident(incidentData: any): void {
        console.log('📢 Handling new incident from WebSocket broadcast');

        // Show notification for new incident
        (window as any).NotificationManager?.showIncidentNotification('New Incident Created!', incidentData, 'created');

        // Add incident to existing array without full refresh (more efficient)
        if (incidentData && incidentData.id) {
            // Create incident object matching our CrimeIncident interface
            const newIncident = {
                id: incidentData.id,
                incident_code: incidentData.incident_code || 'INC-' + Date.now(),
                incident_title: incidentData.incident_title || 'New Incident',
                incident_date: incidentData.incident_date || new Date().toISOString().split('T')[0],
                status: incidentData.status || 'reported',
                clearance_status: incidentData.clearance_status || 'uncleared',
                category: {
                    id: incidentData.crime_category_id || 0,
                    category_name: incidentData.category_name || 'Unknown'
                },
                barangay: {
                    id: incidentData.barangay_id || 0,
                    barangay_name: incidentData.location || 'Unknown'
                }
            };

            // Add to beginning of incidents array
            this.incidents.unshift(newIncident);

            // Re-apply filters to update filtered list
            this.applyFilters();

            // Update stats
            this.updateStats();

            console.log('✅ Incident added to local data, table will refresh automatically');
        }
    }
    
    private handleUpdatedIncident(incidentData: any): void {
        console.log('📢 Handling updated incident from WebSocket broadcast');

        // Show notification for updated incident
        (window as any).NotificationManager?.showIncidentNotification('Incident Updated', incidentData, 'updated');

        // Update incident in existing array
        if (incidentData && incidentData.id) {
            const index = this.incidents.findIndex(i => i.id === incidentData.id);
            if (index !== -1) {
                // Update the incident
                this.incidents[index] = {
                    ...this.incidents[index],
                    incident_title: incidentData.incident_title,
                    status: incidentData.status,
                    clearance_status: incidentData.clearance_status
                };
            }
            this.applyFilters();
            this.updateStats();
            console.log('✅ Incident updated in local data');
        }
    }

    private handleDeletedIncident(incidentData: any): void {
        console.log('📢 Handling deleted incident from WebSocket broadcast');

        // Show notification for deleted incident
        (window as any).NotificationManager?.showIncidentNotification('Incident Deleted', incidentData, 'deleted');

        // Remove incident from existing array
        if (incidentData && incidentData.id) {
            this.incidents = this.incidents.filter(i => i.id !== incidentData.id);
            this.applyFilters();
            this.updateStats();
            console.log('✅ Incident removed from local data');
        }
    }

    public goToPage(page: number): void {
        this.currentPage = page;
        this.renderTable();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    private showError(message: string): void {
        // Simple error notification
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        errorDiv.textContent = message;
        document.body.appendChild(errorDiv);

        setTimeout(() => {
            document.body.removeChild(errorDiv);
        }, 3000);
    }

    private logIncidentView(incidentId: number): void {
        // Log the incident view to audit logs
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Get the incident code for logging
        const incident = this.incidents.find(i => i.id === incidentId);
        const incidentCode = incident?.incident_code || 'Unknown';

        const headers: any = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        // Add CSRF token if available
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        fetch(`/api/crimes/${incidentId}/log-view`, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`📝 View logged for incident ${incidentCode} (ID: ${incidentId})`);
            } else {
                console.warn(`⚠️ Failed to log view for incident ${incidentCode}: ${data.message || 'Unknown error'}`);
            }
        })
        .catch(error => {
            console.error(`❌ Error logging incident view: ${error.message}`);
        });
    }
}

// Initialize the crime page manager - check to prevent duplicate initialization
function initializeCrimePageManager(): void {
    // Only initialize once
    if (typeof (window as any).crimePageManager !== 'undefined') {
        console.log('ℹ️ Crime Page Manager already initialized, skipping...');
        return;
    }

    try {
        console.log('📋 Initializing Crime Page Manager...');
        (window as any).crimePageManager = new CrimePageManager();
        console.log('✅ Crime Page Manager initialized successfully');
    } catch (error) {
        console.error('❌ Failed to initialize Crime Page Manager:', error);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCrimePageManager);
} else {
    // DOM is already loaded
    initializeCrimePageManager();
}
