<?php

namespace App\Junglefox;

use App\Core\Logger;
use App\Exceptions\InitAPIException;
use App\Exceptions\EventException;
use App\Exceptions\ImageException;
use App\Exceptions\LocationException;
use App\Exceptions\SignInException;
use App\Models\Event;
use App\Models\Location;
use App\Models\Picture;
use T4\Core\Config;

class JungleFoxAPI
{
    protected $auth_token = null;
    protected $curl = null;
    protected $config = null;
    protected $logger = null;

    /**
     * JungleFoxAPI constructor.
     * @param Config $config
     * @param Logger $logger
     * @throws InitAPIException
     */
    public function __construct(Config $config, Logger $logger)
    {
        $this->logger = $logger;
        $this->config = $config->junglefox;
        if (false === $this->curl = curl_init()) {
            $this->logger->log('Critical', 'Can\'t initialise cURL library', []);
            throw new InitAPIException();
        }
        try {
            $this->signIn();
        } catch (SignInException $e) {
            throw new InitAPIException();
        }
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    /**
     * Аутентификация + авторизация пользователя
     * получет auth_token с сервера
     * @throws SignInException
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
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code'] || (isset($out->success) && false === $out->success)) {
            $output = '(signIn) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
            throw new SignInException();
        } else {
            $this->auth_token = json_decode($out)->user->auth_token;
        }
    }

    /**
     * @param Location $location
     * @return int
     * @throws LocationException
     */
    public function addLocation(Location $location)
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
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 201 != $info['http_code']) {
            $output = '(addLocation) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
            throw new LocationException();
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
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (204 != $info['http_code']) {
            $output = '(deleteLocation) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
        }
    }

    /**
     * Поиск канала по его имени
     * @param string $streamName
     * @return int|null
     */
    public function findStream(string $streamName)
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v3/streams?name_cont=' . urlencode($streamName),
            CURLOPT_POST => false,
            CURLOPT_RETURNTRANSFER => true,
        ];
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = '(findStream) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
            return null;
        } else {
            return (int)json_decode($out)[0]->id;
        }
    }

    /**
     * @param Event $event
     * @return int
     * @throws EventException
     */
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
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = '(addEvent) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
            throw new EventException();
        } else {
            $res = json_decode($out);
            return $res->id;
        }
    }

    /**
     * Удаление события по его ID
     * @param int $eventID - ID удаляемого события
     */
    public function deleteEvent(int $eventID)
    {
        $options = [
            CURLOPT_URL => $this->config->url . '/api/v2/events/' . $eventID,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'auth_token: ' . $this->auth_token
            ],
            CURLOPT_RETURNTRANSFER => false,
        ];
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (204 != $info['http_code']) {
            $output = '(deleteEvent) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
        }
    }

    /**
     * Скачивает изображение, возвращет его, закодировав в Base64
     * @param string $pictureURL
     * @return string
     * @throws ImageException
     */
    protected function getPicture(string $pictureURL)
    {
        $options = [
            CURLOPT_URL => $pictureURL,
            CURLOPT_POST => false,
            CURLOPT_RETURNTRANSFER => true,
        ];

        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = '(getPicture) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
            throw new ImageException();
        } else {
            $path = explode('://', $pictureURL)[1];
            $type = pathinfo($path, PATHINFO_EXTENSION);
            return 'data:image/' . $type . ';base64,' . base64_encode($out);
        }
    }

    /**
     * Загрузка изображения на сервер
     * @param $pictureURL - URL изображения
     * @return Picture
     * @throws ImageException
     */
    public function addPicture($pictureURL)
    {
        if (boolval($picture = Picture::findByColumn('url', $pictureURL))) {
            return $picture;
        }
        $pictureData = $this->getPicture($pictureURL);
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
        curl_reset($this->curl);
        curl_setopt_array($this->curl, $options);
        $out = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if (false === $out || 200 != $info['http_code']) {
            $output = '(addPicture) From ' . $options[CURLOPT_URL] . ' returned [' . $info['http_code'] . ']';
            if (curl_error($this->curl)) {
                $output .= "\n" . curl_error($this->curl);
            }
            $this->logger->log('Error', $output, ['request' => $options]);
            throw new ImageException();
        } else {
            $res = json_decode($out);
            $picture = new Picture();
            $picture->url = $pictureURL;
            $picture->saved_id = $res->id;
            $picture->save();
            return $picture;
        }
    }

    /**
     * @return null|resource
     */
    public function getCurl()
    {
        return $this->curl;
    }
}
