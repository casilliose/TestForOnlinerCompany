<?php
return [
    'db' => ['host' => 'mysql', 'dbname' => 'counterpage', 'user' => 'root', 'password' => 'root'],
    'redis' => [
        'options' => ['cluster' => 'redis'],
        'parameters' => ['tcp://redis-node-0', 'tcp://redis-node-1', 'tcp://redis-node-2'],
    ],
    'prod' => true,
];