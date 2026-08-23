<?php

declare(strict_types=1);

namespace Application\SnapshotMeta\Dto;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * スナップショットメタ取得レスポンスDTO
 */
final readonly class GetSnapshotMetaResponse
{
    public function __construct(
        public string $period,
        public DateTimeImmutable $snapshotAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'period'     => $this->period,
            'snapshotAt' => $this->snapshotAt
                                ->setTimezone(new DateTimeZone('Asia/Tokyo'))
                                ->format(DateTimeInterface::ATOM),
        ];
    }
}
