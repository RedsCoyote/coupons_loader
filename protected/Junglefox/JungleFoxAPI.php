<?php

namespace App\Junglefox;

use App\Models\Event;
use App\Models\Location;
use App\Models\Picture;
use T4\Core\Config;
use T4\Core\Logger;

class JungleFoxAPI
{
    protected $auth_token = null;
    protected $curl = null;
    protected $config = null;
    protected $logger = null;

    public function __construct(Config $config)
    {
        $this->logger = new Logger($config);
        $this->config = $config->junglefox;
        $this->curl = curl_init();
        $this->signIn();
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    /**
     * Аутентификация + авторизация пользователя
     * получет auth_token с сервера
     */
    protected function signIn()
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v2/users/sign_in',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-type: application/json; charset=UTF-8'],
            CURLOPT_RETURNTRANSFER => true,
        ];
        $options[CURLOPT_POSTFIELDS] = json_encode(['user' => $this->config->user->getData()]);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, []);
        } else {
            $this->auth_token = json_decode($out)->user->auth_token;
        }
    }

    /**
     * @param Location $location
     * @return int|null
     */
    public function addLocation(Location $location) : ?int
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v2/locations',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-type: application/json; charset=UTF-8',
                'auth_token: ' . $this->auth_token
            ],
            CURLOPT_RETURNTRANSFER => true,
        ];
        $options[CURLOPT_POSTFIELDS] = json_encode(['location' => $location->getData()]);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 201 != $info['http_code']) {
            $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, []);
            return null;
        } else {
            $res = json_decode($out);
            return $res->id;
        }
    }

    /**
     * Удаление локации по ее ID
     * @param int $locationID - ID удаляемой локации
     */
    public function deleteLocation(int $locationID)
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v2/locations/' . $locationID,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'auth_token: ' . $this->auth_token
            ],
            CURLOPT_RETURNTRANSFER => false,
        ];
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (204 != $info['http_code']) {
            $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, []);
        }
    }

    /**
     * Поиск канала по его имени
     * @param string $streamName
     * @return int|null
     */
    public function findStream(string $streamName) : ?int
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v3/streams?name_cont=' . urlencode($streamName),
            CURLOPT_POST => false,
            CURLOPT_RETURNTRANSFER => true,
        ];
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, []);
            return null;
        } else {
            return (int) json_decode($out)[0]->id;
        }
    }

    public function addEvent(Event $event)
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v2/events',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-type: application/json; charset=UTF-8',
                'auth_token: ' . $this->auth_token
            ],
            CURLOPT_RETURNTRANSFER => true,
        ];
        $eventData = $event->getData();
        $eventData['locations'] = $event->locations;  // Имя locations еще и имя связи, по getData не выдается
        $eventData['pictures'] = $event->pictures;  // Имя pictures еще и имя связи, по getData не выдается
        $options[CURLOPT_POSTFIELDS] = json_encode(['event' => $eventData]);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, []);
            return null;
        } else {
            $res = json_decode($out);
            return $res->id;
        }
    }

    /**
     * Скачивает изображение, возвращет его, закодировав в Base64
     * @param string $pictureURL
     * @return null|string
     */
    protected function getPicture(string $pictureURL)
    {
        $options = [
            CURLOPT_URL => $pictureURL,
            CURLOPT_POST => false,
            CURLOPT_RETURNTRANSFER => true,
        ];

        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, []);
            return null;
        } else {
            $path = explode('://', $pictureURL)[1];
            $type = pathinfo($path, PATHINFO_EXTENSION);
            return 'data:image/' . $type . ';base64,' . base64_encode($out);
        }
    }

    public function addPicture($pictureURL) : ?Picture
    {
        if (boolval($picture = Picture::findByColumn('url', $pictureURL))) {
            return $picture;
        }
        if (boolval($pictureData = $this->getPicture($pictureURL))) {
            $options = [
                CURLOPT_URL => $this->config->url . '/api/v2/pictures',
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-type: application/json; charset=UTF-8',
                    'auth_token: ' . $this->auth_token
                ],
                CURLOPT_RETURNTRANSFER => true,
            ];
            $options[CURLOPT_POSTFIELDS] = json_encode(['picture' => ['image' => $pictureData]]);
            curl_setopt_array($this->curl, $options);
            $out = curl_exec($this->curl);
            $info = curl_getinfo($this->curl);

            if (false === $out || 200 != $info['http_code']) {
                $output = 'From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
                if (curl_error($this->curl)) {
                    $output .= "\n" . curl_error($this->curl);
                }
                $this->logger->log('Error', $output, []);
                return null;
            } else {
                $res = json_decode($out);
                $picture = new Picture();
                $picture->url = $pictureURL;
                $picture->saved_id = $res->id;
                $picture->save();
                return $picture;
            }
        }
        return null;
    }
}
