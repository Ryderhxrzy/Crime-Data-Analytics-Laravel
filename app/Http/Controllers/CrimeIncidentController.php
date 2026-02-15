<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;

class CrimeIncidentController extends Controller
{
    public function index()
    {
        $crimes = CrimeIncident::with('category', 'barangay')->get();

        return view('crimes.index', compact('crimes'));
    }
}
