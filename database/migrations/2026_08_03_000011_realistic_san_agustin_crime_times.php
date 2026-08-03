<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make every San Agustin crime's TIMING realistic for its category:
 *
 *   - incident_time is regenerated from SHARP per-category hour profiles
 *     (hours outside the realistic window get ZERO weight — no more 3 AM
 *     pickpocketing or 10 AM hold-ups):
 *       Theft            → daytime crowds + rush hour (7 AM - 9 PM)
 *       Robbery          → night street crime (6 PM - 3 AM)
 *       Assault          → late-night altercations (5 PM - 3 AM)
 *       Burglary         → early morning while occupants sleep (1-5 AM)
 *                          plus workday hours when homes are empty (9 AM - 4 PM)
 *       Vehicle Theft    → overnight street parking (9 PM - 5 AM)
 *       Domestic Violence→ evenings at home (5 PM - 12 MN)
 *       Fraud            → business hours (9 AM - 6 PM)
 *       Sexual Offense   → night (7 PM - 2 AM)
 *       Homicide         → late night (8 PM - 3 AM)
 *   - incident_date is nudged (at most ±3 days) onto a day-of-week that fits
 *     the category: assaults/homicides/DV cluster on weekends, fraud on
 *     weekdays, burglary on workdays, etc. Dates never move into the future.
 *   - clearance_date is repaired if the nudge would put it before the
 *     incident date.
 *   - Generated descriptions get the reported time woven in ("reported around
 *     9:30 PM") so the narrative matches the row. Only rows carrying the
 *     generator boilerplate are rewritten — hand-typed text is left alone.
 *
 * Deterministic per row id (LCG seeded by crc32), so re-running produces the
 * exact same times and dates — the migration is idempotent by determinism.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_san_agustin_incidents';
    private const ANCHOR_DATE = '2026-08-03';

    /** hour => weight; hours not listed get ZERO weight */
    private const HOUR_PROFILES = [
        'Theft'             => [7 => 2, 8 => 4, 9 => 5, 10 => 7, 11 => 8, 12 => 9, 13 => 8, 14 => 6, 15 => 6, 16 => 7, 17 => 9, 18 => 9, 19 => 7, 20 => 4, 21 => 2],
        'Robbery'           => [18 => 3, 19 => 6, 20 => 8, 21 => 10, 22 => 10, 23 => 9, 0 => 7, 1 => 5, 2 => 4, 3 => 2],
        'Assault'           => [17 => 2, 18 => 3, 19 => 4, 20 => 6, 21 => 8, 22 => 10, 23 => 10, 0 => 8, 1 => 6, 2 => 4, 3 => 2],
        'Burglary'          => [1 => 6, 2 => 8, 3 => 9, 4 => 7, 5 => 4, 9 => 4, 10 => 6, 11 => 6, 12 => 4, 13 => 4, 14 => 6, 15 => 5, 16 => 3],
        'Vehicle Theft'     => [21 => 4, 22 => 7, 23 => 9, 0 => 10, 1 => 10, 2 => 9, 3 => 7, 4 => 5, 5 => 2],
        'Domestic Violence' => [17 => 2, 18 => 4, 19 => 6, 20 => 8, 21 => 10, 22 => 9, 23 => 6, 0 => 3],
        'Fraud'             => [9 => 6, 10 => 8, 11 => 9, 12 => 6, 13 => 7, 14 => 9, 15 => 8, 16 => 7, 17 => 5, 18 => 3],
        'Sexual Offense'    => [19 => 3, 20 => 5, 21 => 7, 22 => 8, 23 => 8, 0 => 6, 1 => 4, 2 => 3],
        'Homicide'          => [20 => 3, 21 => 6, 22 => 8, 23 => 10, 0 => 9, 1 => 7, 2 => 5, 3 => 3],
    ];

    /** Sun..Sat weights per category — which days each crime clusters on */
    private const DOW_PROFILES = [
        'Theft'             => [0.9, 1.0, 1.0, 1.0, 1.0, 1.2, 1.1],
        'Robbery'           => [1.0, 0.8, 0.8, 0.9, 1.0, 1.3, 1.3],
        'Assault'           => [1.2, 0.6, 0.6, 0.7, 0.8, 1.4, 1.5],
        'Burglary'          => [0.8, 1.2, 1.2, 1.2, 1.2, 1.0, 0.8],
        'Vehicle Theft'     => [1.0, 1.0, 1.0, 1.0, 1.0, 1.1, 1.1],
        'Domestic Violence' => [1.3, 0.7, 0.7, 0.7, 0.8, 1.2, 1.4],
        'Fraud'             => [0.3, 1.3, 1.3, 1.3, 1.3, 1.2, 0.5],
        'Sexual Offense'    => [1.0, 0.8, 0.8, 0.9, 1.0, 1.3, 1.3],
        'Homicide'          => [1.2, 0.6, 0.6, 0.6, 0.8, 1.4, 1.5],
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $anchor = new \DateTimeImmutable(self::ANCHOR_DATE);
        $now = now();

        DB::table(self::TABLE)
            ->orderBy('id')
            ->select(['id', 'category_name', 'incident_date', 'incident_time',
                      'clearance_date', 'address_details', 'modus_operandi', 'incident_description'])
            ->chunk(100, function ($rows) use ($anchor, $now) {
                foreach ($rows as $row) {
                    $seed = crc32('sa-realistic-times/' . $row->id);
                    $category = $this->profileKey((string) $row->category_name);

                    $time = $this->sampleTime($category, $seed);
                    $date = $this->alignDate((string) $row->incident_date, $category, $seed, $anchor);

                    $update = [
                        'incident_time' => $time,
                        'incident_date' => $date->format('Y-m-d'),
                        'updated_at'    => $now,
                    ];

                    // Clearance can never precede the (possibly nudged) incident
                    if ($row->clearance_date && $row->clearance_date < $date->format('Y-m-d')) {
                        $update['clearance_date'] = $date
                            ->modify('+' . (3 + $this->nextInt($seed, 21)) . ' days')
                            ->format('Y-m-d');
                    }

                    // Weave the reported time into generator-boilerplate text only
                    $desc = (string) $row->incident_description;
                    $modus = trim((string) $row->modus_operandi);
                    $street = trim(explode(',', (string) $row->address_details)[0] ?? '');
                    if ($modus !== '' && $street !== ''
                        && ($desc === '' || str_contains($desc, 'Responding unit dispatched'))) {
                        $update['incident_description'] = $modus . ' along ' . $street
                            . ', Barangay San Agustin, reported around ' . $this->hour12($time)
                            . '. Responding unit dispatched; case logged for investigation.';
                    }

                    DB::table(self::TABLE)->where('id', $row->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        // Original times/dates are not recoverable; nothing to undo.
    }

    /** Map free-text category names onto a profile key */
    private function profileKey(string $category): string
    {
        $exact = trim($category);
        if (isset(self::HOUR_PROFILES[$exact])) {
            return $exact;
        }

        $c = mb_strtolower($exact);
        foreach ([
            'carnap' => 'Vehicle Theft', 'vehicle' => 'Vehicle Theft',
            'robbery' => 'Robbery', 'holdap' => 'Robbery',
            'burglary' => 'Burglary', 'akyat' => 'Burglary',
            'assault' => 'Assault', 'injur' => 'Assault',
            'homicide' => 'Homicide', 'murder' => 'Homicide',
            'sexual' => 'Sexual Offense', 'rape' => 'Sexual Offense',
            'fraud' => 'Fraud', 'estafa' => 'Fraud', 'scam' => 'Fraud',
            'domestic' => 'Domestic Violence', 'vawc' => 'Domestic Violence',
            'theft' => 'Theft',
        ] as $needle => $key) {
            if (str_contains($c, $needle)) {
                return $key;
            }
        }

        return 'Theft';
    }

    /** Weighted hour from the category profile + a random minute */
    private function sampleTime(string $category, int &$seed): string
    {
        $profile = self::HOUR_PROFILES[$category] ?? self::HOUR_PROFILES['Theft'];

        $total = array_sum($profile);
        $roll = $this->nextFloat($seed) * $total;
        $hour = array_key_first($profile);
        foreach ($profile as $h => $w) {
            $roll -= $w;
            if ($roll <= 0) {
                $hour = $h;
                break;
            }
        }

        return sprintf('%02d:%02d:00', $hour, $this->nextInt($seed, 60));
    }

    /**
     * Nudge the date (±3 days at most) onto a category-appropriate day of the
     * week. The target day depends only on the seed, so a re-run computes the
     * same target, finds the date already on it, and shifts by zero.
     */
    private function alignDate(string $current, string $category, int &$seed, \DateTimeImmutable $anchor): \DateTimeImmutable
    {
        $weights = self::DOW_PROFILES[$category] ?? array_fill(0, 7, 1.0);

        $total = array_sum($weights);
        $roll = $this->nextFloat($seed) * $total;
        $target = 6;
        foreach ($weights as $dow => $w) {
            $roll -= $w;
            if ($roll <= 0) {
                $target = $dow;
                break;
            }
        }

        try {
            $date = new \DateTimeImmutable($current);
        } catch (\Exception $e) {
            return $anchor;
        }

        $delta = $target - (int) $date->format('w');
        if ($delta > 3) {
            $delta -= 7;
        } elseif ($delta < -3) {
            $delta += 7;
        }
        $date = $date->modify(($delta >= 0 ? '+' : '') . $delta . ' days');

        // Never into the future (relative to the dataset anchor)
        if ($date > $anchor) {
            $date = $date->modify('-7 days');
        }

        return $date;
    }

    /** "21:30:00" → "9:30 PM" */
    private function hour12(string $time): string
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', $time), 2, 0));
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $hh = $h % 12 ?: 12;

        return $hh . ':' . sprintf('%02d', $m) . ' ' . $ampm;
    }

    // ------------------------------------------------------------------- rng

    private function nextFloat(int &$seed): float
    {
        $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;

        return $seed / 0x7FFFFFFF;
    }

    private function nextInt(int &$seed, int $bound): int
    {
        return $bound <= 0 ? 0 : (int) floor($this->nextFloat($seed) * $bound) % $bound;
    }
};
