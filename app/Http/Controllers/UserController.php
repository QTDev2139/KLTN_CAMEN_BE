<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('role')
            ->select('id', 'email', 'name', 'role_id', 'status')
            ->where('role_id', '!=', 4)
            ->orderByDesc('status')
            ->get();

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $name = $request->input('name');
        $email = $request->input('email');
        $role_id = $request->input('role_id');
        if(User::where('email', $email)->exists()) {
            return response()->json([
                "message" => "Email đã được sử dụng",
            ], 409);
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make("123456"),
            'role_id' => $role_id,
        ]);
        return response()->json(['message' => 'Đăng ký tài khoản thành công'], 201);
    }

    /**
     * Display the specified resource.
     */
    // Show danh sách nhân viên 
    public function getPersonnel($id)
    {
        $users = User::where('role_id', $id)->get();
        return UserResource::collection($users);
    }

    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $User = User::findOrFail($id);

        $User->update([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'role_id' => $request->get('role_id'),
            'status' => $request->get('status'),
        ]);
        return response() -> json(["message" => "Thông tin tài khoản được cập nhật thành công"]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $User = User::findOrFail($id);

        $User->delete();
        
        return response() -> json(["message" => "Xóa tài khoản nhân viên thành công"]);
        
    }

    public function updateRole(Request $request, $id) {
        $User = User::findOrFail($id);

        $User->update([
            'role_id' => $request->get('role_id'),
        ]);
        return response() -> json(["message" => "Quyền nhân viên được cập nhật thành công"]);
    }
}
