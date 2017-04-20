<?php

namespace App\Models;

use T4\Orm\Model;

/**
 * Class Source
 * @package App\Models
 * @property string $name
 * @property int stream_id
 */
class Source extends Model
{
    public static $schema = [
        'table' => 'sources',
        'columns' => [
            'name' => ['type' => 'string'],
            'stream_id' => ['type' => 'link'],
        ],
        'relations' => [
            'events' => ['type' => self::HAS_MANY, 'model' => Event::class],
        ],
    ];
}
