<?php

namespace Hexlet\Code\Tests;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

    /**
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        vfsStream::setup('var', 0777);

        $this->html = '/site-com-blog-about.html';
        $content = file_get_contents(__DIR__ . '/fixtures' . $this->html);

        $this->client = $this->createMock(Client::class);
        $this->clientResponse = $this->createMock(ResponseInterface::class);
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
        $params = ['url' => 'https://site.com/blog/about', 'path' => '', 'client' => ''];
        $loader = new Loader($params);
        $loader->load();
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidSelectorException
     * @throws Exception
     */
    public function testLoader()
    {
        $url = 'https://site.com/blog/about';
        $directoryPath = vfsStream::url('var');
        $path = $directoryPath . '/tmp';
        mkdir($path);

        $this->clientResponse->method('getStatusCode')->willReturn(200);
        $this->client->expects($this->once())->method('get')->with($this->equalTo($url));

        $params = ['url' => $url, 'path' => $path, 'client' => $this->client];
        $loader = new Loader($params);
        $loader->load();

        $domDocument = new Document(__DIR__ . '/fixtures' . $this->html, true);
        $this->assertFileExists($path . $this->html);
        $images = $domDocument->find("img");
        $scripts = $domDocument->find("script");
        $files = array_merge($scripts, $images);
        foreach ($files as $file) {
            $this->assertSrcExists($file->getAttribute('src'), $path);
        }

        $links = $domDocument->find('link');
        foreach ($links as $link) {
            $this->assertLinkExists($link->getAttribute('href'), $path);
        }
    }

    public function assertSrcExists($fileUrl, $path)
    {
        if (
            pathinfo($fileUrl, PATHINFO_EXTENSION)
            && !empty($fileUrl)
            && !strpos($fileUrl, '://')
            && !strpos($fileUrl, '.com')
            && !strpos($fileUrl, '@')
        ) {
            $this->assertFileExists($path . '/' . $fileUrl);
        }
    }

    public function assertLinkExists($href, $path)
    {
        if (
            !empty($href)
            && pathinfo($href, PATHINFO_EXTENSION)
            && !(substr($href, -1) !== '/')
            && !strpos($href, '@')
            && !strpos($href, 'https')
        ) {
            $this->assertFileExists($path . '/' . $href);
        }
    }
}
