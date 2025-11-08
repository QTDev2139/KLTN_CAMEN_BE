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
        $users = User::where('role_id', '!=', 4)->get();
        return UserResource::collection($users);
    }

    /**
     * Show the form for creating a new resource.
     */
    // public function create() // không sài trong api
    // {
    //     //
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $role_id = $request->input('role_id');
        if(User::where('email', $email)->exists()) {
            return response()->json([
                "message" => "Email đã được sử dụngg",
            ], 409);
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $role_id,
        ]);
        return response()->json(['message' => 'Đăng ký tài khoản thành công'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $User)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    // public function edit(User $User) // không sài trong api
    // {
    //     //
    // }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $User = User::findOrFail($id);

        $User->update([
            'password' => Hash::make($request->get('password')),
        ]);
        return response() -> json(["message" => "Mật khẩu được cập nhật thành công"]);
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
