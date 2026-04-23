<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Notification;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::with(['appointment.patient', 'appointment.queues'])
            ->orderByDesc('notifiedAt');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('appointment.patient', function ($q) use ($search) {
                $q->where('hn', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $notifications = $query->get();

        return response()->json([
            'data' => $notifications->map(fn($n) => [
                'id'              => $n->id,
                'hn'              => $n->appointment->patient->hn,
                'vn'              => $n->appointment->vn,
                'patientName'     => $n->appointment->patient->name,
                'notifyStatus'    => $n->status,
                'deliveryMethod'  => $n->deliveryMethod,
                'deliveryAddress' => $n->deliveryAddress,
                'deliveryPhone'   => $n->deliveryPhone,
                'notifiedAt'      => $n->notifiedAt?->toDateTimeString(),
                'processedAt'     => $n->processedAt?->toDateTimeString(),
                'dispatchedAt'    => $n->dispatchedAt?->toDateTimeString(),
                'completedAt'     => $n->completedAt?->toDateTimeString(),
                'nextQueue'       => $this->resolveNextQueue($n->appointment),
            ]),
        ]);
    }

    private function resolveNextQueue($appointment): ?array
    {
        $next = $appointment->queues
            ->filter(fn($q) => in_array($q->status, ['upcoming', null]) && $q->date)
            ->sortBy('date')
            ->first();

        return $next ? ['queueNo' => $next->queueNo, 'date' => $next->date->format('Y-m-d')] : null;
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:processing,dispatched,completed'],
        ]);

        $notification = Notification::findOrFail($id);

        $transitions = [
            'pending'    => ['processing'],
            'processing' => ['dispatched', 'completed'],
            'dispatched' => ['completed'],
        ];

        if (!in_array($data['status'], $transitions[$notification->status] ?? [])) {
            return response()->json([
                'message' => "Cannot transition from {$notification->status} to {$data['status']}",
            ], 422);
        }

        $timestampField = [
            'processing' => 'processedAt',
            'dispatched' => 'dispatchedAt',
            'completed'  => 'completedAt',
        ][$data['status']];

        $notification->update([
            'status'        => $data['status'],
            $timestampField => now(),
            'pharmacistId'  => $request->user()->id,
        ]);

        return response()->json(['message' => 'Status updated', 'status' => $notification->status]);
    }
}
