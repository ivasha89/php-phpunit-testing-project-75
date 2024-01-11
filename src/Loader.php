<?php

namespace Hexlet\Code;

use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use DiDom\Document;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class Loader
{
    protected $user;

    protected $url;

    protected $domainUrl;

    protected $urlScheme;

    protected $urlPagePath;

    protected $path;

    protected $client;

    protected $contentUrl;

    protected $filesDirectory;

    protected $logger;

    /**
     * @throws Exception
     */
    public function __construct(array $params)
    {
        $this->url = $params['url'];
        if (empty($this->url)) {
            throw new Exception('Empty url');
        }
        $parse = parse_url($this->url);
        $this->domainUrl = $parse['host'];
        $this->urlScheme = $parse['scheme'];
        $this->urlPagePath = !empty($parse['path']) ? $parse['path'] : '';

        $this->path = $params['path'];
        $this->user = posix_getpwuid(posix_geteuid());

        if (empty($this->path)) {
            throw new Exception('Empty path for page saving');
        }
        $this->client = $params['client'];

        $this->logger = new Logger('pageLoader');
        $this->logger->pushHandler(new StreamHandler('../loader.log', Level::Warning));
    }

    /**
     * @return string
     * @throws GuzzleException
     * @throws InvalidSelectorException
     * @throws Exception
     */
    public function load(): string
    {
        $loader = $this->client;
        $content = $loader->get($this->url)->getBody()->getContents();
        $document = new Document($this->url, true);

        // создание префикса имён
        $this->contentUrl = $this->createPath($this->url, 'directory');

        // сохраняем изображения
        $this->filesDirectory = $this->path . '/' . $this->contentUrl . '_files';
        if (!file_exists($this->filesDirectory)) {
            mkdir($this->filesDirectory);
            chown($this->filesDirectory, $this->user['name']);
            if (!is_writable($this->filesDirectory)) {
                throw new Exception('No permission to create path: ' . $this->filesDirectory);
            }
        } else {
            fwrite(STDERR, 'File directory ' . $this->filesDirectory . ' exists' . PHP_EOL);
        }
        $images = $document->find("img");
        foreach ($images as $image) {
            $imageUrl = $image->getAttribute('src');
            if (!empty($imageUrl)) {
                $content = $this->checkUrl($imageUrl, $content);
            }
        }
        $links = $document->find('link');
        foreach ($links as $link) {
            $linkHref = $link->getAttribute('href');
            if (!empty($linkHref)) {
                if ($linkHref === $this->urlPagePath) {
                    $content = str_replace(
                        $linkHref,
                        $this->contentUrl . '_files/' . $this->contentUrl . '.html',
                        $content
                    );
                } else {
                    $content = $this->checkUrl($linkHref, $content);
                }
            }
        }
        $scripts = $document->find("script");
        foreach ($scripts as $script) {
            $scriptUrl = $script->getAttribute('src');
            if (!empty($scriptUrl)) {
                $content = $this->checkUrl($scriptUrl, $content);
            }
        }

        // сохраняем страницу
        $fileName = $this->contentUrl . '.html';
        $path = $this->path . '/' . $fileName;
        try {
            file_put_contents($path, $content);
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            fwrite(STDERR, $message . PHP_EOL);
            throw $e;
        }

        return 'true';
    }


    /**
     * @param $url
     * @param $content
     * @return mixed|string
     * @throws Exception
     */
    public function checkUrl($url, $content)
    {
        if (
            stripos($url, $this->urlScheme . '://' . $this->domainUrl) === 0
            && (!substr($url, -1) != '/')
            && !stripos($url, '@')
        ) {
            if (pathinfo($url, PATHINFO_EXTENSION)) {
                $urlArray = explode('//', $url);
                if (!empty($urlArray[1])) {
                    $content = $this->saveFile($urlArray[1], $content);
                }
            }
        } elseif (
            !stripos($url, '://')
            && !stripos($url, '.com')
            && !stripos($url, '@')
        ) {
            if (pathinfo($url, PATHINFO_EXTENSION)) {
                $content = $this->saveFile($url, $content, 'local');
            }
        } else {
            fwrite(STDERR, 'Skipping file from other domain url: ' . $url . PHP_EOL);
        }

        return $content;
    }

    /**
     * @param $url
     * @param $content
     * @param string $url_type
     * @return string
     * @throws Exception
     */
    public function saveFile($url, $content, string $url_type = 'http'): string
    {
        $urlToLoad = $url_type === 'http' ? $url : $this->domainUrl . $url;
        $replaceUrl = $this->createPath($urlToLoad);
        try {
            $newUrlToSave = $this->filesDirectory . '/' . $replaceUrl;
            try {
                file_put_contents($newUrlToSave, $this->urlScheme . '://' . $urlToLoad);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Not found URL')) {
                    file_put_contents($newUrlToSave, $this->url . $url);
                }
            }

            $newUrlToReplace = $this->contentUrl . '_files' . '/' . $replaceUrl;
            $searchedUrl = $url_type === 'http' ? $this->urlScheme . '://' . $url : $url;
            $content = str_replace($searchedUrl, $newUrlToReplace, $content);
        } catch (Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            throw new Exception($e->getMessage());
        }

        return $content;
    }

    public function createPath($url, $type = 'file'): string
    {
        if ($type == 'directory') {
            $urlArray = explode('//', $url);
            return preg_replace('/[^\w\d\s]/', '-', $urlArray[1]);
        } else {
            $getExtension = explode('.', $url);
            $extension = $getExtension[count($getExtension) - 1];
            $removedExtension = substr($url, 0, strlen($url) - strlen($extension) - 1);
            $newUrl = preg_replace('/[^\w\d\s]/', '-', $removedExtension);
            return $newUrl . '.' . $extension;
        }
    }
}
