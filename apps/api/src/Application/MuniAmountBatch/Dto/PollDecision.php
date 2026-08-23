<?php

declare(strict_types=1);

namespace Application\MuniAmountBatch\Dto;

/**
 * 更新検知ポーリング（設計書 §5.0）の判定結果
 */
final readonly class PollDecision
{
    public const ACTION_NOT_INITIALIZED = 'not_initialized'; // snapshot_meta が空。先に初回取込が必要
    public const ACTION_REIMPORT_GAPS = 'reimport_gaps';      // 現在のスナップショット期間が1都3県揃っていない→穴埋め再取込
    public const ACTION_NEW_PERIOD_DETECTED = 'new_period_detected'; // 次の四半期のデータを検知→新規取込
    public const ACTION_NONE = 'none';                        // 現在は揃っており、次の四半期もまだ未公開

    /**
     * @param string $action 上記 ACTION_* のいずれか
     * @param string|null $period アクション対象の period（ACTION_NOT_INITIALIZED/NONE時はnull）
     * @param string[] $missingPrefectureCodes reimport_gaps の場合、未取得の都道府県コード
     * @param array<string, bool> $probedPrefectures new_period_detected/none の場合のプローブ結果（都道府県コード => データありか）
     */
    private function __construct(
        public string $action,
        public ?string $period,
        public array $missingPrefectureCodes = [],
        public array $probedPrefectures = [],
    ) {
    }

    public static function notInitialized(): self
    {
        return new self(self::ACTION_NOT_INITIALIZED, null);
    }

    /**
     * @param string[] $missingPrefectureCodes
     */
    public static function reimportGaps(string $period, array $missingPrefectureCodes): self
    {
        return new self(self::ACTION_REIMPORT_GAPS, $period, missingPrefectureCodes: $missingPrefectureCodes);
    }

    /**
     * @param array<string, bool> $probedPrefectures
     */
    public static function newPeriodDetected(string $period, array $probedPrefectures): self
    {
        return new self(self::ACTION_NEW_PERIOD_DETECTED, $period, probedPrefectures: $probedPrefectures);
    }

    /**
     * @param array<string, bool> $probedPrefectures
     */
    public static function none(string $period, array $probedPrefectures): self
    {
        return new self(self::ACTION_NONE, $period, probedPrefectures: $probedPrefectures);
    }

    public function requiresImport(): bool
    {
        return in_array($this->action, [self::ACTION_REIMPORT_GAPS, self::ACTION_NEW_PERIOD_DETECTED], true);
    }
}
