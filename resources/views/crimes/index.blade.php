<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crime Incidents - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
    @stack('styles')
</head>
<body class="bg-gray-100">
    <!-- Header Component -->
    @include('components.header')

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Main Content -->
    <main class="lg:ml-72 ml-0 lg:mt-16 mt-16 min-h-screen bg-gray-100">
        <div class="p-6 pt-8 pb-12">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Crime Incidents</h1>
                <p class="text-gray-600 mt-2">Manage and view all reported crime incidents</p>
            </div>

            <!-- Crime Incidents Table -->
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table id="crimesTable" class="w-full display nowrap">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Barangay</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Clearance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($crimes as $crime)
                        <tr>
                            <td>{{ $crime->incident_code }}</td>
                            <td>{{ $crime->incident_title }}</td>
                            <td>{{ $crime->category->category_name ?? 'Unknown' }}</td>
                            <td>{{ $crime->barangay->barangay_name ?? 'Unknown' }}</td>
                            <td>{{ $crime->incident_date ? $crime->incident_date->format('M d, Y') : 'N/A' }}</td>
                            <td><span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">{{ ucfirst($crime->status) }}</span></td>
                            <td>
                                @if($crime->clearance_status === 'cleared')
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">{{ ucfirst($crime->clearance_status) }}</span>
                                @else
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-800">{{ ucfirst($crime->clearance_status) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <button class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('aside');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle?.addEventListener('click', function() {
            sidebar?.classList.toggle('-translate-x-full');
            sidebarOverlay?.classList.toggle('hidden');
        });

        sidebarOverlay?.addEventListener('click', function() {
            sidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('hidden');
        });

        const sidebarLinks = sidebar?.querySelectorAll('a, button');
        sidebarLinks?.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    sidebar?.classList.add('-translate-x-full');
                    sidebarOverlay?.classList.add('hidden');
                }
            });
        });

        // Initialize DataTables with client-side processing
        $(document).ready(function() {
            $('#crimesTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [[0, 'desc']]
            });
        });
    </script>
</body>
</html>
