<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\EncryptionService;

class PersonsInvolved extends Model
{
    use HasFactory;

    protected $table = 'crime_department_persons_involved';
    protected $primaryKey = 'person_id';

    protected $fillable = [
        'incident_id',
        'person_type',
        'first_name',
        'middle_name',
        'last_name',
        'contact_number',
        'other_info',
    ];

    /**
     * Automatically encrypt sensitive fields on save
     */
    protected static function booted(): void
    {
        static::creating(function (PersonsInvolved $person) {
            if ($person->first_name) {
                $person->first_name = EncryptionService::encrypt($person->first_name);
            }
            if ($person->middle_name) {
                $person->middle_name = EncryptionService::encrypt($person->middle_name);
            }
            if ($person->last_name) {
                $person->last_name = EncryptionService::encrypt($person->last_name);
            }
            if ($person->contact_number) {
                $person->contact_number = EncryptionService::encrypt($person->contact_number);
            }
            if ($person->other_info) {
                $person->other_info = EncryptionService::encrypt($person->other_info);
            }
        });
    }

    /**
     * Get the incident that this person is involved in
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(CrimeIncident::class, 'incident_id');
    }

    /**
     * Get decrypted first name
     */
    public function getDecryptedFirstName(): ?string
    {
        return $this->first_name ? EncryptionService::decrypt($this->first_name) : null;
    }

    /**
     * Get decrypted middle name
     */
    public function getDecryptedMiddleName(): ?string
    {
        return $this->middle_name ? EncryptionService::decrypt($this->middle_name) : null;
    }

    /**
     * Get decrypted last name
     */
    public function getDecryptedLastName(): ?string
    {
        return $this->last_name ? EncryptionService::decrypt($this->last_name) : null;
    }

    /**
     * Get decrypted contact number
     */
    public function getDecryptedContactNumber(): ?string
    {
        return $this->contact_number ? EncryptionService::decrypt($this->contact_number) : null;
    }

    /**
     * Get decrypted other info
     */
    public function getDecryptedOtherInfo(): ?string
    {
        return $this->other_info ? EncryptionService::decrypt($this->other_info) : null;
    }

    /**
     * Get full name (decrypted)
     */
    public function getFullName(): string
    {
        $first = $this->getDecryptedFirstName() ?? '';
        $middle = $this->getDecryptedMiddleName() ?? '';
        $last = $this->getDecryptedLastName() ?? '';

        return trim(implode(' ', [$first, $middle, $last]));
    }
}
