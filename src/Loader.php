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

    protected $domain_url;

    protected $path;

    protected $client;

    protected $content_url;

    protected $files_directory;

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
        $this->domain_url = $parse['host'];
        $this->path = $params['path'];
        $this->user = posix_getpwuid(posix_geteuid());

        if (empty($this->path)) {
            throw new Exception('Empty path for page saving');
        }
        $this->client = !empty($params['client']) ? new $params['client']() : new Client();

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
        $get_content = $loader->get($this->url);
        if ($get_content->getStatusCode() !== 200) {
            fwrite(STDERR, $get_content->getReasonPhrase() . PHP_EOL);
            throw new Exception('Page status code is: ' . $get_content->getStatusCode() . '. Aborting');
        } else {
            $content = $get_content->getBody()->getContents();
        }
        $document = new Document($this->url, true);

        // создание префикса имён
        $this->content_url = $this->createPath($this->url, 'directory');

        // сохраняем изображения
        $this->files_directory = $this->path . '/' . $this->content_url . '_files';
        if (!file_exists($this->files_directory)) {
            mkdir($this->files_directory, 0770, true);
            chown($this->files_directory, $this->user['name']);
            if (!is_writable($this->files_directory)) {
                throw new Exception('No permission to create path: ' . $this->files_directory);
            }
        } else {
            fwrite(STDERR, 'File directory ' . $this->files_directory . ' exists' . PHP_EOL);
        }
        $images = $document->find("img");
        foreach ($images as $image) {
            $image_url = $image->getAttribute('src');
            if (!empty($image_url)) {
                $content = $this->checkUrl($image_url, $content);
            }
        }
        $links = $document->find('link');
        foreach ($links as $link) {
            $link_href = $link->getAttribute('href');
            if (!empty($link_href)) {
                $content = $this->checkUrl($link_href, $content);
            }
        }
        $scripts = $document->find("script");
        foreach ($scripts as $script) {
            $script_url = $script->getAttribute('src');
            if (!empty($script_url)) {
                $content = $this->checkUrl($script_url, $content);
            }
        }


        // сохраняем страницу
        $file_name = $this->content_url . '.html';
        $path = $this->path . '/' . $file_name;
        if (is_writable($path)) {
            file_put_contents($path, $content);
        } else {
            $message = 'No permission to save to path: ' . $path;
            fwrite(STDERR, $message . PHP_EOL);
            throw new Exception($message);
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
            strpos($url, $this->domain_url)
            && (!substr($url,  -1) !== '/')
            && !strpos($url, '@')
        ) {
            if (pathinfo($url, PATHINFO_EXTENSION)) {
                $url_array = explode('//', $url);
                if (!empty($url_array[1])) {
                    $content = $this->saveFile($url_array[1], $content);
                }
            }
        } elseif (
            !strpos($url,'http')
            && !strpos($url,'.com')
            && !strpos($url,'@')
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
    public function saveFile($url, $content, string $url_type = 'https'): string
    {
        $url_to_load = $url_type === 'https' ? $url : $this->domain_url . $url;
        $replace_url = $this->createPath($url);
        try {
            $new_url_to_save = $this->files_directory . '/' . $replace_url;
            file_put_contents($new_url_to_save, file_get_contents('https://' . $url_to_load));

            $new_url_to_replace = $this->content_url . '_files' . '/' . $replace_url;
            $searched_url = $url_type === 'https' ? 'https://' . $url : $url;
            $content = str_replace($searched_url, $new_url_to_replace, $content);
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
            $url_array = explode('//', $url);
            return preg_replace('/[^\w\d\s]/', '-', $url_array[1]);
        } else {
            $get_extension = explode('.', $url);
            $extension = $get_extension[count($get_extension) - 1];
            $removed_extension = substr($url, 0, strlen($url) - strlen($extension) - 1);
            $new_url = preg_replace('/[^\w\d\s]/', '-', $removed_extension);
            return $new_url . '.' . $extension;
        }
    }
}
