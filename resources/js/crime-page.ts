interface CrimeIncident {
    id: number;
    incident_code: string;
    incident_title: string;
    incident_date: string;
    status: 'live' | 'under investigation' | 'cleared';
    clearance_status: 'cleared' | 'uncleared';
    latitude?: number;
    longitude?: number;
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
        this.initializeElements();
        this.initializeEventListeners();
        this.loadInitialData();
        this.initializeRealtimeListeners();
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

        // Sidebar toggle for mobile
        this.setupSidebarToggle();
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

    private async loadInitialData(): Promise<void> {
        try {
            const response = await fetch('/api/crimes');
            const data: CrimeIncidentResponse = await response.json();
            
            this.incidents = data.incidents || [];
            this.updateCategories(data.categories || []);
            this.updateBarangays(data.barangays || []);
            this.applyFilters();
            this.updateStats();
        } catch (error) {
            console.error('Error loading crime data:', error);
            this.showError('Failed to load crime data');
        }
    }

    private applyFilters(): void {
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
        if (categoryFilter) {
            // Clear existing options except the first one
            while (categoryFilter.children.length > 1) {
                categoryFilter.removeChild(categoryFilter.lastChild!);
            }

            // Add new options
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.category_name;
                option.textContent = category.category_name;
                categoryFilter.appendChild(option);
            });
        }
    }

    private updateBarangays(barangays: Array<{id: number, barangay_name: string}>): void {
        const barangayFilter = (window as any).crimePage.barangayFilter;
        if (barangayFilter) {
            // Clear existing options except the first one
            while (barangayFilter.children.length > 1) {
                barangayFilter.removeChild(barangayFilter.lastChild!);
            }

            // Add new options
            barangays.forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay.barangay_name;
                option.textContent = barangay.barangay_name;
                barangayFilter.appendChild(option);
            });
        }
    }

    private renderTable(): void {
        const tbody = document.getElementById('crimesTableBody');
        if (!tbody) return;

        const startIndex = (this.currentPage - 1) * this.pageSize;
        const endIndex = startIndex + this.pageSize;
        const pageIncidents = this.filteredIncidents.slice(startIndex, endIndex);

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
            const row = document.createElement('tr');
            
            // Apply standard row styling
            row.className = 'hover:bg-gray-50 transition-colors border border-gray-200';
            
            row.innerHTML = `
                <td class="px-4 py-3 text-sm font-medium text-gray-900 border-r border-gray-200">${incident.incident_code}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${incident.incident_title}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${incident.category?.category_name || 'Unknown'}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${incident.barangay?.barangay_name || 'Unknown'}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${this.formatDate(incident.incident_date)}</td>
                <td class="px-4 py-3 text-sm border-r border-gray-200">${statusBadge}</td>
                <td class="px-4 py-3 text-sm border-r border-gray-200">${clearanceBadge}</td>
                <td class="px-4 py-3 text-sm border-gray-200">
                    <div class="flex gap-2">
                        <button onclick="crimePageManager.viewIncident(${incident.id})" 
                            class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors" 
                            title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="crimePageManager.editIncident(${incident.id})" 
                            class="px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors" 
                            title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="crimePageManager.deleteIncident(${incident.id})" 
                            class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600 transition-colors" 
                            title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

        this.updatePagination();
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

        // Previous button
        if (this.currentPage > 1) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${this.currentPage - 1})" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded-l hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
        }

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, this.currentPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === this.currentPage;
            const className = isActive 
                ? 'px-3 py-1 text-sm bg-alertara-600 text-white border-alertara-600' 
                : 'px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors';
            
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${i})" class="${className}">
                    ${i}
                </button>
            `;
        }

        // Next button
        if (this.currentPage < totalPages) {
            paginationHTML += `
                <button onclick="crimePageManager.goToPage(${this.currentPage + 1})" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded-r hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }

        container.innerHTML = paginationHTML;
    }

    private viewIncident(id: number): void {
        const incident = this.incidents.find(i => i.id === id);
        if (!incident) return;

        const modalContent = document.getElementById('modalContent');
        if (!modalContent) return;

        modalContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Incident Details</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Code</label>
                            <p class="text-gray-900">${incident.incident_code}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <p class="text-gray-900">${incident.incident_title}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date</label>
                            <p class="text-gray-900">${this.formatDate(incident.incident_date)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <div class="mt-1">${this.getStatusBadge(incident.status)}</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Location</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Barangay</label>
                            <p class="text-gray-900">${incident.barangay?.barangay_name || 'Unknown'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <p class="text-gray-900">${incident.category?.category_name || 'Unknown'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Clearance Status</label>
                            <div class="mt-1">${this.getClearanceBadge(incident.clearance_status)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.openModal();
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
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    private closeModal(): void {
        const modal = document.getElementById('incidentModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    private showAddIncidentModal(): void {
        const modal = document.getElementById('addIncidentModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            this.loadCategoriesIntoModal();
        }
    }

    private closeAddIncidentModal(): void {
        const modal = document.getElementById('addIncidentModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            this.resetAddIncidentForm();
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
            const response = await fetch('/crime-incident', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                body: formData
            });

            if (response.ok) {
                const result = await response.json();
                
                // Use NotificationManager for success notification
                if (typeof (window as any).NotificationManager !== 'undefined') {
                    (window as any).NotificationManager.showIncidentNotification('Incident Created Successfully!', {
                        incident_title: result.incident_title || 'New Incident',
                        category_name: result.category_name || 'Category',
                        location: result.barangay_name || 'Location',
                        id: result.id,
                        incident_date: result.incident_date || new Date().toISOString(),
                        status: result.status || 'reported',
                        clearance_status: result.clearance_status || 'uncleared'
                    }, 'created');
                }
                
                this.closeAddIncidentModal();
                this.loadInitialData(); // Refresh the data
            } else {
                const error = await response.text();
                this.showError('Failed to create incident: ' + error);
            }
        } catch (error) {
            console.error('Error creating incident:', error);
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
        // Show notification for new incident
        (window as any).NotificationManager?.showIncidentNotification('New Incident Reported', incidentData, 'created');
        
        // Refresh the data to show the new incident
        this.loadInitialData();
    }
    
    private handleUpdatedIncident(incidentData: any): void {
        // Show notification for updated incident
        (window as any).NotificationManager?.showIncidentNotification('Incident Updated', incidentData, 'updated');
        
        // Refresh the data to show updated incident
        this.loadInitialData();
    }
    
    private handleDeletedIncident(incidentData: any): void {
        // Show notification for deleted incident
        (window as any).NotificationManager?.showIncidentNotification('Incident Deleted', incidentData, 'deleted');
        
        // Refresh the data to remove deleted incident
        this.loadInitialData();
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
}

// Initialize the crime page manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    (window as any).crimePageManager = new CrimePageManager();
});
