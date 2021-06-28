<?php

declare(strict_types=1);

namespace App\VisitsCounterPage;

use App\infrastructure\StorageInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

class VisitsCounterPage
{
    private Session $session;

    private StorageInterface $storage;

    private Request $request;

    public function __construct(Session $session, StorageInterface $storage, Request $request)
    {
        $this->session = $session;
        $this->storage = $storage;
        $this->request = $request;
    }

    public function counterPage(): void
    {
        $date = date('Ymd');
        $hashUri = md5($this->request->getRequestUri());
        $key = $this->session->getId() . $date . $hashUri;
        $count = $this->session->get($key, 0);
        if ($count < 2) {
            $this->session->set($key, ++$count);
            return;
        }
        $this->storage->inc($date, $this->request->getRequestUri());
        $this->session->set($key, 0);
    }

    public function getCountPage()
    {
        return $this->storage->getByUrlAndDate($this->request->getRequestUri(), date('Ymd'));
    }

    public function oldDataRemove(string $year): void
    {
        $this->storage->removeOldDataByYear($year);
    }

    public function analyticsPage(): array
    {
        return $this->storage->getRowsWithMaxCountByDate();
    }
}