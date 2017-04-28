<?php

namespace App\Models;

use T4\Core\Collection;
use T4\Dbal\QueryBuilder;
use T4\Orm\Model;

/**
 * Class Event
 * @package App\Models
 * @property int saved_id
 * @property int source
 * @property string stream
 * @property int original_id
 * @property int expiration_date
 * @property string description
 * @property string announcement
 * @property bool moderation
 * @property string s_description понятия не имею, что это такое!!!
 * @property int start_pub_date
 * @property int end_pub_date
 * @property string action_url
 * @property string action_title
 * @property Collection $locations
 * @property Collection $pictures
 * @property int price_from
 * @property int price_to
 */
class Event extends Model
{
    public static $schema = [
        'table' => 'events',
        'columns' => [
            'saved_id' => ['type' => 'link'],
            'original_id' => ['type' => 'link'],
            'expiration_date' => ['type' => 'date'],
        ],
        'relations' => [
            'source' => ['type' => self::BELONGS_TO, 'model' => Source::class],
            'locations' => ['type' => self::MANY_TO_MANY, 'model' => Location::class],
            'pictures' => ['type' => self::MANY_TO_MANY, 'model' => Picture::class],
        ],
    ];

    public function __construct($data = null)
    {
        parent::__construct($data);
        $this->moderation = true;
        $this->action_title = 'Купить купоны';
    }

    /**
     * Проверка, Загружалось ли событие ранее
     * @param $id - ID события, полученый от купонатора
     * @param Source $source - источник событий
     * @return bool
     */
    public static function isLoaded($id, Source $source) : bool
    {
        $query = new QueryBuilder();
        $query->
        select('COUNT(*)')->
        from('events')->
        where('__source_id = :source AND original_id = :id')->
        params([':source' => $source->__id, ':id' => $id]);
        return boolval(Event::countAllByQuery($query));
    }
}
