<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use LaunchDarkly\Subsystems\BigSegmentsStore;
use LaunchDarkly\Types\BigSegmentsStoreMetadata;

class BigSegmentsStoreGuzzle implements BigSegmentsStore
{
    public function __construct(private Client $client, private string $uri)
    {
    }

    public function getMetadata(): BigSegmentsStoreMetadata
    {
        $response = $this->client->request('POST', $this->uri . '/getMetadata');

        /** @var array<string, mixed> */
        $json = json_decode($response->getBody()->getContents(), associative: true);

        /** @var mixed|null */
        $lastUpToDate = $json['lastUpToDate'] ?? null;
        if ($lastUpToDate !== null) {
            $lastUpToDate = (int) $lastUpToDate;
        }

        return new BigSegmentsStoreMetadata($lastUpToDate);
    }

    /**
     * @return array<string, bool>|null
     */
    public function getMembership(string $contextHash): ?array
    {
        $response = $this->client->request('POST', $this->uri . '/getMembership', ['json' => ['contextHash' => $contextHash]]);

        $body = $response->getBody()->getContents();

        /** @var array<string, mixed> */
        $json = json_decode($body, associative: true);

        /** @var array<string, bool>|null */
        return $json['values'] ?? null;
    }
}
