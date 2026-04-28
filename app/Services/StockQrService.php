<?php

namespace App\Services;

use App\Models\Product;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Str;

class StockQrService
{
    public function ensureToken(Product $product): Product
    {
        if ($product->qr_token && $product->sku) {
            return $product;
        }

        $product->forceFill([
            'qr_token' => $product->qr_token ?: (string) Str::uuid(),
            'sku' => $product->sku ?: 'AABB-EST-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
        ])->save();

        return $product->refresh();
    }

    public function productUrl(Product $product): string
    {
        $this->ensureToken($product);

        return route('team.stock.product.show', ['token' => $product->qr_token]);
    }

    public function qrCodeDataUri(Product $product): string
    {
        return (new QRCode)->render($this->productUrl($product));
    }

    public function shortCode(Product $product): string
    {
        $this->ensureToken($product);

        return $product->sku ?: Str::upper(Str::substr((string) $product->qr_token, 0, 8));
    }
}
