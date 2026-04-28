<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Appointment;
use App\Models\MedBank\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $patients = Patient::where('hn', 'like', $q . '%')
            ->orWhere('name', 'like', '%' . $q . '%')
            ->with(['appointments' => function ($query) {
                $query->with(['queues', 'notification'])
                      ->orderByDesc('visitDate');
            }])
            ->limit(20)
            ->get()
            ->map(function ($patient) {
                return [
                    'hn'           => $patient->hn,
                    'patientName'  => $patient->name,
                    'appointments' => $patient->appointments->map(function ($appt) {
                        $nextQueue = $appt->queues
                            ->filter(fn($q) => in_array($q->status, ['upcoming', null]) && $q->date)
                            ->sortBy('date')
                            ->first();

                        return [
                            'vn'           => $appt->vn,
                            'visitDate'    => $appt->visitDate?->format('Y-m-d'),
                            'clinic'       => $appt->clinic,
                            'doctor'       => $appt->doctor,
                            'notifyStatus' => $appt->notification?->status,
                            'nextQueue'    => $nextQueue ? [
                                'queueNo' => $nextQueue->queueNo,
                                'date'    => $nextQueue->date?->format('Y-m-d'),
                            ] : null,
                        ];
                    }),
                ];
            });

        return response()->json($patients);
    }

    public function show(Request $request, string $vn)
    {
        $hn = $request->query('hn');

        if (!$hn) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $appointment = Appointment::with(['patient', 'queues', 'notification'])
            ->where('vn', $vn)
            ->whereHas('patient', fn($q) => $q->where('hn', $hn))
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Settle stale queues: upcoming but outside notify window (>7 days past) → missed
        $appointment->queues()
            ->where('status', 'upcoming')
            ->whereDate('date', '<', today()->subDays(7))
            ->update(['status' => 'missed']);

        $appointment->load('queues');

        $today = today();
        $nextQueue = $appointment->queues
            ->filter(fn($q) => in_array($q->status, ['upcoming', 'missed', null]) && $q->date)
            ->sortBy(fn($q) => $q->date->diffInDays($today))
            ->first();

        $n = $appointment->notification;

        return response()->json([
            'hn'              => $appointment->patient->hn,
            'vn'              => $appointment->vn,
            'patientName'     => $appointment->patient->name,
            'clinic'          => $appointment->clinic,
            'doctor'          => $appointment->doctor,
            'visitDate'       => $appointment->visitDate?->format('Y-m-d'),
            'notifyStatus'    => $n?->status,
            'deliveryMethod'  => $n?->deliveryMethod,
            'deliveryAddress' => $n?->deliveryAddress,
            'deliveryPhone'   => $n?->deliveryPhone,
            'queues'          => $appointment->queues->map(fn($q) => [
                'queueNo' => $q->queueNo,
                'date'    => $q->date?->format('Y-m-d'),
                'status'  => $q->status,
            ]),
            'nextQueue' => $nextQueue ? [
                'queueNo' => $nextQueue->queueNo,
                'date'    => $nextQueue->date?->format('Y-m-d'),
                'status'  => $nextQueue->status,
            ] : null,
        ]);
    }
}
