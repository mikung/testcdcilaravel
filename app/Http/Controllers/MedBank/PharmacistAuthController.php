<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Pharmacist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PharmacistAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $pharmacist = Pharmacist::where('username', $data['username'])->first();

        if (!$pharmacist || !Hash::check($data['password'], $pharmacist->passwordHash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($pharmacist->status !== 'active') {
            return response()->json(['message' => 'Account not active'], 403);
        }

        $token = $pharmacist->createToken('pharmacist-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'   => $pharmacist->id,
                'name' => $pharmacist->name,
                'role' => $pharmacist->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
