<?php

namespace App\Services;

use App\Models\Dependent;
use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Support\BrazilianMasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class MemberImportService
{
    public function import(UploadedFile $file, ?User $user = null): ImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw ValidationException::withMessages([
                'file' => 'Envie um arquivo CSV ou XLSX.',
            ]),
        };

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'file' => 'A planilha precisa ter cabeçalho e ao menos uma linha de dados.',
            ]);
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), array_shift($rows));

        return DB::transaction(function () use ($file, $user, $rows, $headers) {
            $batch = ImportBatch::create([
                'created_by_user_id' => $user?->id,
                'type' => 'members',
                'filename' => $file->getClientOriginalName(),
                'status' => 'processing',
                'total_rows' => count($rows),
            ]);

            foreach ($rows as $index => $row) {
                $payload = $this->combineRow($headers, $row);

                if ($this->isBlankRow($payload)) {
                    continue;
                }

                try {
                    $member = $this->importRow($payload);
                    $batch->rows()->create([
                        'row_number' => $index + 2,
                        'status' => 'success',
                        'identifier' => $member->membership_code,
                        'message' => 'Associado importado/atualizado.',
                        'payload' => $payload,
                    ]);
                    $batch->increment('success_rows');
                } catch (\Throwable $exception) {
                    $batch->rows()->create([
                        'row_number' => $index + 2,
                        'status' => 'failed',
                        'identifier' => $payload['cpf'] ?? $payload['matricula'] ?? null,
                        'message' => $exception->getMessage(),
                        'payload' => $payload,
                    ]);
                    $batch->increment('failed_rows');
                }
            }

            $batch->update([
                'status' => $batch->failed_rows > 0 ? 'finished_with_errors' : 'finished',
                'summary' => [
                    'success' => $batch->success_rows,
                    'failed' => $batch->failed_rows,
                ],
                'finished_at' => now(),
            ]);

            return $batch->fresh(['rows']);
        });
    }

    private function importRow(array $payload): Member
    {
        $name = $this->value($payload, ['nome', 'name', 'associado']);
        $cpf = BrazilianMasks::formatCpf($this->value($payload, ['cpf', 'documento']));

        if (! $name || ! $cpf) {
            throw new \InvalidArgumentException('Nome e CPF são obrigatórios.');
        }

        if (! BrazilianMasks::hasCpfLength($cpf)) {
            throw new \InvalidArgumentException('CPF precisa ter 11 números.');
        }

        $plan = $this->findPlan($this->value($payload, ['plano', 'plan']));
        $membershipCode = $this->value($payload, ['matricula', 'codigo', 'membership_code'])
            ?: 'AABB-'.Str::upper(Str::random(6));

        $member = Member::updateOrCreate(
            ['cpf' => $cpf],
            [
                'plan_id' => $plan?->id,
                'membership_code' => $membershipCode,
                'name' => $name,
                'email' => $this->value($payload, ['email', 'e_mail']),
                'phone' => BrazilianMasks::formatPhone($this->value($payload, ['telefone', 'phone', 'celular'])),
                'status' => $this->value($payload, ['status']) ?: 'active',
                'category' => $this->value($payload, ['categoria', 'category']) ?: 'Familiar',
                'billing_due_day' => $this->intValue($payload, ['vencimento', 'dia_vencimento']),
                'membership_type' => $this->value($payload, ['tipo', 'membership_type']) ?: 'associate',
                'joined_at' => now(),
                'imported_at' => now(),
                'notes' => $this->value($payload, ['observacoes', 'notes']),
            ],
        );

        $dependentName = $this->value($payload, ['dependente', 'dependente_nome', 'nome_dependente']);

        if ($dependentName) {
            $dependentCpf = BrazilianMasks::formatCpf($this->value($payload, ['dependente_cpf', 'cpf_dependente']));

            Dependent::updateOrCreate(
                [
                    'member_id' => $member->id,
                    'cpf' => $dependentCpf,
                ],
                [
                    'name' => $dependentName,
                    'relationship' => $this->value($payload, ['parentesco']) ?: 'Dependente',
                    'status' => 'active',
                    'is_free' => true,
                    'access_status' => 'allowed',
                ],
            );
        }

        return $member;
    }

    private function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        $delimiter = substr_count((string) strtok($content, "\n"), ';') > substr_count((string) strtok($content, "\n"), ',') ? ';' : ',';
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($value) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Não foi possível abrir o XLSX.']);
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheet) {
            throw ValidationException::withMessages(['file' => 'O XLSX precisa ter dados na primeira aba.']);
        }

        $xml = simplexml_load_string($sheet);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $index = $this->columnIndex($column);
                $type = (string) $cell['t'];
                $raw = (string) ($cell->v ?? '');

                $values[$index] = match ($type) {
                    's' => $sharedStrings[(int) $raw] ?? '',
                    'inlineStr' => (string) ($cell->is->t ?? ''),
                    default => $raw,
                };
            }

            ksort($values);
            $rows[] = array_values($values);
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $xml, $items);

        return array_map(function ($item) {
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $item, $texts);

            return html_entity_decode(implode('', $texts[1] ?? []));
        }, $items[1] ?? []);
    }

    private function combineRow(array $headers, array $row): array
    {
        $payload = [];

        foreach ($headers as $index => $header) {
            if ($header) {
                $payload[$header] = trim((string) ($row[$index] ?? ''));
            }
        }

        return $payload;
    }

    private function findPlan(?string $name): ?Plan
    {
        if (! $name) {
            return Plan::where('is_active', true)->first();
        }

        return Plan::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?: Plan::where('is_active', true)->first();
    }

    private function value(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (($payload[$key] ?? '') !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    private function intValue(array $payload, array $keys): ?int
    {
        $value = $this->value($payload, $keys);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::ascii(trim(mb_strtolower($header)));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim((string) $header, '_');
    }

    private function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }

    private function isBlankRow(array $payload): bool
    {
        return collect($payload)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty();
    }
}
