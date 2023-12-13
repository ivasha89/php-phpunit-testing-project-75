<?php

namespace Downloader\Downloader;

use DiDom\Exceptions\InvalidSelectorException;
use Exception;
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
    function downloadPage(string $url, ?string $targetPath, $clientClass): bool
    {

        $targetDir = $targetPath ?? getcwd();

        $params = ['url' => $url, 'path' => $targetDir, 'client' => $clientClass];
        $loader = new Loader($params);

        return $loader->load();

    }
}