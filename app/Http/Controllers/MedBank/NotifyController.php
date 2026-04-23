<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Appointment;
use App\Models\MedBank\Notification;
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

        $appointment = Appointment::where('vn', $data['vn'])->first();

        if (!$appointment) {
            return response()->json(['message' => 'VN not found'], 404);
        }

        if ($appointment->notification) {
            return response()->json(['message' => 'Already notified'], 409);
        }

        $notification = Notification::create([
            'appointmentId'  => $appointment->id,
            'status'         => 'pending',
            'deliveryMethod' => $data['delivery_method'],
            'deliveryAddress' => $data['address'] ?? null,
            'deliveryPhone'  => $data['phone'] ?? null,
            'notifiedAt'     => now(),
        ]);

        return response()->json(['message' => 'Notified successfully', 'status' => $notification->status], 201);
    }
}
