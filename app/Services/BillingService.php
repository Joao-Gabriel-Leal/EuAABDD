<?php

namespace App\Services;

use App\Models\CashEntry;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function createMonthlyInvoices(int $year, int $month): Collection
    {
        $billingMonth = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $created = collect();

        Member::query()
            ->with('plan')
            ->where('status', 'active')
            ->whereNotNull('plan_id')
            ->orderBy('membership_code')
            ->chunk(100, function ($members) use ($billingMonth, $created) {
                foreach ($members as $member) {
                    $amount = $member->monthlyAmount();

                    if ($amount <= 0) {
                        continue;
                    }

                    $dueDay = min($member->dueDay(), $billingMonth->daysInMonth);
                    $dueDate = $billingMonth->setDay($dueDay);

                    $invoice = Invoice::query()
                        ->where('member_id', $member->id)
                        ->where('type', 'monthly')
                        ->whereDate('billing_month', $billingMonth->toDateString())
                        ->first();

                    if (! $invoice) {
                        $invoice = Invoice::create([
                            'member_id' => $member->id,
                            'type' => 'monthly',
                            'billing_month' => $billingMonth->toDateString(),
                            'number' => $this->nextNumber('MEN', $member),
                            'description' => 'Mensalidade '.$billingMonth->translatedFormat('F/Y').' - '.$member->plan->name.' '.$member->category,
                            'amount' => $amount,
                            'due_date' => $dueDate->toDateString(),
                            'status' => $dueDate->isPast() ? 'overdue' : 'open',
                            'payment_method' => 'Boleto BRB / QR App',
                            'issued_at' => now(),
                            'metadata' => [
                                'meios_previstos' => ['boleto_brb', 'debito_brb', 'qr_app'],
                                'categoria' => $member->category,
                                'vencimento' => $dueDay,
                            ],
                        ]);

                        $created->push($invoice);
                    }
                }
            });

        return $created;
    }

    public function markOverdueInvoices(): int
    {
        return Invoice::query()
            ->whereIn('status', ['open', 'pending'])
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);
    }

    public function uploadProof(Invoice $invoice, ?UploadedFile $proof, ?string $method, ?string $notes = null): Invoice
    {
        if (! $invoice->isPayable()) {
            throw ValidationException::withMessages([
                'invoice' => 'Esta cobrança não aceita novo comprovante.',
            ]);
        }

        $path = $proof?->store('payment-proofs');
        $metadata = $invoice->metadata ?? [];
        $metadata['portal_notes'] = $notes;
        $metadata['proof_uploaded_at'] = now()->toDateTimeString();

        $invoice->update([
            'status' => 'awaiting_review',
            'payment_method' => $method ?: $invoice->payment_method,
            'payment_proof_path' => $path ?: $invoice->payment_proof_path,
            'metadata' => $metadata,
        ]);

        return $invoice->fresh();
    }

    public function recordManualPayment(Invoice $invoice, array $data, ?User $user = null): Payment
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice' => 'Cobrança cancelada não pode receber baixa.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $user) {
            $amount = (float) ($data['amount'] ?? $invoice->amount);
            $paidAt = CarbonImmutable::parse($data['paid_at'] ?? now());
            $method = $data['method'] ?? $invoice->payment_method ?? 'Baixa manual';
            $proofPath = $data['proof_path'] ?? null;

            if (($data['proof_file'] ?? null) instanceof UploadedFile) {
                $proofPath = $data['proof_file']->store('payment-proofs');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $method,
                'status' => 'paid',
                'confirmed_by_user_id' => $user?->id,
                'transaction_code' => $data['manual_reference'] ?? 'MAN-'.Str::upper(Str::random(8)),
                'proof_path' => $proofPath ?: $invoice->payment_proof_path,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $paidAt,
                'received_at' => now(),
            ]);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'payment_method' => $method,
                'payment_proof_path' => $payment->proof_path,
                'manual_reference' => $payment->transaction_code,
                'confirmed_by_user_id' => $user?->id,
                'reviewed_at' => now(),
            ]);

            $this->activateLinkedRecords($invoice->fresh());
            $this->registerCashEntry($invoice->fresh(), $payment);

            return $payment;
        });
    }

    public function nextNumber(string $prefix, Member $member): string
    {
        return 'AABB-'.$prefix.'-'.now()->format('YmdHis').'-'.$member->id.'-'.Str::upper(Str::random(3));
    }

    private function activateLinkedRecords(Invoice $invoice): void
    {
        if ($invoice->type === 'membership_initial' && $invoice->member?->status === 'pending_payment') {
            $invoice->member->update(['status' => 'active']);
        }

        $invoice->reservation?->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $invoice->invitations()->update([
            'status' => 'available',
        ]);
    }

    private function registerCashEntry(Invoice $invoice, Payment $payment): void
    {
        CashEntry::create([
            'type' => 'income',
            'category' => match ($invoice->type) {
                'reservation' => 'Reservas',
                'invitation' => 'Convites',
                'monthly', 'membership_initial' => 'Mensalidades',
                default => 'Cobranças avulsas',
            },
            'description' => 'Baixa '.$invoice->number.' - '.$invoice->description,
            'amount' => $payment->amount,
            'entry_date' => $payment->paid_at?->toDateString() ?? today(),
            'status' => 'confirmed',
        ]);
    }
}
