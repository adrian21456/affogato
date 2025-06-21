<?php

namespace Zchted\Affogato;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ExpireSanctumTokens
{
    public function handle($request, Closure $next)
    {
        $accessToken = $request->bearerToken();

        if ($accessToken) {
            $token = PersonalAccessToken::findToken($accessToken);

            if ($token) {
                $created = $token->created_at;

                if (empty($token->expires_at)) {
                    $token->expires_at = $created->addMinutes(30);
                    $token->save();
                }

                if (now()->greaterThan($token->expires_at)) {
                    $token->delete(); // optional: remove expired token
                    $response = getResponseObject();
                    $response['status'] = false;
                    $response['error'] = 'Session expired. Please login again.';
                    unset($response['message']);
                    return response()->json($response, 401);
                } else {
                    $token->expires_at = now()->addMinutes(30);
                    $token->save();
                }
            }
        }

        return $next($request);
    }
}
