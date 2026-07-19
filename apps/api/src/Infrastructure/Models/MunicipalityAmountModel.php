<?php

declare(strict_types=1);

namespace Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 市区町村金額モデル
 * 
 * @property string $muni_code
 * @property string $type
 * @property string $price_category
 * @property int|null $avg_trade_price
 * @property int|null $median_trade_price
 * @property int $txn_count
 * @property string $period
 * @property \DateTimeImmutable $updated_at
 */
class MunicipalityAmountModel extends Model
{
    protected $table = 'muni_amount';

    public $timestamps = false;

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'muni_code',
        'type',
        'price_category',
        'avg_trade_price',
        'median_trade_price',
        'txn_count',
        'period',
        'updated_at',
    ];

    protected $casts = [
        'avg_trade_price' => 'integer',
        'median_trade_price' => 'integer',
        'txn_count' => 'integer',
        'updated_at' => 'immutable_datetime',
    ];
}
