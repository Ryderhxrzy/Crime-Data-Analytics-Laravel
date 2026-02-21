<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\EncryptionService;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'crime_department_evidence';
    protected $primaryKey = 'evidence_id';

    protected $fillable = [
        'incident_id',
        'evidence_type',
        'description',
        'evidence_link',
    ];

    /**
     * Automatically encrypt sensitive fields on save
     */
    protected static function booted(): void
    {
        static::creating(function (Evidence $evidence) {
            if ($evidence->description) {
                $evidence->description = EncryptionService::encrypt($evidence->description);
            }
            if ($evidence->evidence_link) {
                $evidence->evidence_link = EncryptionService::encrypt($evidence->evidence_link);
            }
        });
    }

    /**
     * Get the incident this evidence belongs to
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(CrimeIncident::class, 'incident_id');
    }

    /**
     * Get decrypted description
     */
    public function getDecryptedDescription(): ?string
    {
        return $this->description ? EncryptionService::decrypt($this->description) : null;
    }

    /**
     * Get decrypted evidence link
     */
    public function getDecryptedEvidenceLink(): ?string
    {
        return $this->evidence_link ? EncryptionService::decrypt($this->evidence_link) : null;
    }
}
