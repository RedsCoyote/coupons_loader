<?php

namespace App\Commands;

use App\Core\Logger;
use T4\Console\Command;

class Skidkabum extends Command
{
    protected $options = [
        CURLOPT_URL => 'http://api.skidkabum.ru/actions/get/',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
    ];

    public function actionGet()
    {
        $logger = new Logger($this->app->config);
        if ($curl = curl_init()) {
            $request = ['reques_t' => json_encode($this->app->config->couponators->skidkabum->getData())];
            $this->options[CURLOPT_POSTFIELDS] = http_build_query($request);
            curl_setopt_array($curl, $this->options);

            $out = curl_exec($curl);
            $info = curl_getinfo($curl);

            if (false === $out || 200 != $info['http_code']) {
                $output = 'No cURL data returned for ' . $this->options[CURLOPT_URL] . ' [' . $info['http_code']. ']';
                if (curl_error($curl)) {
                    $output .= "\n" . curl_error($curl);
                }
                $logger->log('Error', $output, []);
            } else {
                $res = json_decode($out);
                // TODO: Загрузка в БД
                echo print_r($res);
                $logger->log('Info', 'Loading from skidkabum.ru OK', ['coupons' => 1]);
            }

            curl_close($curl);
        } else {
            $logger->log('Critical', 'Can\'t inicialise cURL library', []);
        }
    }
}
