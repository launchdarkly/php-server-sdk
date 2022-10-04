<?php

class TestDataStore
{
    private $_basePath;

    private const PREFIX = "client-params-";

    public function __construct($basePath)
    {
        $this->_basePath = $basePath;
    }

    public function addClientParams($params)
    {
        $data = json_encode($params);

        // call tempnam() to pick a random filename that doesn't already exist in our directory,
        // and use that filename as the client ID from now on
        $filePath = tempnam($this->_basePath, self::PREFIX);
        file_put_contents($filePath, $data);
        $id = substr_replace(basename($filePath), "", 0, strlen(self::PREFIX));

        return $id;
    }

    public function getClientParams($id)
    {
        $data = file_get_contents($this->getClientParamsFilePath($id));
        if ($data === false) {
            return null;
        }
        return json_decode($data, true);
    }

    public function deleteClientParams($id)
    {
        unlink($this->getClientParamsFilePath($id));
    }

    private function getClientParamsFilePath($id)
    {
        return $this->_basePath . '/' . self::PREFIX . $id;
    }
}
