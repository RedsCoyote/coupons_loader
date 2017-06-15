<?php

namespace App\Core;

use App\Exceptions\InitAPIException;
use App\Junglefox\JungleFoxAPI;
use App\Models\Event;
use App\Models\Location;
use App\Models\Source;

abstract class Command extends \T4\Console\Command
{
    const STREAM_NAME = '';
    /**
     * @var Logger $logger
     */
    protected $logger = null;
    /**
     * @var JungleFoxAPI $jfApi
     */
    protected $jfApi = null;
    /**
     * @var Source $source
     */
    protected $source = null;

    /**
     * Действия, выполняемые до любой команды
     * @return bool Если возвращается false, то дальнейшая команда игнорируется
     */
    protected function beforeAction()
    {
        $config = $this->app->config;
        $this->logger = new Logger($config);
        try {
            $this->jfApi = new JungleFoxAPI($config, $this->logger);
        } catch (InitAPIException $e) {
            $this->logger->error('Can\'t init API. Application terminated.');
            return false;
        }
        if (!$this->initStream()) {
            $this->logger->error('Can\'t find current stream. Application terminated.');
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
        $this->source = Source::findByColumn('stream', static::STREAM_NAME);
        if (boolval($this->source)) {
            if (is_null($this->source->stream_id)) {
                $this->source->stream_id = $this->jfApi->findStream(static::STREAM_NAME);
                $this->source->save();
                return boolval($this->source->stream_id);
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * Удаление прошедших событий
     */
    private function removeExpiredEvents()
    {
        $expiredEvents = Event::findExpiredBySource($this->source);
        /** @var Event $expiredEvent */
        foreach ($expiredEvents as $expiredEvent) {
            $this->jfApi->deleteEvent($expiredEvent->saved_id);
            $expiredEvent->delete();
        }
    }

    /**
     * Удаление локаций, которые не связаны ни с одним событием
     */
    private function removeUnusedLocations()
    {
        $unusedLocations = Location::findUnused();
        /** @var Location $unusedLocation */
        foreach ($unusedLocations as $unusedLocation) {
            $this->jfApi->deleteLocation($unusedLocation->getPk());
            $unusedLocation->delete();
        }
    }

    /**
     * Удаление устаревших и ненужных данных
     */
    protected function cleanUp()
    {
        $this->removeExpiredEvents();
        $this->removeUnusedLocations();
    }
}
