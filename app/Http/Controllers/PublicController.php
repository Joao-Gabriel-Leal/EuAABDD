<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\ReservableSpace;
use App\Models\User;
use App\Services\MembershipSignupService;
use App\Support\BrazilianMasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'spaces' => ReservableSpace::where('is_active', true)->with('spaceType')->get(),
        ]);
    }

    public function proposal(Request $request, MembershipSignupService $signups)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'required',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasCpfLength($value) ?: $fail('Informe um CPF com 11 numeros.'),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => [
                'required',
                'string',
                'max:40',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasPhoneLength($value) ?: $fail('Informe um telefone com DDD.'),
            ],
            'plan_id' => ['required', 'exists:plans,id'],
            'category' => ['required', 'in:Familiar,Individual,Individual 30 Menos,Especial'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['cpf'] = BrazilianMasks::formatCpf($data['cpf']);
        $data['phone'] = BrazilianMasks::formatPhone($data['phone']);

        if (Member::where('cpf', $data['cpf'])->exists()) {
            return back()
                ->withErrors(['cpf' => 'Este CPF ja possui cadastro de associado.'])
                ->withInput();
        }

        $signup = $signups->create($data);
        Auth::login($signup['user']);

        return redirect()
            ->route('portal.dashboard')
            ->with('portal_status', 'Adesao criada. Pague a primeira mensalidade para liberar sua carteirinha e acessar o clube.');
    }
}
