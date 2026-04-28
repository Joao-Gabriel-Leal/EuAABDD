<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Benefit;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\ReservableSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'announcements' => Announcement::latest('published_at')->take(3)->get(),
            'benefits' => Benefit::where('is_active', true)->get(),
            'plans' => Plan::where('is_active', true)->get(),
            'spaces' => ReservableSpace::where('is_active', true)->get(),
        ]);
    }

    public function proposal(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        Proposal::create([
            ...$data,
            'cpf' => null,
            'status' => 'new',
            'notes' => 'Lead criado pelo site publico em '.now()->format('d/m/Y H:i').'.',
        ]);

        return back()->with('proposal_status', 'Recebemos seu interesse. A secretaria entrara em contato para concluir a proposta.');
    }
}
