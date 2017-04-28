<?php

namespace App\Core;

use App\Junglefox\JungleFoxAPI;
use App\Models\Source;
use Exception;

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

    protected function beforeAction()
    {
        $config = $this->app->config;
        $this->logger = new Logger($config);
        try {
            $this->jfApi = new JungleFoxAPI($config, $this->logger);
        } catch (Exception $e) {
            $this->logger->error('Can\'t init Junglefox API. Application terminated.');
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
}
