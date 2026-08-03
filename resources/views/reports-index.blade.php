@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Reports</h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">View and manage generated reports</p>
                </div>
            </div>
        </div>

        <a href="{{ route('reports.crime-data') }}"
           class="block bg-white rounded-xl border border-gray-200 p-6 hover:border-alertara-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-alertara-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-file-shield text-alertara-700 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Crime Data Reports</h2>
                    <p class="text-gray-600 text-sm mt-1">
                        Browse every recorded crime by street with a hover map, save named selections
                        (e.g. all resolved cases on a street), and download PDFs — map included — for
                        anyone requesting crime data.
                    </p>
                </div>
                <i class="fas fa-chevron-right text-gray-300 ml-auto self-center"></i>
            </div>
        </a>
    </div>
</div>
@endsection
