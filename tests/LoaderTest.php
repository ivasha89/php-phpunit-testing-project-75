<?php

namespace Hexlet\Code\Tests;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Hexlet\Code\Loader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class LoaderTest extends TestCase
{
    private $html;

    private $client;

    private $clientResponse;

    private $root;

    /**
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        vfsStream::setup('var', 0770);

        $this->html = '/www-youtube-com.html';
        $content = file_get_contents(__DIR__ . $this->html);

        $this->client = $this->createMock(Client::class);
        $this->clientResponse = $this->createMock(Response::class);
        $message = $this->createMock(StreamInterface::class);
        $this->client->method('get')->willReturn($this->clientResponse);
        $this->client->method('request')->willReturn($this->clientResponse);
        $this->clientResponse->method('getBody')->willReturn($message);
        $message->method('getContents')->willReturn($content);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidSelectorException
     */
    public function testInputUrl()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Empty url');
        $params = ['url' => '', 'path' => '', 'client' => ''];
        $loader = new Loader($params);
        $loader->load();
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidSelectorException
     */
    public function testInputPathParam()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Empty path for page saving');
        $params = ['url' => 'https://www.google.com', 'path' => '', 'client' => ''];
        $loader = new Loader($params);
        $loader->load();
    }

//    /**
//     * @return void
//     * @throws GuzzleException
//     * @throws InvalidSelectorException
//     */
//    public function testPageStatusFalseCode()
//    {
//        $this->expectException(Exception::class);
//        $this->expectExceptionMessage('Page status code is: 500. Aborting');
//        $this->clientResponse->method('getStatusCode')->willReturn(500);
//        $params = ['url' => 'https://www.google.com', 'path' => '/any/path', 'client' => $this->client];
//        $loader = new Loader($params);
//        $loader->load();
//    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidSelectorException
     * @throws Exception
     */
    public function testLoader()
    {
        $url = 'https://www.youtube.com';
        $directory_path = vfsStream::url('var');
        $path = $directory_path . '/tmp';
        mkdir($path, 0770, true);

        $this->clientResponse->method('getStatusCode')->willReturn(200);
        $this->client->expects($this->once())->method('get')->with($this->equalTo($url));

        $params = ['url' => $url, 'path' => $path, 'client' => $this->client];
        $loader = new Loader($params);
        $loader->load();

        $dom_document = new Document( __DIR__ . $this->html, true);
        $this->assertFileExists($path . $this->html);
        $images = $dom_document->find("img");
        $scripts = $dom_document->find("script");
        $files = array_merge($scripts, $images);
        foreach ($files as $file) {
            $file_url = $file->getAttribute('src');
            if (!strpos($file_url, 'https')) {
                 $this->assertFileExists($path . '/' . $file_url);
            }
        }

        $links = $dom_document->find('link');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (
                pathinfo($url, PATHINFO_EXTENSION)
                && !(substr($href, -1) !== '/')
                && !strpos($href, '@')
                && !strpos($href, 'https')
            ) {
                $this->assertFileExists($path . '/' . $href);
            }

        }
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidSelectorException
     */
    public function testSavingFileError()
    {
        $this->expectException(Exception::class);
        $url = 'https://www.youtube.com';
        $path = '/tzar';

        $this->clientResponse->method('getStatusCode')->willReturn(200);
        $this->client->expects($this->once())->method('get')->with($this->equalTo($url));

        $params = ['url' => $url, 'path' => $path, 'client' => $this->client];
        $loader = new Loader($params);
        $loader->load();
    }
}
