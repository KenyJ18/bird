<?php

declare(strict_types=1);

namespace Presentation\Api\Controllers;

use Application\SnapshotMeta\UseCase\GetSnapshotMetaUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * スナップショットメタ APIコントローラー
 */
class SnapshotMetaController extends Controller
{
    public function __construct(
        private readonly GetSnapshotMetaUseCase $useCase
    ) {
    }

    /**
     * スナップショットメタ取得
     */
    public function show(): JsonResponse
    {
        $response = $this->useCase->execute();
        if ($response === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($response->toArray());
    }
}
