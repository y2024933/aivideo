<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KenBurns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Shot extends Model
{
    use HasFactory, HasUuids;

    /** Auto 模式輪替用的運鏡序列 */
    private const AUTO_KEN_BURNS = [
        KenBurns::ZoomIn,
        KenBurns::PanRight,
        KenBurns::ZoomOut,
        KenBurns::PanLeft,
        KenBurns::ZoomInPanUp,
        KenBurns::ZoomOutPanDown,
    ];

    protected $guarded = [];

    protected $casts = [
        'compliance_flags' => 'array',
        'duration_seconds' => 'decimal:1',
        'voiceover_duration_sec' => 'decimal:1',
        'video_cost_usd' => 'decimal:4',
        'subtitle_has_simplified' => 'boolean',
        'voiceover_has_simplified' => 'boolean',
        'is_approved' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class);
    }

    /**
     * Remotion 實際要吃的素材 URL：有 B-roll 影片優先，否則用商品圖
     */
    public function renderableUrl(): ?string
    {
        return $this->video_status === 'done' ? $this->video_remote_url : $this->image_remote_url;
    }

    public function effectiveTransition(): string
    {
        return $this->transition ?: ($this->product?->global_transition ?? 'crossfade');
    }

    public function effectiveKenBurns(int $index): string
    {
        $value = $this->ken_burns ?: ($this->product?->default_ken_burns ?? 'auto');

        return $value === KenBurns::Auto->value
            ? self::AUTO_KEN_BURNS[$index % count(self::AUTO_KEN_BURNS)]->value
            : $value;
    }
}
