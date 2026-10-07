<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductImage extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_selected' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function aspectRatio(): ?float
    {
        return $this->width && $this->height ? $this->height / $this->width : null;
    }

    /**
     * 長圖（高寬比 >= 1.5）用 cover 填滿，其餘用 contain 避免裁切商品
     */
    public function suggestedFit(): string
    {
        return ($this->aspectRatio() ?? 1.0) >= 1.5 ? 'cover' : 'contain';
    }
}
