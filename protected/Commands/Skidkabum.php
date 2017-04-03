<?php

namespace App\Commands;

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
        if ($curl = curl_init()) {
            $request = ['request' => json_encode($this->app->config->couponators->skidkabum->getData())];
            $this->options[CURLOPT_POSTFIELDS] = http_build_query($request);
            curl_setopt_array($curl, $this->options);
            $out = curl_exec($curl);
            curl_close($curl);
            $res = json_decode($out);

            echo print_r($res);
        }
    }
}
