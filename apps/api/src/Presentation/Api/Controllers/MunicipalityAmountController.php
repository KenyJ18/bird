<?php

declare(strict_types=1);

namespace Presentation\Api\Controllers;

use Application\MunicipalityAmount\Dto\GetMunicipalityAmountsRequest;
use Application\MunicipalityAmount\UseCase\GetMunicipalityAmountsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Presentation\Api\Requests\GetMunicipalityAmountsHttpRequest;

/**
 * 市区町村金額APIコントローラー
 */
class MunicipalityAmountController extends Controller
{
    public function __construct(
        private readonly GetMunicipalityAmountsUseCase $useCase
    ) {
    }

    /**
     * 市区町村金額一覧取得
     */
    public function index(GetMunicipalityAmountsHttpRequest $request): JsonResponse
    {
        try {
            // HTTPリクエストをアプリケーション層のDTOに変換
            $dto = GetMunicipalityAmountsRequest::fromArray(
                $request->validated()
            );

            // ユースケースを実行
            $response = $this->useCase->execute($dto);

            return response()->json($response->toArray());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid request',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
