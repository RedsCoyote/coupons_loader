<?php

namespace App\Models;

use T4\Dbal\QueryBuilder;
use T4\Orm\Model;

define('MAX_DISTANCE', 50.0);  // В метрах
define('EARTH_RADIUS', 6372795.0);  // В метрах

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
            'lat' => ['type' => 'float'],
            'lng' => ['type' => 'float'],
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

    /**
     * Нахождение локации по ее данным
     * @param string $address - адрес локации
     * @param float $lat - широта локации
     * @param float $lng - долгота локации
     * @return bool|self - найденная локация или false
     */
    public static function getByData($address, $lat, $lng)
    {
        $locations = self::findAllByColumn('name', $address);
        if (boolval($locations)) {
            foreach ($locations as $location) {
                if (self::distance($location, $lat, $lng) <= MAX_DISTANCE) {
                    return $location;
                }
            }
        }
        return false;
    }

    /**
     * Вычисление расстояния от заданной локации до заданных координат
     * @param Location $location - локация
     * @param float $lat - широта
     * @param float $lng - долгота
     * @return float - расстояние в метрах
     */
    protected static function distance(Location $location, float $lat, float $lng): float
    {
        $lat *= M_PI / 180;
        $lat0 = $location->lat * M_PI / 180;
        $lng *= M_PI / 180;
        $lng0 = $location->lng * M_PI / 180;

        $d_lon = $lng - $lng0;

        $slat1 = sin($lat);
        $slat2 = sin($lat0);
        $clat1 = cos($lat);
        $clat2 = cos($lat0);
        $cdelt = cos($d_lon);

        $y = pow($clat2 * sin($d_lon), 2) + pow($clat1 * $slat2 - $slat1 * $clat2 * $cdelt, 2);
        $x = $slat1 * $slat2 + $clat1 * $clat2 * $cdelt;

        return abs(atan2(sqrt($y), $x) * EARTH_RADIUS);
    }

    /**
     * Поиск неиспользуемых локаций
     * @return static
     */
    public static function findUnused()
    {
        $query = new QueryBuilder();
        $query
            ->select('*')
            ->from('locations')
            ->where('__id NOT IN (SELECT __location_id FROM events_to_locations)');
        return Location::findAllByQuery($query);
    }
}
