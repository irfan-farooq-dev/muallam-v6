<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Private = 'private';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Private => 'Private',
            self::Restricted => 'Restricted',
        };
    }
}
