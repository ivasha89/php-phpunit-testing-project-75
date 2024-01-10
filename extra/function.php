<?php

namespace Downloader\Downloader;

use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hexlet\Code\Loader;

if (!function_exists('Downloader\Downloader\downloadPage')) {
    /**
     * @throws InvalidSelectorException
     * @throws GuzzleException
     * @throws Exception
     */
    function downloadPage(string $url, ?string $targetPath, ?string $clientClass): bool
    {

        $targetDir = $targetPath ?? getcwd();

        $clientClass = !empty($clientClass) ? $clientClass : new Client();
        $params = ['url' => $url, 'path' => $targetDir, 'client' => $clientClass];
        $loader = new Loader($params);

        return $loader->load();
    }
}
