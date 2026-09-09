<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $activeOrganizationsCount = User::where('role', 'dispatcher')->count();
        $totalSystemUsersCount    = User::count();
        
        $recentCompanies = User::where('role', 'dispatcher')
            ->latest()
            ->take(5)
            ->get();

        return view('content.admin.dashboard', compact(
            'activeOrganizationsCount',
            'totalSystemUsersCount',
            'recentCompanies'
        ));
    }
}
