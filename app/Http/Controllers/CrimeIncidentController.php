<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;

require_once app_path('auth-include.php');

class CrimeIncidentController extends Controller
{
    public function index()
    {
        $crimes = CrimeIncident::with('category', 'barangay')->get();

        return view('crimes.index', compact('crimes'));
    }
}
