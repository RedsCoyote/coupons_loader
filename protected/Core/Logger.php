<?php

namespace App\Core;

class Logger extends \T4\Core\Logger
{
    public function log($level, $message, array $context = array())
    {
        if ('Critical' == $level) {
            parent::log($level, $message, $context);
        } else {
            $messageArray = [
                date('Y/m/d H:i:s')." [". $level . "] " . $message
            ];

            if (!empty($context)) {
                $messageArray[] = 'Context:';
                foreach ($context as $key => $value) {
                    $messageArray[] =  $key . ' => ' . var_export($value, true);
                }
            }

            $file = $this->path . DIRECTORY_SEPARATOR . 'application.log';
            if (!file_exists($this->path)) {
                mkdir($this->path, 0777, true);
            }

            file_put_contents(
                $file,
                implode(", ", $messageArray) . "\n",
                FILE_APPEND
            );
        }
    }
}
