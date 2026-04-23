<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Appointment;

class PatientController extends Controller
{
    public function show(string $vn)
    {
        $appointment = Appointment::with(['patient', 'queues', 'notification'])
            ->where('vn', $vn)
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $nextQueue = $appointment->queues
            ->filter(fn($q) => in_array($q->status, ['upcoming', null]) && $q->date)
            ->sortBy('date')
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
                'date'    => $q->date?->format('d M Y'),
                'status'  => $q->status,
            ]),
            'nextQueue' => $nextQueue ? [
                'queueNo' => $nextQueue->queueNo,
                'date'    => $nextQueue->date?->format('d M Y'),
                'status'  => $nextQueue->status,
            ] : null,
        ]);
    }
}
