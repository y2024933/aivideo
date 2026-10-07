<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * 一次合規掃描的完整結果。
 *
 * rulesVersion / rulesFingerprint 必須一起存進 DB：version 是人工遞增的（會忘記改），
 * fingerprint 是程式算的（不會忘）。兩者不一致就代表規則被改過但沒遞增版本號。
 */
final class ComplianceReport extends Data
{
    /**
     * @param  list<ComplianceFinding>  $findings
     * @param  bool  $profileBlocked  此商品分類依法不可做（仍會回傳完整 findings 給 operator 看）
     */
    public function __construct(
        public string $profile,
        public string $rulesVersion,
        public string $rulesFingerprint,
        #[DataCollectionOf(ComplianceFinding::class)]
        public array $findings,
        public CarbonImmutable $checkedAt,
        public bool $profileBlocked = false,
        public ?string $profileBlockedReason = null,
    ) {}

    /** @return list<ComplianceFinding> */
    public function blocking(): array
    {
        return array_values(array_filter($this->findings, fn (ComplianceFinding $f) => $f->severity === 'blocking'));
    }

    /** @return list<ComplianceFinding> */
    public function warnings(): array
    {
        return array_values(array_filter($this->findings, fn (ComplianceFinding $f) => $f->severity === 'warning'));
    }

    /** @return list<ComplianceFinding> */
    public function unacknowledgedWarnings(): array
    {
        return array_values(array_filter($this->warnings(), fn (ComplianceFinding $f) => ! $f->acknowledged));
    }

    /** 可以進渲染流程：分類沒被擋、沒有 blocking、所有 warning 都已人工確認 */
    public function passed(): bool
    {
        return ! $this->profileBlocked && $this->blocking() === [] && $this->unacknowledgedWarnings() === [];
    }

    /** @return array<string, list<ComplianceFinding>> field => findings */
    public function byField(): array
    {
        $grouped = [];
        foreach ($this->findings as $finding) {
            $grouped[$finding->field][] = $finding;
        }

        return $grouped;
    }

    /**
     * 依 shot 代號分組（'S02.subtitle' => 'S02'）。
     * 非 shot 欄位（caption / hashtags / title）不納入。
     *
     * @return array<string, list<ComplianceFinding>>
     */
    public function byShot(): array
    {
        $grouped = [];
        foreach ($this->findings as $finding) {
            if (preg_match('/^(S\d+)\./i', $finding->field, $m) === 1) {
                $grouped[$m[1]][] = $finding;
            }
        }

        return $grouped;
    }
}
