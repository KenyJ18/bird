<?php

declare(strict_types=1);

namespace Presentation\Api\Requests;

use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 市区町村金額取得リクエスト
 */
class GetMunicipalityAmountsHttpRequest extends FormRequest
{
    /**
     * リクエストが認可されているか判定
     */
    public function authorize(): bool
    {
        return true; // 認証は不要
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        $validTypes = implode(',', DataType::validTypes());
        $validCategories = implode(',', PriceCategory::validCategories());

        return [
            'type' => ['sometimes', 'string', "in:{$validTypes}"],
            'priceCategory' => ['sometimes', 'string', "in:{$validCategories}"],
            'period' => ['sometimes', 'string', 'regex:/^\d{4}-Q[1-4]$/'],
        ];
    }

    /**
     * バリデーションエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'type.in' => 'データ種類は「宅地(土地)」「宅地(土地と建物)」「中古マンション等」のいずれかを指定してください。',
            'priceCategory.in' => '価格区分は「取引価格」「成約価格」のいずれかを指定してください。',
            'period.regex' => '期間はYYYY-QN形式で指定してください。例: 2026-Q2',
        ];
    }
}
