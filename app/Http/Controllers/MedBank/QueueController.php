<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Notification;
use App\Models\MedBank\Queue;
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
        $today = today();
        $next = $appointment->queues
            ->filter(fn($q) => in_array($q->status, ['upcoming', 'missed', null]) && $q->date)
            ->sortBy(fn($q) => $q->date->diffInDays($today))
            ->first();

        return $next ? ['queueNo' => $next->queueNo, 'date' => $next->date->format('Y-m-d')] : null;
    }

    public function missed(Request $request)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $request->month);

        $queues = Queue::with(['appointment.patient', 'appointment.notification'])
            ->where('status', 'missed')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $queues->map(fn($q) => [
                'queueId'     => $q->id,
                'hn'          => $q->appointment->patient->hn,
                'vn'          => $q->appointment->vn,
                'patientName' => $q->appointment->patient->name,
                'department'  => $q->appointment->doctor,
                'clinic'      => $q->appointment->clinic,
                'queueNo'     => $q->queueNo,
                'date'        => $q->date->format('Y-m-d'),
                'queueStatus' => $q->status,
            ]),
        ]);
    }

    public function updateQueueStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:completed'],
        ]);

        $queue = Queue::findOrFail($id);
        $queue->update(['status' => $data['status']]);

        return response()->json(['message' => 'Queue status updated', 'status' => $queue->status]);
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

        if ($data['status'] === 'completed' && $notification->queueNo) {
            Queue::where('appointmentId', $notification->appointmentId)
                ->where('queueNo', $notification->queueNo)
                ->update(['status' => 'completed']);
        }

        return response()->json(['message' => 'Status updated', 'status' => $notification->status]);
    }
}
