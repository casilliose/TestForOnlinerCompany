<?php

declare(strict_types=1);

namespace App;

require __DIR__ . '/vendor/autoload.php';

use App\infrastructure\StorageMySql;
use App\VisitsCounterPage\VisitsCounterPage;
use Exception;
use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Predis\Client;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;


$logger = new Logger('app');
$handler = new RotatingFileHandler('/app/log/app.log', 5, Logger::ERROR, true, 0664);
$handler->setFilenameFormat('{date}-{filename}', 'Y/m/d');
$logger->pushHandler($handler);

try {
    $config = require __DIR__ . '/config.php';
    $client = new Client($config['redis']['parameters'], $config['redis']['options']);
    $client->connect();
    $session = new Session();
    $session->start();
    $request = Request::createFromGlobals();
    $router = new RouteCollector();
    $router->get('/', function () {
        return 'Like at Home!';
    });
    $router->get('/example/{pageId}', function () use ($request, $session, $config, $client) {
        (new VisitsCounterPage($session, StorageMySql::getInstance($config, $client), $request))->counterPage();
        $rows = (new VisitsCounterPage($session, StorageMySql::getInstance($config, $client), $request))->getCountPage();
        return 'Current date ' . date('Y-m-d') . ' count review for this page: ' . $rows[0]['count'];
    });
    $router->get('/removeold/{year}', function ($year) use ($request, $session, $config, $client) {
        (new VisitsCounterPage($session, StorageMySql::getInstance($config, $client), $request))->oldDataRemove($year);
        return 'Old data was removed';
    });
    $router->get('/analytics', function () use ($request, $session, $config, $client) {
        $rows = (new VisitsCounterPage($session, StorageMySql::getInstance($config, $client), $request))->analyticsPage();
        include ('./VisitsCounterPage/analyticsTemplate.php');
    });
    $dispatcher = new Dispatcher($router->getData());
    echo $dispatcher->dispatch($request->getMethod(), $request->getRequestUri());
} catch (HttpRouteNotFoundException $e) {
    $logger->addError($e->getMessage(), $e->getTrace());
    http_response_code(404);
    include('404.php');
    die();
} catch (Exception $e) {
    $logger->addError($e->getMessage(), $e->getTrace());
    if ($config['prod']) {
        include('500.php');
        die();
    }
    var_dump($e);
}