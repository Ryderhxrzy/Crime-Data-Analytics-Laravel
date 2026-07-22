<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidence for a San Agustin incident.
 *
 * description and evidence_link are encrypted at rest, matching Evidence.
 */
class SanAgustinEvidence extends Model
{
    use HasFactory;

    protected $table = 'crime_department_san_agustin_evidence';
    protected $primaryKey = 'evidence_id';

    protected $fillable = [
        'incident_id',
        'evidence_type',
        'description',
        'evidence_link',
    ];

    protected static function booted(): void
    {
        static::creating(function (SanAgustinEvidence $evidence) {
            if ($evidence->description) {
                $evidence->description = EncryptionService::encrypt($evidence->description);
            }
            if ($evidence->evidence_link) {
                $evidence->evidence_link = EncryptionService::encrypt($evidence->evidence_link);
            }
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SanAgustinIncident::class, 'incident_id');
    }

    public function getDecryptedDescription(): ?string
    {
        return $this->description ? EncryptionService::decrypt($this->description) : null;
    }

    public function getDecryptedEvidenceLink(): ?string
    {
        return $this->evidence_link ? EncryptionService::decrypt($this->evidence_link) : null;
    }
}
