<?php

declare(strict_types=1);

namespace Application\MuniAmountBatch\UseCase;

use Application\MuniAmountBatch\Dto\PollDecision;
use Domain\MunicipalityAmount\Repository\MunicipalityAmountRepositoryInterface;
use Domain\MunicipalityAmount\ValueObject\Period;
use Domain\Reinfolib\Repository\ReinfolibApiClientInterface;
use Domain\Reinfolib\ValueObject\PrefectureCode;
use Illuminate\Support\Facades\Log;
use Infrastructure\Models\SnapshotMetaModel;

/**
 * 更新検知ポーリング ユースケース（設計書 §5.0）
 *
 * 週1回呼び出される想定。取込ジョブ（ImportMuniAmountsUseCase）そのものは実行せず、
 * 「今回何をすべきか」を判定して返すだけに責務を絞る（実際の取込起動は呼び出し元コマンドが行う）。
 *
 * 判定は2段階：
 *   1. 現在のスナップショット期間（snapshot_meta.period）が1都3県すべて揃っているか
 *      → 揃っていなければ「穴埋め再取込」（部分公開への保険。設計書§5.0）
 *   2. 揃っていれば、次の四半期を1都3県それぞれプローブし、
 *      いずれか1つでも200（データあり）なら「新四半期を検知」→取込を起動
 */
final readonly class PollMuniAmountsUpdateUseCase
{
    public function __construct(
        private ReinfolibApiClientInterface $apiClient,
        private MunicipalityAmountRepositoryInterface $repository,
    ) {
    }

    public function execute(): PollDecision
    {
        $meta = SnapshotMetaModel::find(1);
        if ($meta === null) {
            Log::info('更新検知ポーリング: snapshot_meta が未初期化のため対象外（先に初回取込が必要）');

            return PollDecision::notInitialized();
        }

        $currentPeriod = new Period($meta->period);
        $missing = $this->missingPrefectureCodes($currentPeriod);

        if ($missing !== []) {
            Log::info('更新検知ポーリング: 現行期間が1都3県揃っていない→穴埋め再取込対象', [
                'period' => $currentPeriod->value(),
                'missingPrefectures' => $missing,
            ]);

            return PollDecision::reimportGaps($currentPeriod->value(), $missing);
        }

        $nextPeriod = $currentPeriod->next();
        $probedPrefectures = $this->probeAllTargetPrefectures($nextPeriod);
        $anyPublished = in_array(true, $probedPrefectures, true);

        if ($anyPublished) {
            Log::info('更新検知ポーリング: 新四半期を検知', [
                'period' => $nextPeriod->value(),
                'probed' => $probedPrefectures,
            ]);

            return PollDecision::newPeriodDetected($nextPeriod->value(), $probedPrefectures);
        }

        Log::info('更新検知ポーリング: 新四半期は未公開（全都道府県404）', [
            'period' => $nextPeriod->value(),
        ]);

        return PollDecision::none($nextPeriod->value(), $probedPrefectures);
    }

    /**
     * @return string[] muni_amount に未取得の都道府県コード一覧
     */
    private function missingPrefectureCodes(Period $period): array
    {
        $covered = $this->repository->coveredPrefectureCodes($period);

        return array_values(array_diff(PrefectureCode::targetAreaCodes(), $covered));
    }

    /**
     * @return array<string, bool> 都道府県コード => データありか
     */
    private function probeAllTargetPrefectures(Period $period): array
    {
        $result = [];
        $codes = PrefectureCode::targetAreaCodes();

        foreach ($codes as $index => $code) {
            $result[$code] = $this->apiClient->probeAreaHasData(new PrefectureCode($code), $period);

            $isLast = $index === count($codes) - 1;
            if (!$isLast) {
                sleep(1); // レート配慮
            }
        }

        return $result;
    }
}
