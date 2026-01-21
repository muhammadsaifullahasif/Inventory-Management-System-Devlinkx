<?php

namespace App\Services;

use App\Models\SalesChannel;

class UnsoldListingsService
{
    private const EBAY_API_URL = 'https://api.ebay.com/ws/api.dll';
    private const API_COMPATIBILITY_LEVEL = '967';
    private const API_SITE_ID = '0'; // US

    /**
     * Get unsold listings (paginated)
     */
    public function getUnsoldListings(SalesChannel $salesChannel, int $page = 1, int $perPage = 100, int $days = 60): array
    {
        $endTimeFrom = gmdate('Y-m-d\TH:i:s\Z', strtotime("-$days days"));
        $endTimeTo = gmdate('Y-m-d\TH:i:s\Z');

        $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
            <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <DetailLevel>ReturnAll</DetailLevel>
                <EndTimeFrom>' . $endTimeFrom . '</EndTimeFrom>
                <EndTimeTo>' . $endTimeTo . '</EndTimeTo>
                <IncludeWatchCount>true</IncludeWatchCount>
                <Pagination>
                    <EntriesPerPage>' . $perPage . '</EntriesPerPage>
                    <PageNumber>' . $page . '</PageNumber>
                </Pagination>
                <GranularityLevel>Fine</GranularityLevel>
            </GetSellerListRequest>';

        // This would use your existing EbayService's callTradingApi method
        // For now, this is a placeholder
        return [
            'success' => true,
            'items' => [],
            'pagination' => ['totalPages' => 1],
        ];
    }

    /**
     * Get ALL unsold listings (auto-pagination)
     */
    public function getAllUnsoldListings(SalesChannel $salesChannel, int $days = 60): array
    {
        $allItems = [];
        $page = 1;

        do {
            $response = $this->getUnsoldListings($salesChannel, $page, 200, $days);
            $allItems = array_merge($allItems, $response['items']);
            $totalPages = $response['pagination']['totalPages'];
            $page++;
        } while ($page <= $totalPages);

        return [
            'success' => true,
            'total_items' => count($allItems),
            'items' => $allItems,
        ];
    }
}
