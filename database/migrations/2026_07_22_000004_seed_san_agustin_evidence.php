<?php

use App\Services\EncryptionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds evidence for the San Agustin incidents.
 *
 * crime_department_evidence holds no rows, so there is nothing to copy — this
 * generates evidence appropriate to each incident's category instead. Like the
 * incident relocation, it is seeded from the incident id and produces the same
 * result on every run.
 *
 * description and evidence_link are encrypted with EncryptionService so the rows
 * behave exactly like evidence created through the app, whose model encrypts on
 * create and throws when decrypting anything stored in plain text.
 */
return new class extends Migration
{
    /** category => [[evidence_type, description], ...] */
    private const EVIDENCE_BY_CATEGORY = [
        'Theft'              => [['Video', 'CCTV footage covering the area at the time of the theft'], ['Testimonial', 'Statement from the complainant'], ['Photo', 'Photograph of the point of entry']],
        'Robbery'            => [['Video', 'CCTV footage of the suspects entering and leaving'], ['Testimonial', 'Statement from the store attendant on duty'], ['Fingerprint', 'Latent prints lifted from the counter']],
        'Assault'            => [['Photo', 'Photographs of the injuries sustained'], ['Testimonial', 'Statement from a bystander'], ['Document', 'Medico-legal certificate']],
        'Burglary'           => [['Photo', 'Photographs of the forced entry point'], ['Fingerprint', 'Latent prints lifted from the window frame'], ['Video', 'Footage from a neighbouring CCTV camera']],
        'Vehicle Theft'      => [['Video', 'CCTV footage of the vehicle being taken'], ['Document', 'Copy of the OR/CR of the stolen vehicle'], ['Photo', 'Photograph of the parking area']],
        'Drug-Related'       => [['Photo', 'Photographs of the seized items'], ['Document', 'Inventory receipt of seized items'], ['Video', 'Body-worn camera footage of the operation']],
        'Homicide'           => [['Weapon', 'Recovered weapon submitted for ballistic examination'], ['Biological Sample', 'Blood sample collected at the scene'], ['Photo', 'Crime scene photographs']],
        'Sexual Offense'     => [['Document', 'Medico-legal report'], ['Testimonial', 'Sworn statement of the complainant'], ['Clothing', 'Garments turned over for examination']],
        'Fraud'              => [['Document', 'Copies of the falsified documents'], ['Digital File', 'Screenshots of the online transactions'], ['Testimonial', 'Statement from the complainant']],
        'Domestic Violence'  => [['Photo', 'Photographs of the injuries sustained'], ['Audio', 'Recording of the barangay blotter interview'], ['Document', 'Barangay protection order']],
        'Vehicular Accident' => [['Photo', 'Photographs of the vehicle damage'], ['Video', 'Dashcam footage of the collision'], ['Document', 'Traffic investigation report']],
        'Fire Incident'      => [['Photo', 'Photographs of the fire damage'], ['Document', 'Fire investigation report'], ['Video', 'Footage taken during the response']],
    ];

    /** Used when the category has no specific list */
    private const DEFAULT_EVIDENCE = [
        ['Photo', 'Photographs taken at the scene'],
        ['Testimonial', 'Statement from the reporting party'],
        ['Document', 'Barangay blotter entry'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('crime_department_san_agustin_evidence')
            || !Schema::hasTable('crime_department_san_agustin_incidents')) {
            return;
        }

        $incidents = DB::table('crime_department_san_agustin_incidents')
            ->select('id', 'incident_code', 'category_name', 'incident_date')
            ->orderBy('id')
            ->get();

        if ($incidents->isEmpty()) {
            return;
        }

        // Keep the migration re-runnable
        $alreadySeeded = DB::table('crime_department_san_agustin_evidence')
            ->distinct()->pluck('incident_id')->flip();

        $rows = [];
        $now = now();

        foreach ($incidents as $incident) {
            if ($alreadySeeded->has($incident->id)) {
                continue;
            }

            $pool = self::EVIDENCE_BY_CATEGORY[$incident->category_name] ?? self::DEFAULT_EVIDENCE;

            $seed = (int) $incident->id;
            $count = 1 + $this->nextInt($seed, count($pool));   // 1..count(pool)

            for ($i = 0; $i < $count; $i++) {
                [$type, $description] = $pool[$i];

                $rows[] = [
                    'incident_id'   => $incident->id,
                    'evidence_type' => $type,
                    'description'   => EncryptionService::encrypt($description),
                    'evidence_link' => EncryptionService::encrypt(
                        'evidence/san-agustin/' . $incident->incident_code . '-' . ($i + 1) . '.' . $this->extensionFor($type)
                    ),
                    'created_at'    => $incident->incident_date ?? $now,
                    'updated_at'    => $incident->incident_date ?? $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('crime_department_san_agustin_evidence')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crime_department_san_agustin_evidence')) {
            DB::table('crime_department_san_agustin_evidence')->delete();
        }
    }

    private function extensionFor(string $type): string
    {
        return match ($type) {
            'Video'                       => 'mp4',
            'Audio'                       => 'mp3',
            'Document', 'Testimonial'     => 'pdf',
            'Digital File'                => 'zip',
            default                       => 'jpg',
        };
    }

    /** Same small LCG as the incident migration, so results are reproducible */
    private function nextInt(int &$seed, int $bound): int
    {
        $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
        return (int) floor(($seed / 0x7FFFFFFF) * $bound) % $bound;
    }
};
