<?php

namespace Downloader\Downloader;

use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hexlet\Code\Loader;

/**

For Hexlet test's needs
 */
if (! function_exists( 'Downloader\Downloader\downloadPage')) {
    /**
     * @throws InvalidSelectorException
     * @throws GuzzleException
     * @throws Exception
     */
    function downloadPage(string $url, ?string $targetPath, string $clientClass): bool
    {

        $targetDir = $targetPath ?? getcwd();

        /**
         * @var Client $client
         */
        $client = new $clientClass();
        $params = ['url' => $url, 'path' => $targetDir, 'client' => $client];
        $loader = new Loader($params);

        return $loader->load();

    }
}