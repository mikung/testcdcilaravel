<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Appointment;
use App\Models\MedBank\Patient;
use App\Models\MedBank\Queue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    private const THAI_MONTHS = [
        'ม.ค.' => 1,  'ก.พ.' => 2,  'มี.ค.' => 3,  'เม.ย.' => 4,
        'พ.ค.' => 5,  'มิ.ย.' => 6, 'ก.ค.' => 7,   'ส.ค.' => 8,
        'ก.ย.' => 9,  'ต.ค.' => 10, 'พ.ย.' => 11,  'ธ.ค.' => 12,
    ];

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        // Strip UTF-8 BOM (Excel adds this)
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = array_values(array_filter(
            explode("\n", str_replace("\r\n", "\n", $content)),
            fn($l) => trim($l) !== ''
        ));

        if (count($lines) < 2) {
            return response()->json(['message' => 'File has no data rows'], 422);
        }

        // Drop header row
        array_shift($lines);

        $stats  = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];
        $today  = now()->toDateString();

        DB::connection('mysql_projectrbh')->transaction(function () use ($lines, $today, &$stats) {
            foreach ($lines as $idx => $line) {
                $rowNum = $idx + 2; // 1-based + header
                $cols   = str_getcsv(trim($line));

                if (count($cols) < 7) {
                    $stats['errors'][] = "Row {$rowNum}: not enough columns";
                    continue;
                }

                $hn        = $this->cleanExcel($cols[2]);
                $vn        = $this->cleanExcel($cols[3]);
                $name      = trim($cols[4]);
                $doctor    = trim($cols[5]);
                $clinic    = trim($cols[6]);
                $visitDate = $this->parseDate(trim($cols[1]));

                if (!$visitDate) {
                    $stats['errors'][] = "Row {$rowNum}: cannot parse visitDate '{$cols[1]}'";
                    continue;
                }

                $patient = Patient::updateOrCreate(
                    ['hn' => $hn],
                    ['name' => $name]
                );

                $isNew = !Appointment::where('vn', $vn)->exists();

                $appointment = Appointment::updateOrCreate(
                    ['vn' => $vn],
                    [
                        'patientId' => $patient->id,
                        'visitDate' => $visitDate,
                        'clinic'    => $clinic,
                        'doctor'    => $doctor,
                    ]
                );

                // Rebuild queues from CSV columns 7-16 (คิวครั้งที่ 1-10)
                Queue::where('appointmentId', $appointment->id)->delete();

                for ($i = 7; $i < min(count($cols), 17); $i++) {
                    $raw = trim($cols[$i]);
                    if ($raw === '' || $raw === '-') continue;

                    $queueDate = $this->parseDate($raw);
                    if (!$queueDate) continue;

                    Queue::create([
                        'appointmentId' => $appointment->id,
                        'queueNo'       => $i - 6,
                        'date'          => $queueDate,
                        'status'        => $queueDate < $today ? 'completed' : 'upcoming',
                    ]);
                }

                $stats['total']++;
                $isNew ? $stats['created']++ : $stats['updated']++;
            }
        });

        return response()->json($stats);
    }

    private function parseDate(string $value): ?string
    {
        // Strip trailing time e.g. "09:00" or "09:00:00"
        $value = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', trim($value));

        // "28 เม.ย. 2569" — Thai abbreviated month, BE or CE year
        if (preg_match('/^(\d{1,2})\s+(\S+)\s+(\d{4})$/', $value, $m)) {
            $month = self::THAI_MONTHS[$m[2]] ?? null;
            if (!$month) return null;
            $year = (int) $m[3];
            if ($year > 2400) $year -= 543;
            return $this->makeDate($year, $month, (int) $m[1]);
        }

        // "28/04/2569" or "28-04-2569" — DD/MM/YYYY, BE or CE year
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
            $year = (int) $m[3];
            if ($year > 2400) $year -= 543;
            return $this->makeDate($year, (int) $m[2], (int) $m[1]);
        }

        // "2026-04-28" or "2569-04-28" — YYYY-MM-DD, CE or BE year
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $m)) {
            $year = (int) $m[1];
            if ($year > 2400) $year -= 543;
            return $this->makeDate($year, (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    private function makeDate(int $year, int $month, int $day): ?string
    {
        if ($year < 1900 || $year > 2200 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function cleanExcel(string $value): string
    {
        $value = trim($value);
        // Excel wraps text cells as ="value"
        if (str_starts_with($value, '="') && str_ends_with($value, '"')) {
            return substr($value, 2, -1);
        }
        return $value;
    }
}
