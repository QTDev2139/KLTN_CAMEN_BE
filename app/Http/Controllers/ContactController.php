<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Contact;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $roleId = $user->role_id;

        if ($roleId == 1 || $roleId == 2) {
            $contacts = Contact::with('user')->get();
            return response()->json($contacts);
        } else if ($roleId == 3) {
            $contacts = Contact::with('user')->where('user_id', $user->id)->get();
            return response()->json($contacts);
        } else {
            return response()->json([], 200);
        }
    }

    public function updateSupportContact(Request $request, $id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json(['message' => 'Liên hệ không tồn tại'], 404);
        }

        $contact->user_id = $request->input('user_id');
        $contact->save();

        return response()->json(['message' => 'Cập nhật phản hồi hỗ trợ thành công'], 200);
    }

    public function updateStatusContact(Request $request, $id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json(['message' => 'Liên hệ không tồn tại'], 404);
        }
        $contact->note = $request->input('note') ?? '';
        $contact->status = true;
        $contact->save();

        return response()->json(['message' => 'Cập nhật trạng thái liên hệ thành công'], 200);
    }

    public function submit(Request $request)
    {
        // Chuẩn hoá tên field gửi từ FE
        $request->merge([
            'recaptcha_token' => $request->input('recaptcha_token')
                ?? $request->input('recaptchaToken')
        ]);

        // Validate
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'title'           => 'required|string|max:255',
            'content'         => 'nullable|string',
            'recaptcha_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // VERIFY RECAPTCHA ----------------------------------------------------
        $secret = env('RECAPTCHA_SECRET');

        if (empty($secret)) {
            Log::warning('RECAPTCHA_SECRET not set');
            return response()->json(['message' => 'reCAPTCHA not configured'], 500);
        }

        try {
            $resp = Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret'   => $secret,
                    'response' => $request->recaptcha_token,
                    'remoteip' => $request->ip(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA request failed: ' . $e->getMessage());
            return response()->json(['message' => 'reCAPTCHA request failed'], 500);
        }

        $body = $resp->json();

        if (!($body['success'] ?? false)) {
            Log::info('reCAPTCHA failed', ['resp' => $body]);
            return response()->json([
                'message' => 'reCAPTCHA verification failed',
                'errors'  => $body,
            ], 422);
        }

        try {
            Contact::create([
                'name'            => $request->name,
                'email'           => $request->email,
                'phone'           => $request->phone,
                'title'           => $request->title,
                'content'         => $request->content ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('Contact insert failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Gửi liên hệ thành công'], 200);
    }

    public function destroy($id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json(['message' => 'Liên hệ không tồn tại'], 404);
        }

        $contact->delete();

        return response()->json(['message' => 'Xoá liên hệ thành công'], 200);
    }
}
