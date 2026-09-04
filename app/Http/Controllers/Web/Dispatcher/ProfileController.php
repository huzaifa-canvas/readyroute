<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return view('content.dispatcher.profile.index', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone'  => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $user->name  = $request->name;
        $user->email = $request->email;
        
        if ($request->has('phone')) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone_number')) {
                $user->phone_number = $request->phone;
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
                $user->phone = $request->phone;
            }
        }

        // Handle Avatar Upload
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/img/avatars'), $filename);
            $user->avatar = 'assets/img/avatars/' . $filename;
        }

        $user->save();

        return redirect()->back()->with('success_profile', 'Profile details updated successfully!');
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ], [
            'new_password.confirmed' => 'New password confirmation does not match.',
            'new_password.min'       => 'New password must be at least 8 characters.',
        ]);

        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password does not match our records.'])->withInput();
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return redirect()->back()->with('success_password', 'Password updated successfully!');
    }
}
