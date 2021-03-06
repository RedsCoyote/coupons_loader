<?php

namespace App\Commands;

use App\Core\Command;
use App\Exceptions\EventException;
use App\Exceptions\ImageException;
use App\Exceptions\LocationException;
use App\Models\Event;
use App\Models\Location;
use T4\Core\Collection;

class Skidkabum extends Command
{
    const STREAM_NAME = 'Купоны на скидки';

    protected $options = [
        CURLOPT_URL => 'http://api.skidkabum.ru/actions/get/',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
    ];

    /**
     * Действие по-умолчанию
     */
    public function actionDefault()
    {
        $this->actionGet();
    }

    /**
     * Получить события с купонатора и загрузить на наш портал
     */
    public function actionGet()
    {
        $this->cleanUp();
        exit();  // 2017-02-14 нужно только удалить все события
        $eventCounter = 0;
        $actions = $this->downloadEvents();
        $singleMode = (1 == count((array)$actions));
        foreach ($actions as $action) {
            if (!Event::isLoaded($action->id, $this->source)) {
                $event = new Event();
                $event->announcement = mb_substr($action->name, 0, 160, 'UTF-8');
                $event->s_description = json_encode(['data' => [[
                    'type' => 1,
                    'data' => $action->describe . $action->describeAdvertisment .
                        $action->describeAttention
                ]]]);
                $event->stream = ['id' => $this->source->stream_id];
                $event->action_url = $action->url . '?utm_source=junglefox#countdown';
                $event->start_pub_date = strtotime($action->dateStart);
                $event->end_pub_date = strtotime($action->dateEnd);
                $event->price_from = $action->priceBeforeDiscount;
                $event->price_to = $action->price;
                try {
                    $picture = $this->jfApi->addPicture($action->mainPhoto);
                } catch (ImageException $e) {
                    $this->logger->error('Skidkabum\'s action ' . $action->url . 'error loading main picture');
                    continue;
                }
                $event->pictures = [['id' => $picture->saved_id]];
                $sessions = [
                    [
                        'start_at' => $event->start_pub_date,
                        'end_at' => $event->end_pub_date,
                        'price' => $action->price,
                        'currency' => 'rub',
                    ]
                ];
                $locationsIds = [];
                $locations = [];
                if (!boolval($action->address)) {
                    $this->logger->error('Skidkabum\'s action ' . $action->url . 'don\'t have the address');
                    continue;
                }
                foreach ($action->address as $address) {
                    if ($singleMode) {
                        $lat = $address->geoX;
                        $lng = $address->geoY;
                    } else {
                        $lat = $address->geoY;
                        $lng = $address->geoX;
                    }
                    if (boolval(
                        $location = Location::getByData($address->address, $lat, $lng)
                    )) {
                        $locationsIds[] = ['id' => (int)$location->saved_id, 'sessions' => $sessions];
                        $locations[] = $location;
                    } else {
                        $location = new Location();
                        $location->lat = $lat;
                        $location->lng = $lng;
                        $location->address = $address->address;
                        $location->name = $location->address;
                        try {
                            $location->id = $this->jfApi->addLocation($location);
                        } catch (LocationException $e) {
                            $this->logger->error('Skidkabum\'s action ' . $action->url . 'error loading location');
                            continue 2;
                        }
                        $locationsIds[] = ['id' => $location->id, 'sessions' => $sessions];
                        $location->saved_id = $location->id;
                        $location->save();
                        $locations[] = $location;
                    }
                }
                $event->locations = $locationsIds;
                try {
                    $event->saved_id = $this->jfApi->addEvent($event);
                } catch (EventException $e) {
                    $this->logger->error('Skidkabum\'s action ' . $action->url . 'error loading event');
                    continue;
                }
                $event->source = $this->source;
                $event->locations = new Collection($locations);
                $event->pictures = new Collection([$picture]);
                $event->original_id = $action->id;
                $event->expiration_date = date("Y-m-d", $event->end_pub_date);
                $event->save();
                $eventCounter += 1;
                usleep(500000);
            }
        }

        $this->logger->log('Info', 'Saved ' . $eventCounter . ' events from ' . $this->options[CURLOPT_URL], []);
    }

    /**
     * Для тестирования отдельных функций
     */
    public function actionTest()
    {
//        $events = Event::findAll();
//        foreach ($events as $event) {
//            $this->jfApi->deleteEvent($event->saved_id);
//        }
//        $locations = Location::findAll();
//        foreach ($locations as $location) {
//            $this->jfApi->deleteLocation($location->saved_id);
//        }
//        $this->jfApi->addPicture('http://skidkabum.ru/img/img/projekt-s2.jpg');
//        $this->jfApi->deleteLocation(13430);
    }

    /**
     * Загрузка акций с купонатора
     * @return array
     */
    protected function downloadEvents(): array
    {
        $curl = $this->jfApi->getCurl();
        $request = ['request' => json_encode($this->app->config->couponators->skidkabum->getData())];
        $this->options[CURLOPT_POSTFIELDS] = http_build_query($request);
        curl_reset($curl);
        curl_setopt_array($curl, $this->options);

        $out = curl_exec($curl);
        $info = curl_getinfo($curl);

        if (false === $out || 200 != $info['http_code']) {
            $this->logger->error(
                'Can\'t download actions',
                ['request' => $this->options, 'answer' => $out, 'answer_info' => $info]
            );
            return [];
        } else {
            $res = json_decode($out);
            if (1 == count((array)$res)) {
                $this->logger->info('Loaded event #' . $res->action->id . ' from skidkabum.ru');
                $res->actions[] = $res->action;
            } else {
                $this->logger->info(
                    'Loaded ' . $res->count . ' events out ' . $res->allCount . ' from skidkabum.ru'
                );
            }
            return $res->actions;
        }
    }
}
