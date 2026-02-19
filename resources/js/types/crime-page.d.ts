declare global {
    interface Window {
        L: any;
        crimePageManager: CrimePageManager;
        crimePage: any;
    }
}

declare var L: any;

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
}

interface CrimeIncidentResponse {
    incidents: CrimeIncident[];
    categories: Array<{id: number, category_name: string}>;
    barangays: Array<{id: number, barangay_name: string}>;
}
