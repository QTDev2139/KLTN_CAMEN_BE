<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuthRequest;
use App\Mail\VerifyOtpMail;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'refresh', 'requestOtp', 'verifyOtp']]);
    }
    public function requestOtp(StoreAuthRequest $request) {
        $name = $request -> input('name');
        $email = $request -> input('email');
        $password = $request -> input('password');
        if(User::where('email', $email) -> exists()) {
            return response() -> json([
                "message" => "Email đã được sử dụng",
            ], 409);
        }

        $otp = random_int(100000, 999999);

        // Lưu tạm thời vào cache, register:otp:$email dùng làm key
        Cache::put("register:otp:$email", [
            'otp_hash' => Hash::make($otp),
            'password_hash' => Hash::make($password),
            'name' => $name,
        ], now() -> addMinutes(2)); // Thời gian sống 2'

        // Gửi mail
        $Mail = Mail::to($email)->send(new VerifyOtpMail($otp));

        return response() -> json(['message' => 'OTP đã được gửi đến email'], 200);

        
    }
    public function verifyOtp(StoreAuthRequest $request) {
        $email = strtolower($request->input('email'));
        $otp = $request->input('otp');

        $cached = Cache::get("register:otp:$email");

        if(!$cached) {
            return response() -> json(['message' => 'OTP đã hết hạn'], 410);
        }

        if(!Hash::check($otp, $cached['otp_hash'])) {
            return response() -> json(['message' => 'OTP không chính xác'], 422);
        }

        $user = User::create([
            'name' => $cached['name'],
            'email' => $email,
            'password' => $cached['password_hash'],
        ]);

        Cache::forget("register:otp:$email");

        return response()->json(['message' => 'Đăng ký thành công'], 201);

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
