<?php

namespace App\Junglefox;

use App\Models\Event;
use App\Models\Source;
use T4\Dbal\QueryBuilder;

trait JungleFoxCommandsTrait
{
    /**
     * @var Source $source
     */
    protected $source = null;

    /**
     * @var JungleFoxAPI $jfApi
     */
    protected $jfApi = null;

    protected function beforeAction()
    {
        $this->jfApi = new JungleFoxAPI($this->app->config);
        if (!$this->initStream()) {
            // TODO: logg
            return false;
        }
        return parent::beforeAction();
    }

    /**
     * Инициализация канала для конкретного купонатора
     * Ищет ID канала в базе, при отсутствии запрашивает на сервере
     * @return bool
     */
    protected function initStream(): bool
    {
        /** @var Source $this->source */
        $this->source = Source::findByColumn('stream', STREAM_NAME);
        if (boolval($this->source)) {
            if (is_null($this->source->stream_id)) {
                $this->source->stream_id = $this->jfApi->findStream(STREAM_NAME);
                $this->source->save();
                return boolval($this->source->stream_id);
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Проверка, является ли событие новым
     * @param $id - ID события, полученый от купонатора
     * @return bool
     */
    protected function isNewEvent($id): bool
    {
        $query = new QueryBuilder();
        $query->
        select('COUNT(*)')->
        from('events')->
        where('__source_id = :source AND original_id = :id')->
        params([':source' => $this->source->__id, ':id' => $id]);
        return !boolval(Event::countAllByQuery($query));
    }
}
