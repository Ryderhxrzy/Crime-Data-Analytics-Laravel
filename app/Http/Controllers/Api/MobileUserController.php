<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class MobileUserController extends Controller
{
    /**
     * Register a new mobile user
     *
     * POST /api/mobile-users/register
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|unique:mobile_user,email',
                'password' => 'required|string|min:6',
            ]);

            $user = MobileUser::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
            ]);

            // Generate JWT token
            $token = JWTAuth::fromUser($user);
            $expires_in = auth('api')->factory()->getTTL() * 60; // in seconds

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'token' => $token,
                    'expires_in' => $expires_in,
                    'created_at' => $user->created_at,
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login mobile user
     *
     * POST /api/mobile-users/login
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            // Find user by email
            $user = MobileUser::where('email', $validated['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found'
                ], 401);
            }

            // Check password
            if (!Hash::check($validated['password'], $user->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password'
                ], 401);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);
            $expires_in = auth('api')->factory()->getTTL() * 60; // in seconds

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'token' => $token,
                    'expires_in' => $expires_in,
                    'created_at' => $user->created_at,
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
