<?php
require __DIR__ . '/vendor/autoload.php';
$parameters = ['tcp://redis-node-0', 'tcp://redis-node-1', 'tcp://redis-node-2'];
$options = ['cluster' => 'redis'];

$client = new Predis\Client($parameters, $options);
$client->set('foo', 'bar');
$value = $client->get('foo');
var_dump($value);