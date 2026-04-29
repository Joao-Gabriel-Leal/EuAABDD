<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveReservableSpaceRequest;
use App\Models\AccessLog;
use App\Models\Announcement;
use App\Models\Benefit;
use App\Models\CashEntry;
use App\Models\Dependent;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Services\AccessService;
use App\Services\BillingService;
use App\Services\MemberImportService;
use App\Services\StockQrService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function dashboard(Request $request, BillingService $billingService, StockQrService $stockQrs)
    {
        $this->authorizeInternal();
        $billingService->markOverdueInvoices();
        $managedReservationSpaces = ReservableSpace::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $spaceEditor = $request->integer('space')
            ? $managedReservationSpaces->firstWhere('id', $request->integer('space'))
            : null;

        return view('team.dashboard', [
            'members' => Member::with(['plan', 'dependents'])->latest()->take(10)->get(),
            'pendingSignups' => Member::with(['plan', 'invoices'])
                ->where('status', 'pending_payment')
                ->latest()
                ->take(8)
                ->get(),
            'dependents' => Dependent::with('member')->latest()->take(10)->get(),
            'proposals' => Proposal::with('plan')->latest()->take(8)->get(),
            'invoices' => Invoice::with(['member', 'payments'])->latest('due_date')->take(12)->get(),
            'initialSignupInvoices' => Invoice::with('member')
                ->where('type', 'membership_initial')
                ->whereIn('status', ['open', 'pending', 'awaiting_review', 'overdue'])
                ->latest('due_date')
                ->take(8)
                ->get(),
            'payments' => Payment::with('invoice.member')->latest('received_at')->latest()->take(8)->get(),
            'reservations' => Reservation::with(['member', 'space', 'guests', 'invoice'])->latest('reservation_date')->take(12)->get(),
            'reservationSpaces' => $managedReservationSpaces->where('is_active', true)->values(),
            'managedReservationSpaces' => $managedReservationSpaces,
            'spaceEditor' => $spaceEditor,
            'invitations' => Invitation::with(['member', 'guest', 'invoice'])->latest('valid_for')->take(10)->get(),
            'guests' => Guest::with(['member', 'reservation.space'])->latest()->take(8)->get(),
            'products' => Product::with(['movements' => fn ($query) => $query->latest()->take(4)])
                ->orderBy('quantity')
                ->get(),
            'stockMovements' => StockMovement::with(['product', 'createdBy'])->latest()->take(14)->get(),
            'stockQrCodes' => Product::orderBy('name')->get()->mapWithKeys(fn (Product $product) => [
                $product->id => $stockQrs->qrCodeDataUri($product),
            ]),
            'accessLogs' => AccessLog::latest('checked_at')->take(10)->get(),
            'announcements' => Announcement::latest('published_at')->take(6)->get(),
            'benefits' => Benefit::latest()->take(6)->get(),
            'cashEntries' => CashEntry::latest('entry_date')->take(8)->get(),
            'income' => CashEntry::where('type', 'income')->sum('amount'),
            'expenses' => CashEntry::where('type', 'expense')->sum('amount'),
            'pendingAmount' => Invoice::whereIn('status', ['open', 'pending', 'awaiting_review'])->sum('amount'),
            'overdueAmount' => Invoice::where('status', 'overdue')->sum('amount'),
            'paidAmount' => Invoice::where('status', 'paid')->sum('amount'),
            'membersCount' => Member::where('status', 'active')->count(),
            'lowStockCount' => Product::whereColumn('quantity', '<', 'minimum_quantity')->count(),
            'zeroStockCount' => Product::where('quantity', '<=', 0)->count(),
            'stockTotalValue' => Product::all()->sum(fn (Product $product) => $product->stockValue()),
            'stockAlerts' => Product::where('is_active', true)
                ->where(function ($query) {
                    $query->where('quantity', '<=', 0)
                        ->orWhereColumn('quantity', '<', 'minimum_quantity');
                })
                ->orderBy('quantity')
                ->get(),
            'openInvoicesCount' => Invoice::whereIn('status', ['open', 'pending', 'awaiting_review'])->count(),
            'overdueInvoicesCount' => Invoice::where('status', 'overdue')->count(),
            'todayAccessCount' => AccessLog::whereDate('checked_at', today())->count(),
            'scheduledReservationsCount' => Reservation::whereDate('reservation_date', '>=', today())->count(),
        ]);
    }

    public function storeReservationSpace(SaveReservableSpaceRequest $request): RedirectResponse
    {
        $this->authorizeInternal();

        $space = new ReservableSpace();
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
                'base_price' => (float) $space->base_price,
                'capacity' => $space->capacity,
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

        $space->fill([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'location' => $validated['location'],
            'capacity' => (int) $validated['capacity'],
            'base_price' => (float) $validated['base_price'],
            'image_url' => $this->resolveReservationSpaceImageUrl($request, $space),
            'is_active' => $request->boolean('is_active'),
            'rules' => $space->mergeOperationalRules([
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'included_guests' => (int) $validated['included_guests'],
            ]),
        ]);

        $space->save();
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

    private function redirectToReservations(?ReservableSpace $space, string $message): RedirectResponse
    {
        $params = $space?->exists ? ['space' => $space->id] : [];

        return redirect()
            ->to(route('team.dashboard', $params).'#reservas')
            ->with('team_status', $message)
            ->with('team_status_type', 'success');
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
