<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Pharmacist;

class PharmacistAccountController extends Controller
{
    public function index()
    {
        $accounts = Pharmacist::where('status', 'pending')
            ->orderBy('createdAt', 'asc')
            ->get(['id', 'name', 'username', 'createdAt']);

        return response()->json([
            'data' => $accounts->map(fn($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'username'  => $p->username,
                'createdAt' => $p->createdAt?->toDateTimeString(),
            ]),
        ]);
    }

    public function approve(int $id)
    {
        $account = Pharmacist::where('status', 'pending')->findOrFail($id);
        $account->update(['status' => 'active']);

        return response()->json(['message' => 'Account approved']);
    }

    public function reject(int $id)
    {
        $account = Pharmacist::where('status', 'pending')->findOrFail($id);
        $account->update(['status' => 'rejected']);

        return response()->json(['message' => 'Account rejected']);
    }
}
