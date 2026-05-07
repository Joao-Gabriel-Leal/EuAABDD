<?php

namespace App\Http\Controllers;

use App\Models\Dependent;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Services\BillingService;
use App\Services\InvitationService;
use App\Services\MemberCardService;
use App\Services\ReservationService;
use App\Support\BrazilianMasks;
use App\Support\ReservationMap;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function dashboard(BillingService $billingService, MemberCardService $cards)
    {
        $billingService->markOverdueInvoices();

        $member = Auth::user()->member()->with([
            'plan',
            'dependents',
            'invoices' => fn ($query) => $query->latest('due_date'),
            'reservations.space',
            'reservations.invoice',
            'reservations.guests.invitation.invoice',
            'invitations' => fn ($query) => $query->with('guest.reservation.space')->latest('valid_for')->latest(),
        ])->firstOrFail();

        return view('portal.dashboard', [
            'member' => $member,
            'spaces' => ReservableSpace::where('is_active', true)
                ->with('spaceType')
                ->get()
                ->filter(fn (ReservableSpace $space): bool => ($space->rules['reserva'] ?? true) !== false)
                ->values(),
            'reservationMapUrl' => ReservationMap::url(),
            'cardCode' => $cards->code($member),
            'cardQrCode' => $cards->qrCodeDataUri($member),
        ]);
    }

    public function reserve(Request $request, ReservationService $reservations)
    {
        $member = Auth::user()->member;
        $this->authorizeActiveMember($member);
        $request->merge(['payment_mode' => $request->input('payment_mode', 'associado_paga')]);

        $data = $request->validate([
            'reservable_space_id' => [
                'required',
                Rule::exists('reservable_spaces', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'payment_mode' => ['required', 'in:associado_paga,rateio_email'],
            'guests' => ['nullable', 'array'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.cpf' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! BrazilianMasks::hasCpfLength($value)) {
                        $fail('Informe um CPF com 11 numeros.');
                    }
                },
            ],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.phone' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! BrazilianMasks::hasPhoneLength($value)) {
                        $fail('Informe um celular com DDD.');
                    }
                },
            ],
            'guests.*.contact' => ['nullable', 'string', 'max:255'],
        ]);

        $space = ReservableSpace::where('is_active', true)->findOrFail($data['reservable_space_id']);
        if (($space->rules['reserva'] ?? true) === false) {
            throw ValidationException::withMessages([
                'reservable_space_id' => 'Este espaco nao aceita reservas pelo portal.',
            ]);
        }

        $guests = collect($data['guests'] ?? [])
            ->filter(fn (array $guest): bool => filled($guest['name'] ?? null))
            ->values()
            ->all();

        if (count($guests) > $space->capacity) {
            throw ValidationException::withMessages([
                'guests' => 'A lista de convidados nao pode ultrapassar a capacidade do espaco.',
            ]);
        }

        if ($data['payment_mode'] === 'rateio_email') {
            foreach ($guests as $index => $guest) {
                if (blank($guest['email'] ?? null) && blank($guest['phone'] ?? null) && blank($guest['contact'] ?? null)) {
                    throw ValidationException::withMessages([
                        'guests.'.$index.'.contact' => 'Informe e-mail ou celular de todos os convidados no rateio.',
                    ]);
                }
            }
        }

        $reservation = $reservations->createReservation(
            $member,
            $space,
            $data['reservation_date'],
            $guests,
            $data['payment_mode'],
        );

        return back()->with('portal_status', 'Reserva criada com agenda bloqueada e cobranca real vinculada: '.$reservation->invoice->number.'.');
    }

    public function addReservationGuest(Request $request, Reservation $reservation, ReservationService $reservations)
    {
        $member = Auth::user()->member;
        $this->authorizeActiveMember($member);
        abort_unless($reservation->member_id === $member->id, 403);
        $this->mergeLegacyGuestContact($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! BrazilianMasks::hasCpfLength($value)) {
                        $fail('Informe um CPF com 11 numeros.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact' => [
                'required',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, \Closure $fail) => $this->validateGuestContact($value, $fail),
            ],
        ]);

        $guest = $reservations->addGuest($reservation, $data);

        return back()->with('portal_status', 'Convidado '.$guest->name.' incluido na lista da reserva.');
    }

    public function downloadReservationGuestTemplate()
    {
        $csv = "nome,cpf,contato\nMaria Souza,12345678901,maria@email.com\nJoao Lima,,61999999999\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-convidados-reserva.csv"',
        ]);
    }

    public function importReservationGuests(Request $request, Reservation $reservation, ReservationService $reservations)
    {
        $member = Auth::user()->member;
        $this->authorizeActiveMember($member);
        abort_unless($reservation->member_id === $member->id, 403);

        $data = $request->validate([
            'guest_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $created = $reservations->addGuests($reservation, $this->reservationGuestRowsFromCsv($data['guest_file']));

        return back()->with('portal_status', $created->count().' convidado(s) importado(s) para a reserva.');
    }

    public function updateReservationGuest(Request $request, Reservation $reservation, Guest $guest, ReservationService $reservations)
    {
        $member = Auth::user()->member;
        $this->authorizeActiveMember($member);
        abort_unless($reservation->member_id === $member->id, 403);
        abort_unless($guest->reservation_id === $reservation->id, 404);
        $this->mergeLegacyGuestContact($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! BrazilianMasks::hasCpfLength($value)) {
                        $fail('Informe um CPF com 11 numeros.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact' => [
                'required',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, \Closure $fail) => $this->validateGuestContact($value, $fail),
            ],
        ]);

        $reservations->updateGuest($reservation, $guest, $data);

        return back()->with('portal_status', 'Convidado atualizado.');
    }

    public function deleteReservationGuest(Reservation $reservation, Guest $guest, ReservationService $reservations)
    {
        $member = Auth::user()->member;
        $this->authorizeActiveMember($member);
        abort_unless($reservation->member_id === $member->id, 403);
        abort_unless($guest->reservation_id === $reservation->id, 404);

        $reservations->deleteGuest($reservation, $guest);

        return back()->with('portal_status', 'Convidado removido da reserva.');
    }

    public function storeDependent(Request $request)
    {
        $member = Auth::user()->member()->with('plan')->firstOrFail();
        $this->authorizeActiveMember($member);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasCpfLength($value) ?: $fail('Informe um CPF com 11 numeros.'),
            ],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'relationship' => ['nullable', 'string', 'max:80'],
        ]);

        $data['cpf'] = BrazilianMasks::formatCpf($data['cpf'] ?? null);

        if ($data['cpf'] && (Member::where('cpf', $data['cpf'])->exists() || Dependent::where('cpf', $data['cpf'])->exists())) {
            throw ValidationException::withMessages([
                'cpf' => 'Este CPF ja esta cadastrado para um associado ou dependente.',
            ]);
        }

        $includedDependents = (int) ($member->plan?->included_dependents ?? 0);
        $isFree = $member->activeDependents()->count() < $includedDependents;
        $monthlyFee = $isFree ? 0 : (float) ($member->plan?->dependent_extra_price ?? 0);

        $member->dependents()->create([
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'birthdate' => $data['birthdate'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'status' => 'active',
            'is_free' => $isFree,
            'monthly_fee' => $monthlyFee,
            'access_status' => 'allowed',
        ]);

        return back()->with('portal_status', $isFree
            ? 'Dependente cadastrado dentro da cota do plano.'
            : 'Dependente cadastrado com mensalidade extra de R$ '.number_format($monthlyFee, 2, ',', '.').'.');
    }

    public function uploadPaymentProof(Request $request, Invoice $invoice, BillingService $billingService)
    {
        abort_unless($invoice->member_id === Auth::user()->member_id, 403);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:80'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $billingService->uploadProof($invoice, $request->file('proof'), $data['payment_method'], $data['notes'] ?? null);

        return back()->with('portal_status', 'Comprovante recebido. O financeiro fara a baixa manual e confirmara a cobranca.');
    }

    public function payDemo(Invoice $invoice, BillingService $billingService)
    {
        abort_unless($invoice->member_id === Auth::user()->member_id, 403);
        abort_unless($invoice->isPayable(), 403);

        $billingService->recordManualPayment($invoice, [
            'amount' => $invoice->amount,
            'method' => 'QR App AABB Demo',
            'paid_at' => now()->toDateString(),
            'manual_reference' => 'DEMO-'.now()->format('YmdHis'),
            'notes' => 'Pagamento simulado pelo portal do associado.',
        ]);

        return back()->with('portal_status', 'Pagamento demo confirmado. Carteirinha liberada para acesso ao clube.');
    }

    public function createInvitation(Request $request, InvitationService $invitations)
    {
        $this->authorizeActiveMember(Auth::user()->member);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasCpfLength($value) ?: $fail('Informe um CPF com 11 numeros.'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'valid_for' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $data['cpf'] = BrazilianMasks::formatCpf($data['cpf'] ?? null);

        $invitation = $invitations->createClubInvitation(Auth::user()->member, $data);

        return back()->with('portal_status', $invitation->is_extra
            ? 'Convite excedente gerado com cobranca vinculada. Ele sera liberado apos baixa do financeiro.'
            : 'Convite gerado dentro da cota mensal: '.$invitation->code.'.');
    }

    private function authorizeActiveMember($member): void
    {
        abort_unless($member?->status === 'active', 403);
    }

    private function mergeLegacyGuestContact(Request $request): void
    {
        if ($request->filled('contact')) {
            return;
        }

        if ($request->filled('email')) {
            $request->merge(['contact' => $request->input('email')]);
            return;
        }

        if ($request->filled('phone')) {
            $request->merge(['contact' => $request->input('phone')]);
        }
    }

    private function validateGuestContact(mixed $value, \Closure $fail): void
    {
        $contact = trim((string) $value);

        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $digits = BrazilianMasks::onlyDigits($contact);
        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }

        if (! in_array(strlen($digits), [10, 11], true)) {
            $fail('Informe um e-mail valido ou celular com DDD.');
        }
    }

    private function reservationGuestRowsFromCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            throw ValidationException::withMessages([
                'guest_file' => 'Nao foi possivel ler o arquivo enviado.',
            ]);
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $header = fgetcsv($handle, 0, $delimiter);

        if (! $header) {
            fclose($handle);
            throw ValidationException::withMessages([
                'guest_file' => 'O arquivo precisa ter as colunas nome, cpf e contato.',
            ]);
        }

        $header = array_map(fn ($column) => $this->normalizeCsvHeader((string) $column), $header);
        $positions = array_flip($header);

        foreach (['nome', 'cpf', 'contato'] as $column) {
            if (! array_key_exists($column, $positions)) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'guest_file' => 'O arquivo precisa ter as colunas nome, cpf e contato.',
                ]);
            }
        }

        $rows = [];
        $errors = [];
        $line = 1;

        while (($columns = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if (collect($columns)->every(fn ($value): bool => blank($value))) {
                continue;
            }

            $name = trim((string) ($columns[$positions['nome']] ?? ''));
            $cpf = trim((string) ($columns[$positions['cpf']] ?? ''));
            $contact = trim((string) ($columns[$positions['contato']] ?? ''));

            if ($name === '') {
                $errors[] = 'Linha '.$line.': nome obrigatorio.';
            }

            if ($cpf !== '' && ! BrazilianMasks::hasCpfLength($cpf)) {
                $errors[] = 'Linha '.$line.': CPF deve ter 11 numeros.';
            }

            try {
                $this->validateGuestContact($contact, function (string $message) use (&$errors, $line): void {
                    $errors[] = 'Linha '.$line.': '.$message;
                });
            } catch (\Throwable) {
                $errors[] = 'Linha '.$line.': contato invalido.';
            }

            if ($name !== '' && $contact !== '') {
                $rows[] = [
                    'name' => $name,
                    'cpf' => $cpf === '' ? null : $cpf,
                    'contact' => $contact,
                ];
            }
        }

        fclose($handle);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'guest_file' => $errors,
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'guest_file' => 'Inclua pelo menos um convidado no arquivo.',
            ]);
        }

        return $rows;
    }

    private function normalizeCsvHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return Str::of($value)->ascii()->lower()->trim()->toString();
    }
}
