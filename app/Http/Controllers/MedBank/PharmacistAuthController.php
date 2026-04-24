<?php

namespace App\Http\Controllers\MedBank;

use App\Http\Controllers\Controller;
use App\Models\MedBank\Pharmacist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PharmacistAuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100', 'unique:mysqlHyggeRBH.medbank_pharmacist,username'],
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(8)],
            'role'     => ['sometimes', 'string', 'in:pharmacist,staff'],
        ]);

        Pharmacist::create([
            'username'     => $data['username'],
            'name'         => $data['name'],
            'passwordHash' => Hash::make($data['password']),
            'role'         => $data['role'] ?? 'pharmacist',
            'status'       => 'pending',
        ]);

        return response()->json(['message' => 'Registration submitted, awaiting approval'], 201);
    }

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
