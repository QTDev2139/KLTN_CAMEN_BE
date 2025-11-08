<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuthRequest;
use App\Mail\VerifyOtpMail;
use App\Models\User;
use App\Http\Resources\UserResource;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'login',
            'refresh',
            'requestOtpForRegister',
            'verifyOtpForRegister',
            'resendOtpForRegister',
            'requestOtpForForgottenPassword',
            'verifyOtpForForgottenPassword',
            'resendOtpForForgottenPassword',
            'resetPassword'
        ]]);
    }
    public function requestOtpForRegister(StoreAuthRequest $request)
    {
        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        if (User::where('email', $email)->exists()) {
            return response()->json([
                "message" => "Email đã được sử dụng",
            ], 409);
        }

        $otp = random_int(100000, 999999);

        // Lưu tạm thời vào cache, register:otp:$email dùng làm key
        Cache::put("register:otp:$email", [
            'otp_hash' => Hash::make($otp),
            'password_hash' => Hash::make($password),
            'name' => $name,
        ], now()->addMinutes(2)); // Thời gian sống 2'

        Cache::put("register:info:$email", [
            'otp_hash' => Hash::make($otp),
            'password_hash' => Hash::make($password),
            'name' => $name,
        ], now()->addMinutes(20));

        // Gửi mail
        $Mail = Mail::to($email)->queue(new VerifyOtpMail($otp));

        return response()->json(['message' => 'OTP đã được gửi đến email'], 200);
    }
    public function resendOtpForRegister(StoreAuthRequest $request)
    {
        $email = strtolower($request->input('email'));
        $info = Cache::get("register:info:$email");
        if (!$info) {
            return response()->json(['message' => 'Vui lòng đăng ký lại '], 410);
        }

        $otp = random_int(100000, 999999);

        $old = Cache::get("register:otp:$email", []);
        $merged = array_merge($old, [
            'otp_hash' => Hash::make($otp),
        ]);
        Cache::put("register:otp:$email", $merged, now()->addMinutes(2));

        // Gửi mail
        $Mail = Mail::to($email)->queue(new VerifyOtpMail($otp));

        return response()->json(['message' => 'OTP đã được gửi đến email'], 200);
    }

    public function verifyOtpForRegister(StoreAuthRequest $request)
    {
        $email = strtolower($request->input('email'));
        $otp = $request->input('otp');

        $cached = Cache::get("register:otp:$email");
        if (empty($otp)) {
            return response()->json(['message' => 'OTP không được rỗng'], 422);
        }
        if (!$cached) {
            return response()->json(['message' => 'OTP đã hết hạn'], 410);
        }

        if (!Hash::check($otp, $cached['otp_hash'])) {
            return response()->json(['message' => 'OTP không chính xác'], 422);
        }

        $user = User::create([
            'name' => $cached['name'],
            'email' => $email,
            'password' => $cached['password_hash'],
        ]);

        Cache::forget("register:otp:$email");
        Cache::forget("register:info:$email");

        return response()->json(['message' => 'Đăng ký thành công'], 201);
    }

    // --------------------------------------------------------------

    public function requestOtpForForgottenPassword(StoreAuthRequest $request)
    {
        $email = strtolower($request->input('email'));
        if (!User::where('email', $email)->exists()) {
            return response()->json([
                "message" => "Email chưa được đăng ký",
            ], 409);
        }
        $otp = random_int(100000, 999999);
        // Lưu tạm thời vào cache, forget_password:otp:$email dùng làm key
        Cache::put("forget_password:otp:$email", [
            'otp_hash' => Hash::make($otp),
        ], now()->addMinutes(2)); // Thời gian sống 2'

        Cache::put("forget_password:info:$email", [
            'otp_hash' => Hash::make($otp),
        ], now()->addMinutes(20));

        // Gửi mail
        $Mail = Mail::to($email)->queue(new VerifyOtpMail($otp));
        return response()->json(['message' => 'OTP đã được gửi đến email'], 200);
    }
    public function resendOtpForForgottenPassword(StoreAuthRequest $request)
    {
        $email = strtolower($request->input('email'));
        $info = Cache::get("forget_password:info:$email");
        if (!$info) {
            return response()->json(['message' => 'Vui lòng gửi lại email '], 410);
        }
        $otp = random_int(100000, 999999);


        Cache::put("forget_password:otp:$email", ['otp_hash' => Hash::make($otp)], now()->addMinutes(2));

        // Gửi mail
        $Mail = Mail::to($email)->queue(new VerifyOtpMail($otp));
        return response()->json(['message' => 'OTP đã được gửi đến email'], 200);
    }
    public function verifyOtpForForgottenPassword(StoreAuthRequest $request)
    {
        $email = strtolower($request->input('email'));
        $otp = $request->input('otp');

        $cached = Cache::get("forget_password:otp:$email");

        if (!$cached) {
            return response()->json(['message' => 'OTP đã hết hạn'], 410);
        }
        if (empty($otp)) {
            return response()->json(['message' => 'OTP không được rỗng'], 422);
        }
        if (!Hash::check($otp, $cached['otp_hash'])) {
            return response()->json(['message' => 'OTP không chính xác'], 422);
        }

        // Sinh token tạm để đổi mật khẩu
        $resetToken = Str::random(64);
        Cache::put("password:reset_token:$resetToken", [
            'email' => $email,
        ], now()->addMinutes(20)); // token dùng 1 lần, sống 15 phút

        Cache::forget("forget_password:otp:$email");

        return response()->json([
            'message' => 'Xác thực OTP thành công',
            'reset_token' => $resetToken,
        ]);
    }
    public function resetPassword(StoreAuthRequest $request)
    {
        $resetToken = $request->input('reset_token');
        $password = $request->input('password');

        $cached = Cache::get("password:reset_token:$resetToken");

        if (!$cached) {
            return response()->json(['message' => 'Vui lòng thực hiện lại thao tác'], 410);
        }

        $email = $cached['email'];

        $updated = User::where('email', $email)->update([
            'password' => Hash::make($password),
        ]);

        Cache::forget("password:reset_token:$resetToken");

        return response()->json(['message' => 'Cập nhật mật khẩu thành công'], 200);
    }

    // -------------------------------------------------------------

    public function changePassword(StoreAuthRequest $request)
    {
        $password = $request->input('password');
        $newPassword = $request->input('newPassword');
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Không được phép'], 407);
        }
        if ($user->role_id != 4) {
            return response()->json(['message' => 'Chỉ khách hàng mới được đổi mật khẩu'], 403);
        }
        if (!Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Mật khẩu hiện tại không đúng'], 422);
        }
        $user->password = Hash::make($newPassword);
        $user->save();

        return response()->json(['message' => 'Đổi mật khẩu thành công'], 200);
    }
    public function login()
    {
        $credentials = request(['email', 'password']);
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Tài khoản hoặc mật khẩu không chính xác'], 407);
        }

        $refreshToken = $this->createReFreshToken();

        return $this->respondWithToken($token, $refreshToken);
    }

    public function profile()
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Đăng nhập thất bại'], 407);
        }
        $user = User::with('role')->find($user->id);
        return UserResource::make($user);
    }

    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Đăng xuất thành công']);
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
            return response()->json(['error' => 'Refresh Token Invalid'], 407);
        }
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
