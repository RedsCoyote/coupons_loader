<?php

return [
    'junglefox' => [
        'url' => 'https://junglefox.me',
        'user' => [
            'email' => '{{api.user}}',
            'password' => '{{api.password}}',
        ]
    ],
    'db' => [
        'default' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'dbname' => '{{db.name}}',
            'user' => '{{db.user}}',
            'password' => '{{db.password}}',
        ]
    ],
    'couponators' => [
        'skidkabum' => [
            'userID' => '{{skidkabum.user}}',  // пользователь
            'pass' => crypt('{{skidkabum.password}}', '{{skidkabum.salt}}'), // пароль и секретное слово (соль)
            'format' => 'json',
            'cityID' => 1,  // Moscow
            'menuID' => 16,
        ],
    ],
    'path' => __DIR__ . '/../logs',  // директория с логами
];
