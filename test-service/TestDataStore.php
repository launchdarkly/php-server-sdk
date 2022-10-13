<?php

declare(strict_types=1);

namespace Tests;

class TestDataStore
{
    private string $_basePath;

    private const PREFIX = "client-params-";

    public function __construct(string $basePath)
    {
        $this->_basePath = $basePath;
    }

    public function addClientParams(mixed $params): string
    {
        $data = json_encode($params);

        // call tempnam() to pick a random filename that doesn't already exist in our directory,
        // and use that filename as the client ID from now on
        $filePath = tempnam($this->_basePath, self::PREFIX);
        file_put_contents($filePath, $data);
        $id = substr_replace(basename($filePath), "", 0, strlen(self::PREFIX));

        return $id;
    }

    public function getClientParams(string $id): array
    {
        $data = file_get_contents($this->getClientParamsFilePath($id));
        if ($data === false) {
            return null;
        }
        return json_decode($data, true);
    }

    public function deleteClientParams(string $id): void
    {
        unlink($this->getClientParamsFilePath($id));
    }

    private function getClientParamsFilePath(string $id): string
    {
        return $this->_basePath . '/' . self::PREFIX . $id;
    }
}
