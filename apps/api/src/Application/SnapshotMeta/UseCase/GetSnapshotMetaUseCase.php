<?php

declare(strict_types=1);

namespace Application\SnapshotMeta\UseCase;

use Application\SnapshotMeta\Dto\GetSnapshotMetaResponse;
use Infrastructure\Models\SnapshotMetaModel;

/**
 * スナップショットメタ取得ユースケース
 */
final readonly class GetSnapshotMetaUseCase
{
    public function execute(): ?GetSnapshotMetaResponse
    {
        $model = SnapshotMetaModel::find(1);
        if ($model === null) {
            return null;
        }

        return new GetSnapshotMetaResponse($model->period, $model->snapshot_at);
    }
}
