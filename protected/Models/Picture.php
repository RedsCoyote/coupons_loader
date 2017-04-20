<?php

namespace App\Models;

use T4\Orm\Model;

/**
 * Class Picture
 * @package App\Models
 * @property string url
 * @property int saved_id
 */
class Picture extends Model
{
    public static $schema = [
        'table' => 'pictures',
        'columns' => [
            'url' => ['type' => 'string'],
            'saved_id' => ['type' => 'integer'],
        ],
        'relations' => [
            'locations' => ['type' => self::MANY_TO_MANY, 'model' => Location::class],
            'events' => ['type' => self::MANY_TO_MANY, 'model' => Event::class],
        ],
    ];
}
