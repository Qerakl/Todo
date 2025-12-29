<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case New = 'new';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Done = 'done';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Paused => 'Paused',
            self::Done => 'Done',
            self::Canceled => 'Canceled',
        };
    }
}
