<?php

namespace Zchted\Affogato;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
    /**
     * Handle login request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // Attempt to log the user in using the credentials
        if (Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            // Fetch the authenticated user
            $user = Auth::user();

            // Generate a token for the authenticated user
            $token = $user->createToken('YourAppName')->plainTextToken;

            // Return the user and token as a response

            $response = getResponseObject(['message' => 'Login successful']);
            $response["status"] = true;
            $response["token"] = $token;
            $response["user"] = $user;
            unset($response["error"]);
            unset($response["request"]);
            return response()->json($response);
        }

        $response = getResponseObject(['error' => 'Unauthorized']);
        $response["status"] = false;
        unset($response["error"]);
        unset($response["request"]);
        return response()->json($response, 401);
    }

    /**
     * Handle logout request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke the current token of the user
        $request->user()->currentAccessToken()->delete();

        $response = getResponseObject(['message' => 'Logout successful']);
        return response()->json($response);
    }
}
