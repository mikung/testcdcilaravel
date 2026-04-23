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

        DB::connection('mysqlHyggeRBH')->transaction(function () use ($lines, $today, &$stats) {
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
                $visitDate = $this->parseThaiDate(trim($cols[1]));

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

                    $queueDate = $this->parseThaiDate($raw);
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

    private function parseThaiDate(string $value): ?string
    {
        // Matches "21 เม.ย. 2569" or "21 เม.ย. 2569 08:15" — time part ignored
        if (!preg_match('/^(\d{1,2})\s+(\S+)\s+(\d{4})/', $value, $m)) {
            return null;
        }
        $month = self::THAI_MONTHS[$m[2]] ?? null;
        $year  = (int) $m[3] - 543;

        if (!$month || $year < 1900 || $year > 2200) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, (int) $m[1]);
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
