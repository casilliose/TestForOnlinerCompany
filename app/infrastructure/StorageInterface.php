<?php

declare(strict_types=1);

namespace App\infrastructure;

interface StorageInterface
{
    /**
     * Method increment count
     * url review by user
     * @param string $date
     * @param string $url
     * @return void
     */
    public function inc(string $date, string $url): void;

    /**
     * Get all count by URL and Date
     * @param string $url
     * @param string $date
     * @return array
     */
    public function getByUrlAndDate(string $url, string $date): array;

    /**
     * Method remove old Year
     * @param string $year
     * @return void
     */
    public function removeOldDataByYear(string $year): void;

    /**
     * Get rows With Max count view
     * by page and date
     * @return array
     */
    public function getRowsWithMaxCountByDate(): array;
}