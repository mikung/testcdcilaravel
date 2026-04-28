<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Appointment;
use App\Models\MedBank\Notification;
use App\Models\MedBank\Queue;
use Illuminate\Http\Request;

class NotifyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'vn'              => ['required', 'string', 'regex:/^\d{12}$/'],
            'delivery_method' => ['required', 'in:pickup,delivery'],
            'address'         => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'phone'           => ['required_if:delivery_method,delivery', 'nullable', 'regex:/^0\d{8,9}$/'],
        ]);

        $appointment = Appointment::with('queues')->where('vn', $data['vn'])->first();

        if (!$appointment) {
            return response()->json(['message' => 'VN not found'], 404);
        }

        $nextQueue = $appointment->queues
            ->filter(fn($q) => in_array($q->status, ['upcoming', null]) && $q->date)
            ->sortBy('date')
            ->first();

        if (!$nextQueue) {
            return response()->json(['message' => 'No upcoming queue'], 422);
        }

        // Mark earlier upcoming queues as missed
        Queue::where('appointmentId', $appointment->id)
            ->where('queueNo', '<', $nextQueue->queueNo)
            ->where('status', 'upcoming')
            ->update(['status' => 'missed']);

        $notifyData = [
            'queueNo'        => $nextQueue->queueNo,
            'status'         => 'pending',
            'deliveryMethod' => $data['delivery_method'],
            'deliveryAddress' => $data['address'] ?? null,
            'deliveryPhone'  => $data['phone'] ?? null,
            'notifiedAt'     => now(),
        ];

        $notification = $appointment->notification
            ? tap($appointment->notification)->update($notifyData)
            : Notification::create(['appointmentId' => $appointment->id] + $notifyData);

        return response()->json(['message' => 'Notified successfully', 'status' => $notification->status], 201);
    }
}
