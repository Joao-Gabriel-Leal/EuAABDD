<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveReservableSpaceRequest;
use App\Models\AccessLog;
use App\Models\Announcement;
use App\Models\Benefit;
use App\Models\Guest;
use App\Models\ImportBatch;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ReservableSpace;
use App\Models\ReservableSpaceType;
use App\Models\Reservation;
use App\Services\AccessService;
use App\Services\BillingService;
use App\Services\MemberImportService;
use App\Services\ProposalService;
use App\Services\StockService;
use App\Support\BrazilianMasks;
use App\Support\ReservationMap;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function dashboard(Request $request, BillingService $billingService)
    {
        $this->authorizeInternal();
        $billingService->markOverdueInvoices();

        $managedReservationSpaces = ReservableSpace::query()
            ->with('spaceType')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $spaceEditor = $request->integer('space')
            ? $managedReservationSpaces->firstWhere('id', $request->integer('space'))
            : null;
        $managedSpaceTypes = ReservableSpaceType::query()
            ->withCount('spaces')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $spaceTypeEditor = $request->integer('space_type')
            ? $managedSpaceTypes->firstWhere('id', $request->integer('space_type'))
            : null;

        return view('team.dashboard', [
            'members' => Member::with('plan')->latest()->take(8)->get(),
            'importBatches' => ImportBatch::with('createdBy')->latest()->take(6)->get(),
            'invoices' => Invoice::with(['member', 'payments'])
                ->whereIn('type', ['membership_initial', 'reservation', 'reservation_guest'])
                ->latest('due_date')
                ->take(16)
                ->get(),
            'payments' => Payment::with('invoice.member')
                ->whereHas('invoice', fn ($query) => $query->whereIn('type', ['membership_initial', 'reservation', 'reservation_guest']))
                ->latest('received_at')
                ->latest()
                ->take(10)
                ->get(),
            'reservations' => Reservation::with(['member', 'space', 'guests.invitation', 'invoice'])->latest('reservation_date')->take(12)->get(),
            'reservationSpaces' => $managedReservationSpaces->where('is_active', true)->values(),
            'managedReservationSpaces' => $managedReservationSpaces,
            'managedSpaceTypes' => $managedSpaceTypes,
            'spaceTypeEditor' => $spaceTypeEditor,
            'reservationMapUrl' => ReservationMap::url(),
            'spaceEditor' => $spaceEditor,
            'invitations' => Invitation::with(['member', 'guest', 'invoice'])
                ->where('type', 'reservation_guest')
                ->latest('valid_for')
                ->take(10)
                ->get(),
            'guests' => Guest::with(['member', 'reservation.space'])->latest()->take(8)->get(),
            'membersCount' => Member::where('status', 'active')->count(),
            'openInvoicesCount' => Invoice::whereIn('type', ['membership_initial', 'reservation', 'reservation_guest'])
                ->whereIn('status', ['open', 'pending', 'awaiting_review'])
                ->count(),
            'overdueInvoicesCount' => Invoice::whereIn('type', ['membership_initial', 'reservation', 'reservation_guest'])
                ->where('status', 'overdue')
                ->count(),
            'scheduledReservationsCount' => Reservation::whereDate('reservation_date', '>=', today())->count(),
        ]);
    }

    private function dashboardMetricsPeriod(Request $request): array
    {
        $defaultFrom = CarbonImmutable::now()->startOfMonth();
        $defaultTo = CarbonImmutable::now()->endOfMonth();

        if (! $request->filled('metrics_from') || ! $request->filled('metrics_to')) {
            return ['from' => $defaultFrom, 'to' => $defaultTo];
        }

        $from = $this->dashboardMetricDate($request->string('metrics_from')->toString());
        $to = $this->dashboardMetricDate($request->string('metrics_to')->toString());

        if (! $from || ! $to || $from->greaterThan($to)) {
            return ['from' => $defaultFrom, 'to' => $defaultTo];
        }

        return ['from' => $from, 'to' => $to];
    }

    private function dashboardMetricDate(string $date): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();

            return $parsed->toDateString() === $date ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function storeReservationSpace(SaveReservableSpaceRequest $request): RedirectResponse
    {
        $this->authorizeInternal();

        $space = new ReservableSpace;
        $this->saveReservationSpace($space, $request);

        return $this->redirectToReservations($space, 'Espaco cadastrado e disponivel para agenda, portal e home.');
    }

    public function updateReservationSpace(SaveReservableSpaceRequest $request, ReservableSpace $space): RedirectResponse
    {
        $this->authorizeInternal();

        $this->saveReservationSpace($space, $request);

        return $this->redirectToReservations($space, 'Espaco atualizado com sucesso para a operacao.');
    }

    public function toggleReservationSpace(ReservableSpace $space): RedirectResponse
    {
        $this->authorizeInternal();

        $space->update([
            'is_active' => ! $space->is_active,
        ]);

        return $this->redirectToReservations(
            null,
            $space->is_active
                ? 'Espaco reativado. Ele voltou para o calendario e para o portal.'
                : 'Espaco desativado. O historico foi preservado e novas reservas foram bloqueadas.',
        );
    }

    public function storeReservationSpaceType(Request $request): RedirectResponse
    {
        $this->authorizeInternal();

        $spaceType = new ReservableSpaceType;
        $this->saveReservationSpaceType($spaceType, $request);

        return $this->redirectToReservations(
            ['space_type' => $spaceType->id],
            'Tipo de espaco cadastrado com cor de pin pronta para o mapa.',
        );
    }

    public function updateReservationSpaceType(Request $request, ReservableSpaceType $spaceType): RedirectResponse
    {
        $this->authorizeInternal();

        $this->saveReservationSpaceType($spaceType, $request);

        return $this->redirectToReservations(
            ['space_type' => $spaceType->id],
            'Tipo de espaco atualizado e aplicado aos pins vinculados.',
        );
    }

    public function toggleReservationSpaceType(ReservableSpaceType $spaceType): RedirectResponse
    {
        $this->authorizeInternal();

        $spaceType->update([
            'is_active' => ! $spaceType->is_active,
        ]);

        return $this->redirectToReservations(
            [],
            $spaceType->is_active
                ? 'Tipo de espaco reativado para novos cadastros.'
                : 'Tipo de espaco desativado. Os espacos existentes mantem historico e cor.',
        );
    }

    public function uploadReservationMap(Request $request): RedirectResponse
    {
        $this->authorizeInternal();

        $data = $request->validate([
            'reservation_map' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ], [
            'reservation_map.required' => 'Envie a imagem da planta do clube.',
            'reservation_map.uploaded' => 'A imagem nao chegou ao servidor. Use JPG, PNG ou WebP menor que o limite do servidor.',
            'reservation_map.image' => 'A planta precisa ser uma imagem valida.',
            'reservation_map.mimes' => 'Use uma imagem JPG, PNG ou WebP para a planta.',
            'reservation_map.max' => 'A planta pode ter no maximo 20 MB.',
        ]);

        ReservationMap::store($data['reservation_map']);

        return redirect()
            ->to(route('team.dashboard').'#reservas')
            ->with('team_status', 'Planta do clube atualizada para reservas e portal.')
            ->with('team_status_type', 'success');
    }

    public function generateMonthlyInvoices(Request $request, BillingService $billingService)
    {
        $this->authorizeFinance();

        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2024,2035'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $created = $billingService->createMonthlyInvoices((int) $data['year'], (int) $data['month']);

        return back()->with('team_status', $created->count().' mensalidade(s) gerada(s). Duplicadas foram ignoradas com segurança.');
    }

    public function markInvoicePaid(Request $request, Invoice $invoice, BillingService $billingService)
    {
        $this->authorizeFinance();
        abort_unless(in_array($invoice->type, ['membership_initial', 'reservation', 'reservation_guest'], true), 404);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:80'],
            'paid_at' => ['required', 'date'],
            'manual_reference' => ['nullable', 'string', 'max:120'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['proof_file'] = $request->file('proof');
        $billingService->recordManualPayment($invoice, $data, Auth::user());

        return back()->with('team_status', 'Baixa registrada e vínculos da cobrança atualizados.');
    }

    public function importMembers(Request $request, MemberImportService $imports)
    {
        $this->authorizeSecretariat();

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:8192'],
        ]);

        $batch = $imports->import($data['file'], Auth::user());

        return back()->with('team_status', "Importação concluída: {$batch->success_rows} sucesso(s), {$batch->failed_rows} erro(s).");
    }

    public function storeProposal(Request $request): RedirectResponse
    {
        $this->authorizeSecretariat();

        $proposal = new Proposal;
        $this->saveProposal($proposal, $request);

        return $this->redirectToSecretariat($proposal, 'Proposta manual cadastrada para analise da secretaria.');
    }

    public function updateProposal(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorizeSecretariat();

        $this->saveProposal($proposal, $request);

        return $this->redirectToSecretariat($proposal, 'Proposta manual atualizada.');
    }

    public function approveProposal(Proposal $proposal, ProposalService $proposals): RedirectResponse
    {
        $this->authorizeSecretariat();

        $member = $proposals->approveAndConvert($proposal, Auth::user());

        return $this->redirectToSecretariat(
            $proposal,
            'Proposta aprovada e convertida no associado '.$member->membership_code.'.',
        );
    }

    public function signProposal(Proposal $proposal): RedirectResponse
    {
        $this->authorizeSecretariat();

        if ($proposal->status !== 'approved') {
            return $this->redirectToSecretariat($proposal, 'Apenas propostas aprovadas podem ser marcadas como assinadas.', 'warning');
        }

        $proposal->update([
            'signature_status' => 'signed',
            'signed_at' => now(),
        ]);

        return $this->redirectToSecretariat($proposal, 'Assinatura da proposta registrada.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $this->authorizeSecretariat();

        $announcement = Announcement::create($this->announcementData($request));

        return $this->redirectToContent(['announcement' => $announcement->id], 'Comunicado cadastrado.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeSecretariat();

        $announcement->update($this->announcementData($request, $announcement));

        return $this->redirectToContent(['announcement' => $announcement->id], 'Comunicado atualizado.');
    }

    public function storeBenefit(Request $request): RedirectResponse
    {
        $this->authorizeSecretariat();

        $benefit = Benefit::create($this->benefitData($request));

        return $this->redirectToContent(['benefit' => $benefit->id], 'Beneficio cadastrado.');
    }

    public function updateBenefit(Request $request, Benefit $benefit): RedirectResponse
    {
        $this->authorizeSecretariat();

        $benefit->update($this->benefitData($request));

        return $this->redirectToContent(['benefit' => $benefit->id], 'Beneficio atualizado.');
    }

    public function toggleBenefit(Benefit $benefit): RedirectResponse
    {
        $this->authorizeSecretariat();

        $benefit->update([
            'is_active' => ! $benefit->is_active,
        ]);

        return $this->redirectToContent(
            [],
            $benefit->is_active ? 'Beneficio ativado na comunicacao.' : 'Beneficio desativado sem apagar historico.',
        );
    }

    public function moveStock(Request $request, Product $product, StockService $stock)
    {
        $this->authorizeInternal();

        $data = $request->validate([
            'type' => ['required', 'in:entry,exit,adjustment,loss'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stock->move(
            $product,
            $data['type'],
            (int) $data['quantity'],
            $data['reason'] ?? null,
            Auth::user(),
            isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
        );

        return back()
            ->with('team_status', 'Movimento de estoque registrado com saldo, custo e auditoria atualizados.')
            ->with('team_status_type', 'success');
    }

    public function registerAccess(Request $request, AccessService $access)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'gate' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $log = $access->registerInvitationAccess($data['code'], $data['gate'] ?? 'Portaria principal');
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'tone' => 'danger',
                    'message' => $exception->errors()['code'][0] ?? 'Convite nao encontrado.',
                ], 422);
            }

            throw $exception;
        }

        $allowed = $log->status === 'allowed';
        $message = $allowed
            ? 'Acesso liberado: '.$log->person_name.'.'
            : 'Acesso bloqueado: '.$log->person_name.' - '.str_replace('blocked: ', '', $log->status).'.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $allowed,
                'tone' => $allowed ? 'success' : 'warning',
                'message' => $message,
                'log' => $this->accessLogPayload($log),
            ]);
        }

        return back()
            ->with('team_status', $message)
            ->with('team_status_type', $allowed ? 'success' : 'warning');
    }

    public function showStockProduct(string $token, StockQrService $stockQrs)
    {
        $this->authorizeInternal();

        $product = Product::with(['movements' => fn ($query) => $query->with('createdBy')->latest()->take(20)])
            ->where('qr_token', $token)
            ->firstOrFail();

        return view('team.stock-product', [
            'product' => $product,
            'stockQrCode' => $stockQrs->qrCodeDataUri($product),
            'latestMovements' => $product->movements,
        ]);
    }

    public function reservationAvailability(Request $request)
    {
        $user = Auth::user();
        abort_unless($user?->hasInternalRole() || $user?->role === 'member', 403);

        $space = ReservableSpace::where('is_active', true)
            ->when($request->integer('space_id'), fn ($query, int $spaceId) => $query->whereKey($spaceId))
            ->orderBy('name')
            ->firstOrFail();

        $month = $this->calendarMonth($request->string('month')->toString());
        $selectedDate = $this->calendarSelectedDate($request->string('date')->toString(), $month);
        $gridStart = $month->startOfMonth()->subDays($month->startOfMonth()->dayOfWeek);
        $gridEnd = $month->endOfMonth()->addDays(6 - $month->endOfMonth()->dayOfWeek);

        $reservations = Reservation::with(['member', 'guests', 'invoice'])
            ->where('reservable_space_id', $space->id)
            ->whereBetween('reservation_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderBy('reservation_date')
            ->get()
            ->groupBy(fn (Reservation $reservation) => $reservation->reservation_date->toDateString());

        $days = collect();
        for ($day = $gridStart; $day->lessThanOrEqualTo($gridEnd); $day = $day->addDay()) {
            $date = $day->toDateString();
            $dayReservations = $reservations->get($date, collect());

            $days->push([
                'date' => $date,
                'day' => $day->day,
                'currentMonth' => $day->month === $month->month,
                'isToday' => $day->isToday(),
                'isPast' => $day->isPast() && ! $day->isToday(),
                'isBlocked' => $dayReservations->isNotEmpty(),
                'reservationsCount' => $dayReservations->count(),
                'reservations' => $dayReservations->map(fn (Reservation $reservation) => $this->reservationPayload($reservation, $user->hasInternalRole()))->values(),
            ]);
        }

        $selectedReservations = $reservations->get($selectedDate->toDateString(), collect());
        $startsAt = $space->startsAt();
        $endsAt = $space->endsAt();

        return response()->json([
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'type' => $space->typeSlug(),
                'type_name' => $space->typeName(),
                'pin_color' => $space->pinColor(),
                'base_price' => (float) $space->base_price,
                'guest_price' => $space->guestPrice(),
                'capacity' => $space->capacity,
                'image_url' => $space->image_url,
                'map_x' => $space->mapX(),
                'map_y' => $space->mapY(),
                'map_note' => $space->mapNote(),
            ],
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->translatedFormat('F Y'),
            'selectedDate' => $selectedDate->toDateString(),
            'days' => $days,
            'slots' => [[
                'label' => $startsAt.' as '.$endsAt,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'available' => $selectedReservations->isEmpty() && ! ($selectedDate->isPast() && ! $selectedDate->isToday()),
            ]],
            'selectedReservations' => $selectedReservations
                ->map(fn (Reservation $reservation) => $this->reservationPayload($reservation, $user->hasInternalRole()))
                ->values(),
        ]);
    }

    private function accessLogPayload(AccessLog $log): array
    {
        return [
            'id' => $log->id,
            'person_name' => $log->person_name,
            'person_type' => $log->person_type,
            'gate' => $log->gate,
            'status' => $log->status,
            'status_label' => $log->status === 'allowed' ? 'Liberado' : 'Bloqueado',
            'checked_at' => $log->checked_at->format('d/m/Y H:i'),
        ];
    }

    private function reservationPayload(Reservation $reservation, bool $detailed): array
    {
        return [
            'id' => $reservation->id,
            'space' => $reservation->space?->name,
            'member' => $detailed ? $reservation->member?->name : 'Reservado',
            'date' => $reservation->reservation_date->toDateString(),
            'starts_at' => $reservation->starts_at,
            'ends_at' => $reservation->ends_at,
            'guests_count' => $detailed ? $reservation->guests->count() : null,
            'status' => $reservation->status,
            'status_label' => $reservation->statusLabel(),
            'invoice' => $detailed ? $reservation->invoice?->number : null,
            'amount' => $detailed ? (float) $reservation->total_amount : null,
        ];
    }

    private function calendarMonth(?string $month): CarbonImmutable
    {
        try {
            return $month
                ? CarbonImmutable::parse($month.'-01')->startOfMonth()
                : CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    private function calendarSelectedDate(?string $date, CarbonImmutable $month): CarbonImmutable
    {
        try {
            return $date
                ? CarbonImmutable::parse($date)->startOfDay()
                : CarbonImmutable::now()->startOfDay();
        } catch (\Throwable) {
            return $month->startOfMonth();
        }
    }

    private function saveReservationSpace(ReservableSpace $space, SaveReservableSpaceRequest $request): void
    {
        $validated = $request->validated();
        $spaceType = $this->resolveReservationSpaceType($validated);

        $space->fill([
            'name' => $validated['name'],
            'reservable_space_type_id' => $spaceType?->id,
            'type' => $spaceType?->slug ?? ReservableSpaceType::normalizeSlug($validated['type'] ?? 'espaco'),
            'location' => $validated['location'],
            'capacity' => (int) $validated['capacity'],
            'base_price' => (float) $validated['base_price'],
            'image_url' => $this->resolveReservationSpaceImageUrl($request, $space),
            'is_active' => $request->boolean('is_active'),
            'rules' => $space->mergeOperationalRules([
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'included_guests' => (int) $validated['included_guests'],
                'guest_price' => (float) $validated['guest_price'],
                'map_x' => (int) $validated['map_x'],
                'map_y' => (int) $validated['map_y'],
                'map_note' => $validated['map_note'] ?? null,
            ]),
        ]);

        $space->save();
    }

    private function resolveReservationSpaceType(array $validated): ?ReservableSpaceType
    {
        if (! empty($validated['reservable_space_type_id'])) {
            return ReservableSpaceType::find((int) $validated['reservable_space_type_id']);
        }

        $slug = ReservableSpaceType::normalizeSlug($validated['type'] ?? null);

        return ReservableSpaceType::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
                'pin_color' => ReservableSpaceType::fallbackColorForSlug($slug),
                'is_active' => true,
            ],
        );
    }

    private function saveReservationSpaceType(ReservableSpaceType $spaceType, Request $request): void
    {
        $slug = ReservableSpaceType::normalizeSlug($request->input('slug') ?: $request->input('name'), $request->input('name'));

        $data = $request->merge(['slug' => $slug])->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('reservable_space_types', 'slug')->ignore($spaceType->id),
            ],
            'pin_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'pin_color.regex' => 'Informe uma cor hexadecimal valida para o pin.',
            'slug.regex' => 'Use apenas letras, numeros e hifens no identificador.',
        ]);

        $spaceType->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'pin_color' => ReservableSpaceType::normalizePinColor($data['pin_color']),
            'is_active' => $request->boolean('is_active'),
        ]);
        $spaceType->save();

        $spaceType->spaces()->update(['type' => $spaceType->slug]);
    }

    private function resolveReservationSpaceImageUrl(SaveReservableSpaceRequest $request, ReservableSpace $space): ?string
    {
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('reservable-spaces', 'public');

            return ReservableSpace::normalizeImageUrl($path);
        }

        if ($request->filled('image_url')) {
            return $request->string('image_url')->trim()->toString();
        }

        return $space->getRawOriginal('image_url');
    }

    private function saveProposal(Proposal $proposal, Request $request): void
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'exists:plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasCpfLength($value) ?: $fail('Informe um CPF com 11 numeros.'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:40',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasPhoneLength($value) ?: $fail('Informe um telefone com DDD.'),
            ],
            'status' => ['required', 'in:new,analysis,approved,rejected'],
            'signature_status' => ['nullable', 'in:pending,pending_president_signature,signed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['cpf'] = BrazilianMasks::formatCpf($data['cpf'] ?? null);
        $data['phone'] = BrazilianMasks::formatPhone($data['phone'] ?? null);
        $data['signature_status'] ??= 'pending';

        if ($data['status'] === 'approved' && ! $proposal->approved_at) {
            $data['approved_at'] = now();
        }

        if ($data['status'] === 'rejected' && ! $proposal->rejected_at) {
            $data['rejected_at'] = now();
        }

        if ($data['signature_status'] === 'signed' && ! $proposal->signed_at) {
            $data['signed_at'] = now();
        }

        $proposal->fill($data);
        $proposal->save();
    }

    private function announcementData(Request $request, ?Announcement $announcement = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:800'],
            'body' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = $this->uniqueAnnouncementSlug(($data['slug'] ?? null) ?: $data['title'], $announcement);
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    private function uniqueAnnouncementSlug(string $value, ?Announcement $announcement = null): string
    {
        $base = Str::slug($value) ?: 'comunicado';
        $slug = $base;
        $counter = 2;

        while (Announcement::where('slug', $slug)
            ->when($announcement?->exists, fn ($query) => $query->whereKeyNot($announcement->id))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function benefitData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1200'],
            'icon' => ['nullable', 'string', 'max:80'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function redirectToReservations(ReservableSpace|array|null $space, string $message): RedirectResponse
    {
        $params = is_array($space)
            ? $space
            : ($space?->exists ? ['space' => $space->id] : []);

        return redirect()
            ->to(route('team.dashboard', $params).'#reservas')
            ->with('team_status', $message)
            ->with('team_status_type', 'success');
    }

    private function redirectToSecretariat(?Proposal $proposal, string $message, string $type = 'success'): RedirectResponse
    {
        $params = $proposal?->exists ? ['proposal' => $proposal->id] : [];

        return redirect()
            ->to(route('team.dashboard', $params).'#secretaria')
            ->with('team_status', $message)
            ->with('team_status_type', $type);
    }

    private function redirectToContent(array $params, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect()
            ->to(route('team.dashboard', $params).'#conteudo')
            ->with('team_status', $message)
            ->with('team_status_type', $type);
    }

    private function authorizeInternal(): void
    {
        abort_unless(Auth::user()?->hasInternalRole(), 403);
    }

    private function authorizeFinance(): void
    {
        abort_unless(Auth::user()?->canManageFinance(), 403);
    }

    private function authorizeSecretariat(): void
    {
        abort_unless(Auth::user()?->canManageSecretariat(), 403);
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->canManageAccess(), 403);
    }
}
