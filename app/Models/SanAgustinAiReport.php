<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A saved Gemini AI output for Barangay San Agustin.
 *
 * report_type says what the row is: 'analysis' (forecast + key findings) or
 * 'recommendation' (a single suggested intervention). Rows saved by the same
 * click share a batch_key.
 */
class SanAgustinAiReport extends Model
{
    protected $table = 'crime_department_san_agustin_ai_reports';

    protected $fillable = [
        'batch_key', 'barangay_name', 'data_source', 'report_type', 'title', 'summary',
        'payload', 'scenario', 'period_days', 'period_start', 'period_end',
        'records_used', 'model', 'saved_by',
    ];

    protected $casts = [
        'payload'      => 'array',
        'scenario'     => 'array',
        'period_start' => 'date',
        'period_end'   => 'date',
        'period_days'  => 'integer',
        'records_used' => 'integer',
    ];

    public function scopeReal($query)
    {
        return $query->where('data_source', 'real');
    }

    public function scopeSimulation($query)
    {
        return $query->where('data_source', 'simulation');
    }

    public function scopeAnalyses($query)
    {
        return $query->where('report_type', 'analysis');
    }

    public function scopeRecommendations($query)
    {
        return $query->where('report_type', 'recommendation');
    }
}
