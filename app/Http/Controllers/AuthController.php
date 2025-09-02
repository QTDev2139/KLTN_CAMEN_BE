<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'refresh']]);
    }
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $refreshToken = $this->createReFreshToken();

        return $this->respondWithToken($token, $refreshToken);
    }

    public function profile()
    {
        try {
            return response()->json(Auth::guard('api')->user());
        } catch (JWTException $exception) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    // refresh Trả về token mới với thời gian sống mới
    public function refresh()
    {
        /** @var \Tymon\JWTAuth\JWTGuard $auth */
        $refreshToken = request()->refresh_token;
        try {
            $decoded = JWTAuth::getJWTProvider()->decode($refreshToken);
            // Xử lý cấp lại token mới
            // -> Lấy thông tin user
            $user = User::find($decoded['user_id']);
            if (!$user) {
                return response()->json(['error' => "User not found"], 404);
            }

            /** @var \Tymon\JWTAuth\JWTGuard $auth */
            $auth = auth('api');
            $auth->invalidate(); // Vô hiệu hóa Token cũ còn time khi cấp Token mới

            $token = Auth::guard('api')->login($user);

            $refreshToken = $this->createReFreshToken();

            return $this->respondWithToken($token, $refreshToken);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Refresh Token Invalid'], 401);
        }

        // $auth = auth('api');
        // return $this->respondWithToken($auth->refresh());
    }

    //respondWithToken Chuẩn hóa JSON response khi cấp token
    protected function respondWithToken($token, $refreshToken)
    {
        return response()->json([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60 // Thời gian sống
        ]);
    }

    public function createReFreshToken()
    {
        $data = [
            'user_id' => auth('api')->user()->id,
            'random' => rand() . time(),
            'exp' => time() + config('jwt.refresh_ttl')
        ];

        $refreshToken = JWTAuth::getJWTProvider()->encode($data);

        return $refreshToken;
    }
}
