<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Platform Security.
 *
 * This route previously pointed at the dispatcher's own profile controller,
 * which rendered a company-scoped page for a platform-level screen. It now has
 * its own controller showing what an operator actually needs: who holds
 * elevated access, account standing, and the configuration warnings worth acting on.
 */
class SecurityController extends Controller
{
    public function index()
    {
        $admins = User::where('role', 'admin')->orderBy('name')->get();

        $suspended = User::withTrashed()
            ->where('status', 'suspended')
            ->orderByDesc('suspended_at')
            ->limit(25)
            ->get();

        $stats = [
            'admins'          => $admins->count(),
            'companies'       => User::query()->companies()->count(),
            'suspended'       => User::withTrashed()->where('status', 'suspended')->count(),
            'archived'        => User::onlyTrashed()->count(),
            'two_factor_on'   => User::whereNotNull('two_factor_confirmed_at')->count(),
            'unverified'      => User::whereNull('email_verified_at')->count(),
        ];

        // Surfaced as a warning banner: the shared socket secret and push
        // credentials are the two settings that silently disable features.
        $health = [
            'socket_enabled'  => (bool) config('socket.enabled'),
            'socket_secret'   => filled(config('socket.secret')),
            'push_enabled'    => (bool) config('readyroute.push.enabled'),
            'app_debug'       => (bool) config('app.debug'),
            'https'           => str_starts_with((string) config('app.url'), 'https://'),
        ];

        return view('content.admin.security.index', compact('admins', 'suspended', 'stats', 'health'));
    }

    /**
     * Change the signed-in admin's own password.
     */
    /**
     * The admin's own details.
     *
     * Kept on this page rather than given one of its own: it is the only
     * account-level screen the platform admin has, and splitting name and
     * password across two pages would be two places to look for one thing.
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'phone'  => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $admin->fill([
            'name'         => $request->name,
            'email'        => $request->email,
            'phone_number' => $request->phone,
        ]);

        if ($request->hasFile('avatar')) {
            // Stored on the public disk, which is where every other uploaded
            // avatar in the app lives; avatar_url already falls back to the
            // default when a file goes missing.
            if ($admin->avatar && str_starts_with($admin->avatar, 'profile_images/')) {
                Storage::disk('public')->delete($admin->avatar);
            }

            $admin->avatar = $request->file('avatar')->store('profile_images', 'public');
        }

        $admin->save();

        return back()->with('success_profile', 'Your details have been updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        return back()->with('success', 'Password updated.');
    }

    /**
     * Sessions other than the current one, so an admin can sign out a device
     * they no longer control.
     */
    public function signOutOtherSessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return back()->with('error', 'Session management requires the database session driver.');
        }

        DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'Signed out of all other sessions.');
    }
}
