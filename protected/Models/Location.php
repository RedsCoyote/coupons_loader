<?php

namespace App\Models;

use T4\Orm\Model;

/**
 * Class Location
 * @package App\Models
 * @property string $name
 * @property int $saved_id
 * @property float $lat
 * @property float $lng
 * @property string $address
 * @property int $id
 */
class Location extends Model
{
    public static $schema = [
        'table' => 'locations',
        'columns' => [
            'name' => ['type' => 'string'],
            'saved_id' => ['type' => 'integer'],
        ],
        'relations' => [
            'pictures' => ['type' => self::MANY_TO_MANY, 'model' => Picture::class],
            'events' => ['type' => self::MANY_TO_MANY, 'model' => Event::class],
        ],
    ];

    public function __construct($data = null)
    {
        parent::__construct($data);
        $this->description = '';
        $this->moderation = true;
    }
}
