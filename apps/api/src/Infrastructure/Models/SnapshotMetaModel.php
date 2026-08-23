<?php

declare(strict_types=1);

namespace Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * snapshot_meta テーブル用 Eloquent モデル
 */
class SnapshotMetaModel extends Model
{
    protected $table = 'snapshot_meta';
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $casts = ['snapshot_at' => 'immutable_datetime'];
}
