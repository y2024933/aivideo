<?php

declare(strict_types=1);

use App\Data\ComplianceFinding;
use App\Data\ComplianceReport;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Compliance\TraditionalChineseValidator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->checker = new AdComplianceChecker(new TraditionalChineseValidator());
});

/** 取出指定規則的 findings */
function rule(ComplianceReport $report, string $ruleId): array
{
    return array_values(array_filter($report->findings, fn (ComplianceFinding $f) => $f->ruleId === $ruleId));
}

function finding(array $overrides = []): ComplianceFinding
{
    return ComplianceFinding::from($overrides + [
        'field' => 'caption', 'offset' => 0, 'length' => 2, 'matched' => '保證',
        'ruleId' => 'guarantee', 'severity' => 'warning', 'category' => 'guarantee',
        'law' => '公平交易法 §21', 'message' => 'm',
    ]);
}

function reportOf(array $findings): ComplianceReport
{
    return new ComplianceReport('general', 'v', 'f', $findings, CarbonImmutable::now());
}

/*
|--------------------------------------------------------------------------
| 醫療效能 / profile 繼承
|--------------------------------------------------------------------------
*/
it('blocks medical verbs on supplement profile', function () {
    expect(rule($this->checker->check(['S01.subtitle' => '改善過敏'], 'supplement'), 'medical_verb'))
        ->toHaveCount(1)
        ->and(rule($this->checker->check(['S01.subtitle' => '改善過敏'], 'supplement'), 'medical_verb')[0]->severity)
        ->toBe('blocking');
});

it('also blocks medical verbs on 3c because 3c extends general', function () {
    $findings = rule($this->checker->check(['S01.subtitle' => '改善過敏'], '3c'), 'medical_verb');

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe('blocking')
        ->and($findings[0]->matched)->toBe('改善過敏');
});

it('isolates profile specific rules', function () {
    // health_food_function 只掛在 supplement，3c 不該有
    expect(rule($this->checker->check(['S01.subtitle' => '調節血脂'], 'supplement'), 'health_food_function'))->toHaveCount(1)
        ->and(rule($this->checker->check(['S01.subtitle' => '調節血脂'], '3c'), 'health_food_function'))->toBe([]);
});

it('resolves rules through a multi level extends chain', function () {
    $rules = $this->checker->resolveRules('supplement');

    expect($rules)
        ->toContain('medical_verb')            // general
        ->toContain('health_food_name')        // food
        ->toContain('health_food_function')    // supplement
        ->and($rules)->toBe(array_values(array_unique($rules)));
});

/*
|--------------------------------------------------------------------------
| 公平交易法
|--------------------------------------------------------------------------
*/
it('flags guarantee as blocking and superlative as warning', function () {
    $report = $this->checker->check(['S01.subtitle' => '保證最便宜'], 'general');

    expect(rule($report, 'guarantee')[0]->severity)->toBe('blocking')
        ->and(rule($report, 'guarantee')[0]->matched)->toBe('保證')
        ->and(rule($report, 'superlative'))->not->toBeEmpty()
        ->and(rule($report, 'superlative')[0]->severity)->toBe('warning');
});

/*
|--------------------------------------------------------------------------
| context_whitelist
|--------------------------------------------------------------------------
*/
it('does not flag officially allowed cosmetic phrases', function () {
    expect(rule($this->checker->check(['S01.subtitle' => '舒緩肌膚乾燥'], 'cosmetic'), 'medical_verb'))->toBe([])
        ->and($this->checker->check(['S01.subtitle' => '舒緩肌膚乾燥'], 'cosmetic')->blocking())->toBe([]);
});

it('still flags the same verb outside the whitelisted context', function () {
    expect($this->checker->check(['S01.subtitle' => '舒緩過敏症狀'], 'cosmetic')->blocking())->not->toBeEmpty();
});

it('masks context whitelist phrases before matching blacklisted words', function () {
    config()->set('compliance.rules.ctx_probe', [
        'severity' => 'blocking', 'category' => 'test', 'law' => 'L', 'message' => 'M',
        'words' => ['舒緩'],
        'context_whitelist' => ['舒緩肌膚'],
    ]);
    config()->set('compliance.profiles.ctx_probe', ['rules' => ['ctx_probe']]);

    expect(rule($this->checker->check(['caption' => '舒緩肌膚乾燥'], 'ctx_probe'), 'ctx_probe'))->toBe([])
        ->and(rule($this->checker->check(['caption' => '舒緩過敏'], 'ctx_probe'), 'ctx_probe'))->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| soft_words 升級
|--------------------------------------------------------------------------
*/
it('keeps a lone disease name as warning', function () {
    $findings = rule($this->checker->check(['S01.subtitle' => '冬天不怕感冒'], 'general'), 'disease_name');

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe('warning')
        ->and($findings[0]->matched)->toBe('感冒');
});

it('upgrades a disease name to blocking when the same field also has a medical verb', function () {
    $findings = rule($this->checker->check(['S01.subtitle' => '預防感冒'], 'general'), 'disease_name');

    expect($findings[0]->severity)->toBe('blocking');
});

/*
|--------------------------------------------------------------------------
| downgrade_if_matches / only_when
|--------------------------------------------------------------------------
*/
it('downgrades health food claims when a valid permit number is present', function () {
    $findings = rule($this->checker->check(['S01.subtitle' => '調節血脂（衛部健食字第A00123號）'], 'supplement'), 'health_food_function');

    expect($findings)->not->toBeEmpty()
        ->and(collect($findings)->pluck('severity')->unique()->all())->toBe(['warning']);
});

it('applies only_when rules solely in ai generated mode', function () {
    expect(rule($this->checker->check(['S01.subtitle' => '親測有效'], 'general', ['is_ai_generated' => false]), 'fake_testimonial'))->toBe([]);

    $findings = rule($this->checker->check(['S01.subtitle' => '親測有效'], 'general', ['is_ai_generated' => true]), 'fake_testimonial');

    expect($findings)->toHaveCount(1)->and($findings[0]->severity)->toBe('blocking');
});

/*
|--------------------------------------------------------------------------
| 揭露
|--------------------------------------------------------------------------
*/
it('requires the disclosure prefix on the first line of the caption', function () {
    $missing = rule($this->checker->check(['caption' => '超好用的耳機'], 'general'), 'disclosure_required');

    expect($missing)->toHaveCount(1)
        ->and($missing[0]->severity)->toBe('blocking')
        ->and($missing[0]->field)->toBe('caption');
});

it('accepts a caption whose first line is the disclosure prefix', function () {
    $caption = config('compliance.disclosure_prefix')."\n超好用的耳機 #開箱";

    expect(rule($this->checker->check(['caption' => $caption], 'general'), 'disclosure_required'))->toBe([]);
});

it('rejects a disclosure prefix that is not on the first line', function () {
    $caption = "超好用的耳機\n".config('compliance.disclosure_prefix');

    expect(rule($this->checker->check(['caption' => $caption], 'general'), 'disclosure_required'))->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| blocked profile
|--------------------------------------------------------------------------
*/
it('still lists findings for a blocked profile', function () {
    $report = $this->checker->check(['caption' => '這是一支隱形眼鏡開箱影片'], 'restricted');

    expect($report->profileBlocked)->toBeTrue()
        ->and($report->profileBlockedReason)->not->toBeEmpty()
        ->and($report->findings)->not->toBeEmpty()
        ->and($report->passed())->toBeFalse();
});

it('inherits blocked state from the extends chain', function () {
    expect($this->checker->check(['caption' => 'x'], 'supplement')->profileBlocked)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| profileForCategory
|--------------------------------------------------------------------------
*/
it('maps product categories to profiles', function (?string $category, string $expected) {
    expect($this->checker->profileForCategory($category))->toBe($expected);
})->with([
    ['保健食品/維他命', 'supplement'],
    ['手機平板與周邊', '3c'],
    ['隱形眼鏡', 'restricted'],
    ['美妝保養', 'cosmetic'],
    [null, 'general'],
    ['', 'general'],
    ['不存在的分類', 'general'],
]);

it('prefers restricted over any other match', function () {
    // 同時含「手機」（3c）與「電子煙」（restricted）時必須回 restricted
    expect($this->checker->profileForCategory('手機配件', ['電子煙']))->toBe('restricted');
});

it('prefers the longest category key', function () {
    expect($this->checker->profileForCategory('保健食品'))->toBe('supplement')
        ->and($this->checker->profileForCategory('食品'))->toBe('food');
});

/*
|--------------------------------------------------------------------------
| mergeAcknowledged
|--------------------------------------------------------------------------
*/
it('keeps acknowledged when rule, field, matched, severity and law all match', function () {
    $old = reportOf([finding(['acknowledged' => true])])->toArray();
    $merged = $this->checker->mergeAcknowledged(reportOf([finding()]), $old);

    expect($merged->findings[0]->acknowledged)->toBeTrue()
        ->and($merged->passed())->toBeTrue();
});

it('clears acknowledged when severity escalates from warning to blocking', function () {
    // 法規變嚴時，三個月前按過的「已確認無誤」不可以讓現在已是 blocking 的違規悄悄通過
    $old = reportOf([finding(['severity' => 'warning', 'acknowledged' => true])])->toArray();
    $merged = $this->checker->mergeAcknowledged(reportOf([finding(['severity' => 'blocking'])]), $old);

    expect($merged->findings[0]->acknowledged)->toBeFalse();
});

it('clears acknowledged when the law reference changes', function () {
    $old = reportOf([finding(['acknowledged' => true, 'law' => '公平交易法 §21'])])->toArray();
    $merged = $this->checker->mergeAcknowledged(reportOf([finding(['law' => '公平交易法 §21 §24'])]), $old);

    expect($merged->findings[0]->acknowledged)->toBeFalse();
});

it('clears acknowledged when the matched text changes', function () {
    $old = reportOf([finding(['acknowledged' => true, 'matched' => '保證'])])->toArray();
    $merged = $this->checker->mergeAcknowledged(reportOf([finding(['matched' => '絕對有效'])]), $old);

    expect($merged->findings[0]->acknowledged)->toBeFalse();
});

it('never acknowledges a blocking finding', function () {
    $old = reportOf([finding(['severity' => 'blocking', 'acknowledged' => true])])->toArray();
    $merged = $this->checker->mergeAcknowledged(reportOf([finding(['severity' => 'blocking'])]), $old);

    expect($merged->findings[0]->acknowledged)->toBeFalse();
});

it('tolerates a null or bare old report', function () {
    expect($this->checker->mergeAcknowledged(reportOf([finding()]), null)->findings[0]->acknowledged)->toBeFalse()
        ->and($this->checker->mergeAcknowledged(reportOf([finding()]), ['findings' => [finding(['acknowledged' => true])->toArray()]])->findings[0]->acknowledged)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| report 行為
|--------------------------------------------------------------------------
*/
it('passes only when there is no blocking and every warning is acknowledged', function () {
    expect(reportOf([])->passed())->toBeTrue()
        ->and(reportOf([finding(['severity' => 'warning'])])->passed())->toBeFalse()
        ->and(reportOf([finding(['severity' => 'warning', 'acknowledged' => true])])->passed())->toBeTrue()
        ->and(reportOf([finding(['severity' => 'blocking'])])->passed())->toBeFalse();
});

it('groups findings by field and by shot', function () {
    $report = reportOf([
        finding(['field' => 'S02.subtitle']),
        finding(['field' => 'S02.voiceover']),
        finding(['field' => 'caption']),
    ]);

    expect(array_keys($report->byField()))->toBe(['S02.subtitle', 'S02.voiceover', 'caption'])
        ->and(array_keys($report->byShot()))->toBe(['S02'])
        ->and($report->byShot()['S02'])->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| 版本與指紋
|--------------------------------------------------------------------------
*/
it('produces a stable 12 char rules fingerprint', function () {
    expect($this->checker->rulesFingerprint())->toHaveLength(12)
        ->and($this->checker->rulesFingerprint())->toBe($this->checker->rulesFingerprint())
        ->and($this->checker->rulesVersion())->toBe(config('compliance.version'));
});

it('changes the fingerprint when a rule changes', function () {
    $before = $this->checker->rulesFingerprint();
    config()->set('compliance.rules.guarantee.words', ['保證']);

    expect($this->checker->rulesFingerprint())->not->toBe($before);
});

it('stamps version and fingerprint on every report', function () {
    $report = $this->checker->check(['caption' => 'x'], 'general');

    expect($report->rulesVersion)->toBe(config('compliance.version'))
        ->and($report->rulesFingerprint)->toHaveLength(12)
        ->and($report->profile)->toBe('general');
});

it('falls back to the general profile for an unknown profile name', function () {
    expect($this->checker->check(['caption' => 'x'], 'no_such_profile')->profile)->toBe('general');
});
