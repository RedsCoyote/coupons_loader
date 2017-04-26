<?php

namespace App\Commands;

use App\Core\Logger;
use App\Junglefox\JungleFoxCommandsTrait;
use App\Models\Event;
use App\Models\Location;
use T4\Console\Command;
use T4\Core\Collection;

define('STREAM_NAME', 'Купоны на скидки');

class Skidkabum extends Command
{
    use JungleFoxCommandsTrait;

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
        $eventCounter = 0;
        $logger = new Logger($this->app->config);
        if ($curl = curl_init()) {
            $request = ['request' => json_encode($this->app->config->couponators->skidkabum->getData())];
            $this->options[CURLOPT_POSTFIELDS] = http_build_query($request);
            curl_setopt_array($curl, $this->options);

            $out = curl_exec($curl);
            $info = curl_getinfo($curl);

            if (false === $out || 200 != $info['http_code']) {
                $output = 'No cURL data returned for ' . $this->options[CURLOPT_URL] . ' [' . $info['http_code'] . ']';
                if (curl_error($curl)) {
                    $output .= "\n" . curl_error($curl);
                }
                $logger->log('Error', $output, ['request' => $this->options, 'answer' => $info]);
            } else {
                $res = json_decode($out);
                if (1 == count((array)$res)) {
                    $logger->log('Info', 'Loaded event #' . $res->action->id . ' from skidkabum.ru');
                    $res->actions[] = $res->action;
                    $singleMode = true;
                } else {
                    $logger->log(
                        'Info',
                        'Loaded ' . $res->count . ' events out ' . $res->allCount . ' from skidkabum.ru'
                    );
                    $singleMode = false;
                }

                foreach ($res->actions as $action) {
                    if ($this->isNewEvent($action->id)) {
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
                        $picture = $this->jfApi->addPicture($action->mainPhoto);
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
                                $location->id = $this->jfApi->addLocation($location);
                                if ($location->id) {
                                    $locationsIds[] = ['id' => $location->id, 'sessions' => $sessions];
                                    $location->saved_id = $location->id;
                                    $location->save();
                                    $locations[] = $location;
                                }
                            }
                        }
                        $event->locations = $locationsIds;
                        if (boolval($event->saved_id = $this->jfApi->addEvent($event))) {
                            $event->source = $this->source;
                            $event->locations = new Collection($locations);
                            $event->pictures = new Collection([$picture]);
                            $event->original_id = $action->id;
                            $event->expiration_date = date("Y-m-d", $event->end_pub_date);
                            $event->save();
                            $eventCounter += 1;
                        }
                    }
                }
            }

            $logger->log('Info', 'Saved ' . $eventCounter . ' events from ' . $this->options[CURLOPT_URL], []);
            curl_close($curl);
        } else {
            $logger->log('Critical', 'Can\'t inicialise cURL library', []);
        }
    }

    public function actionTest()
    {
        $locations = Location::findAll();
        foreach ($locations as $location) {
            $this->jfApi->deleteLocation($location->saved_id);
        }
//        $events = Event::findAll();
//        foreach ($events as $event) {
//            $this->jfApi->deleteEvent($event->saved_id);
//        }
//        $this->jfApi->addPicture('http://skidkabum.ru/img/img/projekt-s2.jpg');
//        $this->jfApi->deleteLocation(13430);
    }
}
