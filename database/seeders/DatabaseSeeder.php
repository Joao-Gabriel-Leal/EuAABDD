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
use App\Models\ReservableSpaceType;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mapAsset = database_path('seeders/assets/reservation-map.jpeg');

        if (is_file($mapAsset) && ($mapContents = file_get_contents($mapAsset)) !== false) {
            Storage::disk('public')->delete([
                'club-map/reservation-map.webp',
                'club-map/reservation-map.png',
                'club-map/reservation-map.jpg',
                'club-map/reservation-map.jpeg',
            ]);
            Storage::disk('public')->put('club-map/reservation-map.jpeg', $mapContents);
        }

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
                'monthly_due_day' => 8,
                'dependent_extra_price' => 35,
                'annual_discount_percent' => 8,
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
                'monthly_due_day' => 20,
                'dependent_extra_price' => 45,
                'annual_discount_percent' => 10,
            ]),
        ]);

        $spaceTypes = ReservableSpaceType::pluck('id', 'slug');

        $spaces = collect([
            [
                'name' => 'Churrasqueira Lago Sul',
                'type' => 'churrasqueira',
                'reservable_space_type_id' => $spaceTypes['churrasqueira'] ?? null,
                'location' => 'Conjunto de churrasqueiras',
                'capacity' => 32,
                'base_price' => 380,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/12/Churrasqueira05-scaled.jpg',
                'rules' => ['lista_obrigatoria' => true, 'pagamento' => 'associado_responsavel', 'included_guests' => 4, 'guest_price' => 14, 'starts_at' => '12:00', 'ends_at' => '18:00', 'map_x' => 31, 'map_y' => 53, 'map_note' => 'Conjunto de churrasqueiras'],
            ],
            [
                'name' => 'Espaco Bosque',
                'type' => 'evento',
                'reservable_space_type_id' => $spaceTypes['evento'] ?? null,
                'location' => 'Area verde e playground',
                'capacity' => 80,
                'base_price' => 720,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/camposequadras.jpg',
                'rules' => ['lista_obrigatoria' => true, 'pagamento' => 'parcial_convidados', 'included_guests' => 8, 'guest_price' => 14, 'starts_at' => '10:00', 'ends_at' => '22:00', 'map_x' => 75, 'map_y' => 50, 'map_note' => 'Area verde e playground'],
            ],
            [
                'name' => 'Complexo Aquatico',
                'type' => 'lazer',
                'reservable_space_type_id' => $spaceTypes['lazer'] ?? null,
                'location' => 'Piscinas centrais',
                'capacity' => 120,
                'base_price' => 0,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/complexosaquaticos.jpg',
                'rules' => ['reserva' => false, 'acesso' => 'beneficio_associado', 'included_guests' => 0, 'guest_price' => 14, 'map_x' => 55, 'map_y' => 38, 'map_note' => 'Piscinas centrais'],
            ],
            [
                'name' => 'Quadras de Tenis',
                'type' => 'quadra',
                'reservable_space_type_id' => $spaceTypes['quadra'] ?? null,
                'location' => 'Conjunto de quadras azuis',
                'capacity' => 4,
                'base_price' => 80,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/camposequadras.jpg',
                'rules' => ['lista_obrigatoria' => false, 'pagamento' => 'associado_responsavel', 'included_guests' => 0, 'guest_price' => 14, 'starts_at' => '07:00', 'ends_at' => '22:00', 'map_x' => 28, 'map_y' => 78, 'map_note' => 'Conjunto de quadras azuis'],
            ],
            [
                'name' => 'Quadra Poliesportiva',
                'type' => 'quadra',
                'reservable_space_type_id' => $spaceTypes['quadra'] ?? null,
                'location' => 'Quadra laranja e verde',
                'capacity' => 20,
                'base_price' => 120,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/camposequadras.jpg',
                'rules' => ['lista_obrigatoria' => false, 'pagamento' => 'associado_responsavel', 'included_guests' => 4, 'guest_price' => 14, 'starts_at' => '08:00', 'ends_at' => '22:00', 'map_x' => 57, 'map_y' => 78, 'map_note' => 'Quadra laranja e verde'],
            ],
            [
                'name' => 'Quadra de Areia',
                'type' => 'quadra',
                'reservable_space_type_id' => $spaceTypes['quadra'] ?? null,
                'location' => 'Quadra de areia',
                'capacity' => 12,
                'base_price' => 100,
                'image_url' => 'https://aabbdf.com.br/wp-content/uploads/2022/09/camposequadras.jpg',
                'rules' => ['lista_obrigatoria' => false, 'pagamento' => 'associado_responsavel', 'included_guests' => 4, 'guest_price' => 14, 'starts_at' => '08:00', 'ends_at' => '22:00', 'map_x' => 67, 'map_y' => 78, 'map_note' => 'Quadra de areia'],
            ],
        ])->map(fn (array $space) => ReservableSpace::updateOrCreate(
            ['name' => $space['name']],
            $space,
        ));

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
        ])->map(function ($data, $index) use ($plans) {
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
                'billing_due_day' => $planName === 'Efetivo' ? 8 : 20,
                'membership_type' => 'associate',
                'joined_at' => now()->subMonths(rand(5, 48)),
                'photo_url' => 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=083b82&color=fff',
                'address' => ['cidade' => 'Brasilia', 'uf' => 'DF'],
                'notes' => 'Associado demo com histórico operacional para apresentação.',
            ]);
        });

        User::create([
            'name' => 'Equipe AABB',
            'email' => 'equipe@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'team',
        ]);

        User::create([
            'name' => 'Financeiro AABB',
            'email' => 'financeiro@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'financeiro',
        ]);

        User::create([
            'name' => 'Secretaria AABB',
            'email' => 'secretaria@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'secretaria',
        ]);

        User::create([
            'name' => 'Portaria AABB',
            'email' => 'portaria@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'portaria',
        ]);

        User::create([
            'name' => 'Carlos Associado',
            'email' => 'associado@aabb.demo',
            'password' => Hash::make('aabb2026'),
            'role' => 'member',
            'member_id' => $members->first()->id,
        ]);

        $demoBillingMonth = CarbonImmutable::create(2026, 5, 1);

        foreach ($members as $index => $member) {
            Dependent::create([
                'member_id' => $member->id,
                'name' => ['Lucas Pereira', 'Clara Souza', 'Miguel Gadelha', 'Julia Costa'][$index],
                'cpf' => null,
                'birthdate' => now()->subYears(12 + $index),
                'relationship' => 'Filho(a)',
                'status' => 'active',
                'is_free' => true,
                'monthly_fee' => 0,
                'access_status' => 'allowed',
            ]);

            $monthlyAmount = $member->category === 'Familiar'
                ? $member->plan->monthly_family
                : ($member->category === 'Individual 30 Menos' ? $member->plan->monthly_under_30 : $member->plan->monthly_individual);

            $invoice = Invoice::create([
                'member_id' => $member->id,
                'number' => 'AABB-2026-05-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT),
                'type' => 'monthly',
                'billing_month' => $demoBillingMonth->toDateString(),
                'description' => 'Mensalidade maio/2026 - '.$member->plan->name.' '.$member->category,
                'amount' => $monthlyAmount,
                'due_date' => $demoBillingMonth->setDay($member->dueDay())->toDateString(),
                'status' => $index === 0 ? 'open' : 'paid',
                'paid_at' => $index === 0 ? null : now()->subDays($index),
                'payment_method' => $index === 0 ? 'QR App AABB' : 'Boleto Banco do Brasil',
                'issued_at' => now()->subDays(3),
                'reviewed_at' => $index === 0 ? null : now()->subDays($index),
                'metadata' => ['gateway' => 'bb_ready', 'bb_debito' => $index === 1],
            ]);

            if ($invoice->status === 'paid') {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount,
                    'method' => $invoice->payment_method,
                    'status' => 'paid',
                    'transaction_code' => 'BB-'.Str::upper(Str::random(8)),
                    'paid_at' => now()->subDays($index),
                    'received_at' => now()->subDays($index),
                ]);
            }
        }

        $reservationInvoice = Invoice::create([
            'member_id' => $members->first()->id,
            'number' => 'AABB-RES-0001',
            'type' => 'reservation',
            'description' => 'Reserva Churrasqueira Lago Sul com lista de convidados',
            'amount' => 450,
            'due_date' => now()->addDays(3),
            'status' => 'open',
            'payment_method' => 'QR App AABB',
            'issued_at' => now(),
            'metadata' => ['link_pagamento' => '/portal/pagamentos', 'pagamento' => 'associado_paga', 'valor_aluguel' => 380, 'valor_convidado' => 14, 'quantidade_convidados' => 5, 'valor_convidados' => 70, 'valor_total_reserva' => 450, 'meios_previstos' => ['boleto_banco_do_brasil', 'qr_app', 'cartao_presencial']],
        ]);

        $reservation = Reservation::create([
            'member_id' => $members->first()->id,
            'reservable_space_id' => $spaces->first()->id,
            'invoice_id' => $reservationInvoice->id,
            'reservation_date' => now()->addDays(12),
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => 'pending_payment',
            'total_amount' => 450,
            'guest_quota' => 4,
            'notes' => 'Lista aberta para convidados e cobranca vinculada ao associado.',
        ]);

        foreach (['Joao Henrique', 'Marina Almeida', 'Pedro Martins', 'Bianca Reis', 'Rafael Lima'] as $i => $guestName) {
            $code = 'AABB-'.Str::upper(Str::random(8));
            $guest = Guest::create([
                'reservation_id' => $reservation->id,
                'member_id' => $members->first()->id,
                'name' => $guestName,
                'cpf' => '000.000.000-0'.$i,
                'is_extra' => true,
                'amount' => 14,
                'status' => 'awaiting_payment',
                'invitation_code' => $code,
            ]);

            Invitation::create([
                'member_id' => $members->first()->id,
                'guest_id' => $guest->id,
                'invoice_id' => $reservationInvoice->id,
                'type' => 'reservation_guest',
                'code' => $code,
                'valid_for' => $reservation->reservation_date,
                'status' => 'payment_pending',
                'is_extra' => true,
                'amount' => 14,
            ]);
        }

        foreach ([
            ['Carvao premium', 'Churrasqueiras', 42, 15, 'saco', 26.90, 'Almoxarifado das churrasqueiras', 'Distribuidora Brasilia Grill'],
            ['Pulseiras de convidados', 'Portaria', 180, 50, 'un', 0.42, 'Guarita principal', 'Grafica credenciada'],
            ['Produtos de limpeza piscina', 'Complexo aquatico', 12, 18, 'un', 38.50, 'Casa de bombas', 'Manutencao piscinas DF'],
            ['Agua mineral 500ml', 'Eventos', 320, 100, 'un', 1.35, 'Deposito eventos', 'Bebidas Planalto'],
        ] as $index => [$name, $category, $quantity, $minimum, $unit, $unitCost, $location, $supplier]) {
            $product = Product::create([
                'name' => $name,
                'category' => $category,
                'quantity' => $quantity,
                'minimum_quantity' => $minimum,
                'unit' => $unit,
                'description' => 'Item de uso operacional do clube com controle por QR Code, custo e auditoria.',
                'location' => $location,
                'supplier' => $supplier,
                'unit_cost' => $unitCost,
            ]);

            StockMovement::create([
                'product_id' => $product->id,
                'movement_code' => 'MOV-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'type' => 'entry',
                'quantity' => $quantity,
                'quantity_before' => 0,
                'quantity_after' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
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
            'signature_status' => 'pending',
            'notes' => 'Lead vindo pelo site publico. Fluxo de proposta demonstrativo.',
        ]);
    }
}
