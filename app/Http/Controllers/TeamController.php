<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Announcement;
use App\Models\CashEntry;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function dashboard()
    {
        abort_unless(in_array(Auth::user()->role, ['team', 'admin'], true), 403);

        return view('team.dashboard', [
            'members' => Member::with('plan')->latest()->take(8)->get(),
            'proposals' => Proposal::latest()->take(5)->get(),
            'invoices' => Invoice::with('member')->latest('due_date')->take(8)->get(),
            'reservations' => Reservation::with(['member', 'space', 'guests'])->latest('reservation_date')->take(6)->get(),
            'products' => Product::orderBy('quantity')->take(6)->get(),
            'accessLogs' => AccessLog::latest('checked_at')->take(6)->get(),
            'announcements' => Announcement::latest('published_at')->take(3)->get(),
            'income' => CashEntry::where('type', 'income')->sum('amount'),
            'expenses' => CashEntry::where('type', 'expense')->sum('amount'),
            'pendingAmount' => Invoice::where('status', 'pending')->sum('amount'),
            'paidAmount' => Invoice::where('status', 'paid')->sum('amount'),
        ]);
    }
}
