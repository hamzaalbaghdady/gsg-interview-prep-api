<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Models\User;
use App\Services\CreateUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate data
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|max:32',
        ]);

        // Check credentials
        if (!Auth::attempt($validated)) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Your credentials are incorrect.',
            ], 401);
        }
        // get User
        $user = User::where('email', $validated['email'])->firstOrFail();
        $token = $user->createToken('token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User logged in successfully.',
            'user' => $user,
            'token' => $token
        ], 200);
    }
    public function register(StoreUserRequest $request, CreateUserService $service)
    {
        // Validate and create User
        $user = $service->execute($request->validated());
        // Generate User token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }
    public function logout(Request $request)
    {
        // delete curren user token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out.'
        ], 200);
    }
}
