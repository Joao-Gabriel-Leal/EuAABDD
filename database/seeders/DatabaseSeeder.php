<?php

namespace Database\Seeders;

use App\Models\AccessLog;
use App\Models\Announcement;
use App\Models\Benefit;
use App\Models\CashEntry;
use App\Models\ChargeItem;
use App\Models\Dependent;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = collect([
            Plan::create([
                'name' => 'Efetivo',
                'segment' => 'Funcionarios BB ativos e aposentados',
                'monthly_family' => 239,
                'monthly_individual' => 189,
                'monthly_under_30' => 166,
                'monthly_special' => null,
                'included_guests' => 4,
                'included_dependents' => 2,
                'extra_guest_price' => 28,
            ]),
            Plan::create([
                'name' => 'Comunitario',
                'segment' => 'Comunidade AABB Brasilia',
                'monthly_family' => 354,
                'monthly_individual' => 284,
                'monthly_under_30' => 249,
                'monthly_special' => 189,
                'included_guests' => 4,
                'included_dependents' => 2,
                'extra_guest_price' => 32,
            ]),
        ]);

        $spaces = collect([
            ReservableSpace::create([
                'name' => 'Churrasqueira Lago Sul',
                'type' => 'churrasqueira',
                'location' => 'Proxima aos complexos aquaticos',
                'capacity' => 32,
                'base_price' => 380,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/12/Churrasqueira05-scaled.jpg',
                'rules' => ['lista_obrigatoria' => true, 'pagamento' => 'associado_responsavel'],
            ]),
            ReservableSpace::create([
                'name' => 'Espaco Bosque',
                'type' => 'evento',
                'location' => 'Area verde',
                'capacity' => 80,
                'base_price' => 720,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/camposequadras.jpg',
                'rules' => ['lista_obrigatoria' => true, 'pagamento' => 'parcial_convidados'],
            ]),
            ReservableSpace::create([
                'name' => 'Complexo Aquatico',
                'type' => 'lazer',
                'location' => 'Piscinas',
                'capacity' => 120,
                'base_price' => 0,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/complexosaquaticos.jpg',
                'rules' => ['reserva' => false, 'acesso' => 'beneficio_associado'],
            ]),
        ]);

        foreach ([
            ['Mensalidade maio/2026', 'monthly', 239],
            ['Reserva de churrasqueira', 'reservation', 380],
            ['Convite excedente', 'invitation', 28],
            ['Exame medico', 'medical_exam', 65],
            ['Carteirinha adicional', 'card', 35],
        ] as [$name, $type, $amount]) {
            ChargeItem::create(compact('name', 'type', 'amount'));
        }

        $members = collect([
            ['AABB-0001', 'Carlos Pereira', '111.222.333-44', 'carlos.associado@aabb.demo', 'Comunitario', 'Familiar'],
            ['AABB-0002', 'Ana Paula Souza', '222.333.444-55', 'ana.souza@aabb.demo', 'Efetivo', 'Familiar'],
            ['AABB-0003', 'Leonardo Gadelha', '333.444.555-66', 'leo.gadelha@aabb.demo', 'Comunitario', 'Individual'],
            ['AABB-0004', 'Marina Costa', '444.555.666-77', 'marina.costa@aabb.demo', 'Efetivo', 'Individual 30 Menos'],
        ])->map(function ($data) use ($plans) {
            [$code, $name, $cpf, $email, $planName, $category] = $data;
            $plan = $plans->firstWhere('name', $planName);

            return Member::create([
                'plan_id' => $plan->id,
                'membership_code' => $code,
                'name' => $name,
                'cpf' => $cpf,
                'email' => $email,
                'phone' => '(61) 3223-0078',
                'status' => 'active',
                'category' => $category,
                'joined_at' => now()->subMonths(rand(5, 48)),
                'photo_url' => 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=083b82&color=fff',
                'address' => ['cidade' => 'Brasilia', 'uf' => 'DF'],
            ]);
        });

        User::create([
            'name' => 'Equipe AABB',
            'email' => 'equipe@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'team',
        ]);

        User::create([
            'name' => 'Carlos Associado',
            'email' => 'associado@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'member',
            'member_id' => $members->first()->id,
        ]);

        foreach ($members as $index => $member) {
            Dependent::create([
                'member_id' => $member->id,
                'name' => ['Lucas Pereira', 'Clara Souza', 'Miguel Gadelha', 'Julia Costa'][$index],
                'cpf' => null,
                'birthdate' => now()->subYears(12 + $index),
                'relationship' => 'Filho(a)',
                'status' => 'active',
            ]);

            $monthlyAmount = $member->category === 'Familiar'
                ? $member->plan->monthly_family
                : ($member->category === 'Individual 30 Menos' ? $member->plan->monthly_under_30 : $member->plan->monthly_individual);

            $invoice = Invoice::create([
                'member_id' => $member->id,
                'number' => 'AABB-2026-05-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT),
                'type' => 'monthly',
                'description' => 'Mensalidade maio/2026 - '.$member->plan->name.' '.$member->category,
                'amount' => $monthlyAmount,
                'due_date' => now()->startOfMonth()->addDays(14),
                'status' => $index === 0 ? 'pending' : 'paid',
                'paid_at' => $index === 0 ? null : now()->subDays($index),
                'payment_method' => $index === 0 ? 'QR/App' : 'Boleto digital',
                'metadata' => ['gateway' => 'simulado', 'brb_debito' => $index === 1],
            ]);

            if ($invoice->status === 'paid') {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount,
                    'method' => $invoice->payment_method,
                    'status' => 'paid',
                    'transaction_code' => 'SIM-'.Str::upper(Str::random(8)),
                    'paid_at' => now()->subDays($index),
                ]);
            }
        }

        $reservationInvoice = Invoice::create([
            'member_id' => $members->first()->id,
            'number' => 'AABB-RES-0001',
            'type' => 'reservation',
            'description' => 'Reserva Churrasqueira Lago Sul com lista de convidados',
            'amount' => 520,
            'due_date' => now()->addDays(3),
            'status' => 'pending',
            'payment_method' => 'Pix/QR simulado',
            'metadata' => ['link_pagamento' => '/portal/pagamentos'],
        ]);

        $reservation = Reservation::create([
            'member_id' => $members->first()->id,
            'reservable_space_id' => $spaces->first()->id,
            'invoice_id' => $reservationInvoice->id,
            'reservation_date' => now()->addDays(12),
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => 'pending_payment',
            'total_amount' => 520,
            'notes' => 'Lista aberta para convidados e cobranca vinculada ao associado.',
        ]);

        foreach (['Joao Henrique', 'Marina Almeida', 'Pedro Martins', 'Bianca Reis', 'Rafael Lima'] as $i => $guestName) {
            $guest = Guest::create([
                'reservation_id' => $reservation->id,
                'member_id' => $members->first()->id,
                'name' => $guestName,
                'cpf' => '000.000.000-0'.$i,
                'is_extra' => $i >= 4,
                'amount' => $i >= 4 ? 28 : 0,
                'status' => $i >= 4 ? 'awaiting_payment' : 'confirmed',
            ]);

            Invitation::create([
                'member_id' => $members->first()->id,
                'guest_id' => $guest->id,
                'invoice_id' => $i >= 4 ? $reservationInvoice->id : null,
                'valid_for' => $reservation->reservation_date,
                'status' => $i >= 4 ? 'extra_pending' : 'used',
                'is_extra' => $i >= 4,
            ]);
        }

        foreach ([
            ['Carvao premium', 'Churrasqueiras', 42, 15, 'saco'],
            ['Pulseiras de convidados', 'Portaria', 180, 50, 'un'],
            ['Produtos de limpeza piscina', 'Complexo aquatico', 12, 18, 'un'],
            ['Agua mineral 500ml', 'Eventos', 320, 100, 'un'],
        ] as [$name, $category, $quantity, $minimum, $unit]) {
            $product = Product::create([
                'name' => $name,
                'category' => $category,
                'quantity' => $quantity,
                'minimum_quantity' => $minimum,
                'unit' => $unit,
            ]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'entry',
                'quantity' => $quantity,
                'reason' => 'Carga inicial do demo',
            ]);
        }

        foreach ([
            ['income', 'Mensalidades', 'Recebimentos maio', 132000],
            ['income', 'Reservas', 'Reservas e listas de convidados', 8200],
            ['income', 'Convites', 'Convites excedentes', 2184],
            ['expense', 'Manutencao', 'Complexos aquaticos e areas sociais', 18400],
            ['expense', 'Eventos', 'Preparacao festa junina', 12600],
        ] as [$type, $category, $description, $amount]) {
            CashEntry::create([
                'type' => $type,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'entry_date' => now()->subDays(rand(1, 20)),
                'status' => 'confirmed',
            ]);
        }

        foreach ([
            ['COMUNICADO - Reajuste anual de mensalidades 2026', 'comunicado-reajuste-anual-2026', 'Comunicado', 'Novos valores de mensalidade a partir de maio/2026.', 'https://aabbdf.com.br/wp-content/uploads/2026/04/WhatsApp-Image-2026-04-02-at-12.11.28.jpeg', true],
            ['Festa Junina confirmada em junho', 'festa-junina-confirmada', 'Evento', 'Programacao especial para associados, familia e convidados.', 'https://aabbdf.com.br/wp-content/uploads/2026/04/WhatsApp-Image-2026-04-21-at-10.48.40.jpeg', true],
            ['Calendario esportivo AABB Brasilia', 'calendario-esportivo-aabb-brasilia', 'Esportes', 'Modalidades e campeonatos movimentando o clube.', 'https://aabbdf.com.br/wp-content/uploads/2025/05/FOTOS.jpg', false],
        ] as [$title, $slug, $category, $summary, $image, $featured]) {
            Announcement::create([
                'title' => $title,
                'slug' => $slug,
                'category' => $category,
                'summary' => $summary,
                'body' => $summary.' A publicacao foi cadastrada no demo para demonstrar a area de comunicados do clube.',
                'image_url' => $image,
                'published_at' => now()->subDays(rand(1, 8)),
                'is_featured' => $featured,
            ]);
        }

        foreach ([
            ['Complexos aquaticos', 'Lazer', 'Piscinas e areas de descanso integradas ao dia a dia do associado.', 'pool'],
            ['Churrasqueiras', 'Reservas', 'Espacos reservaveis com lista, convidados e pagamento no mesmo fluxo.', 'grill'],
            ['Esportes e escolinhas', 'Esportes', 'Modalidades para adultos, jovens e criancas.', 'sport'],
            ['Convites do mes', 'Acesso', 'Cotas por categoria com controle de excedentes.', 'ticket'],
        ] as [$title, $category, $description, $icon]) {
            Benefit::create(compact('title', 'category', 'description', 'icon'));
        }

        foreach ($members as $member) {
            AccessLog::create([
                'member_id' => $member->id,
                'person_name' => $member->name,
                'person_type' => 'associado',
                'gate' => 'Portaria principal',
                'status' => 'allowed',
                'checked_at' => CarbonImmutable::now()->subHours(rand(1, 48)),
            ]);
        }

        Proposal::create([
            'plan_id' => $plans->last()->id,
            'name' => 'Familia interessada no plano comunitario',
            'cpf' => '555.666.777-88',
            'email' => 'familia.demo@aabb.demo',
            'phone' => '(61) 99999-2026',
            'status' => 'analysis',
            'notes' => 'Lead vindo pelo site publico. Fluxo de proposta demonstrativo.',
        ]);
    }
}
