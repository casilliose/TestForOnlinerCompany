<?php

declare(strict_types=1);

namespace App\infrastructure;

use Exception;
use mysqli;
use PDO;
use PDOStatement;
use Predis\Client;

class StorageMySql implements StorageInterface
{
    private static array $instances = [];
    private PDO $connect;
    private Client $redis;
    private string $prefix = 'tb_';
    private array $config;

    protected function __construct(array $config, Client $redis)
    {
        $this->redis = $redis;
        $this->config = $config;
        $this->connect = new PDO('mysql:host=' . $config['db']['host'],
            $config['db']['user'],
            $config['db']['password']
        );
        if (!$this->redis->get('db_created')) {
            $this->connect->exec('CREATE DATABASE IF NOT EXISTS '.$config['db']['dbname']);
            $this->redis->set('db_created', 1);
        }
    }

    protected function __clone()
    {
    }

    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception("Cannot serialize a singleton.");
    }

    public static function getInstance(array $config, Client $redis): StorageMySql
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static($config, $redis);
        }

        return self::$instances[$cls];
    }

    /**
     * @throws Exception
     */
    public function inc(string $date, string $url): void
    {
        if (!$this->createPartition($date)) {
            throw new Exception("Dont can create table $date");
        }
        $pdo = $this->connect->prepare('SELECT * FROM counterpage.'.$this->prefix.$date.' WHERE url = :url');
        $pdo->execute([
            'url' => $url,
        ]);
        $rows = $pdo->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            $pdo = $this->connect->prepare('INSERT INTO counterpage.'.$this->prefix.$date.' (url, count) VALUES (:url, :count)');
            $pdo->execute([
                'url' => $url,
                'count' => 1,
            ]);
            return;
        }
        $pdo = $this->connect->prepare('UPDATE counterpage.'.$this->prefix.$date.' SET count = count + 1 WHERE url = :url');
        $pdo->execute([
            'url' => $url,
        ]);
    }

    private function createPartition(string $date): bool
    {
        if ($this->redis->get($date.'is_created')) {
            return true;
        }
        $sqlCreatePartition = 'CREATE TABLE IF NOT EXISTS counterpage.'.$this->prefix.$date.' (
                url varchar(300) NOT NULL,
                count BIGINT NOT NULL DEFAULT 0
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8
            COLLATE=utf8_general_ci;
        ';
        $this->connect->exec($sqlCreatePartition);
        $this->connect->exec("CREATE INDEX `url_IDX` USING BTREE ON counterpage.`".$this->prefix.$date."` (url);");
        $this->redis->set($date.'is_created', 1);
        return $this->connect->errorCode() === '00000';
    }

    public function getByUrlAndDate(string $url, string $date): array
    {
        $pdo = $this->connect->prepare('SELECT * FROM counterpage.'.$this->prefix.$date.' WHERE url = :url');
        $pdo->execute([
            'url' => $url,
        ]);
        return $pdo->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeOldDataByYear(string $year): void
    {
        $tablesForDelete = $this->connect->query(
            "select table_name from information_schema.tables where TABLE_SCHEMA= 'counterpage' and TABLE_NAME like '".$this->prefix.$year."%';"
        );
        foreach ($tablesForDelete as $tableName) {
            $this->connect->prepare('DROP TABLE counterpage.'.$tableName);
        }
    }

    /**
     * @return false|PDOStatement
     */
    public function getAllTableName()
    {
        return $this->connect->query(
            "select table_name from information_schema.tables where TABLE_SCHEMA= 'counterpage';"
        );
    }

    public function getRowsWithMaxCountByDate(): array
    {
        $results = [];
        $connects = [];

        $allTables = $this->getAllTableName();
        foreach ($allTables as $table) {
            $connect = new mysqli(
                $this->config['db']['host'],
                $this->config['db']['user'],
                $this->config['db']['password'],
                $this->config['db']['dbname']
            );
            $connect->query('select *, \''.$table['table_name'].'\' as date from counterpage.'.$table['table_name'].'
            where count = (select max(count) from counterpage.'.$table['table_name'].')', MYSQLI_ASYNC);
            $connects[] = $connect;
        }
        do {
            $read = $errors = $reject = [];
            foreach ($connects as $connect) {
                $read[] = $connect;
                $errors[] = $connect;
                $reject[] = $connect;
            }

            if (!mysqli_poll($read, $errors, $reject, 1)) {
                continue;
            }

            foreach ($connects as $id => $connect) {
                if ($result = mysqli_reap_async_query($connect)) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $results[] = $row;
                    }
                    mysqli_free_result($result);
                    unset($connects[$id]);
                }
            }
        } while (count($connects) > 0);

        return $results;
    }
}