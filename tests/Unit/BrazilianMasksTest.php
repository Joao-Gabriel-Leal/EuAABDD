<?php

namespace Tests\Unit;

use App\Support\BrazilianMasks;
use PHPUnit\Framework\TestCase;

class BrazilianMasksTest extends TestCase
{
    public function test_formats_brazilian_documents_and_phones(): void
    {
        $this->assertSame('123.456.789-01', BrazilianMasks::formatCpf('12345678901'));
        $this->assertSame('12.345.678/0001-90', BrazilianMasks::formatCnpj('12345678000190'));
        $this->assertSame('12.345.678/0001-90', BrazilianMasks::formatCpfOrCnpj('12345678000190'));
        $this->assertSame('(61) 99999-9999', BrazilianMasks::formatPhone('61999999999'));
        $this->assertSame('(61) 3333-4444', BrazilianMasks::formatPhone('6133334444'));
    }
}
