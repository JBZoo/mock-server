<?php

/**
 * JBZoo Toolbox - Mock-Server
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    Mock-Server
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @link       https://github.com/JBZoo/Mock-Server
 */

declare(strict_types=1);

namespace JBZoo\MockServer\Mocks;

use Amp\Http\Status;
use JBZoo\Data\Data;
use JBZoo\MockServer\Server\Request;
use JBZoo\Utils\Cli;
use JBZoo\Utils\Sys;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Class AbstractMock
 * @package JBZoo\MockServer\Mocks
 */
abstract class AbstractMock
{
    private const CRAZY_MAX_DELAY = 10000; // 10 seconds

    private const CRAZY_POSSIBLE_BODIES = [
        "",
        "Crazy mode is enabled. Received unexpected response ;)",
        '{"result": false}',
        '{"error": "Crazy mode is enabled. Received unexpected response ;)"}',
    ];

    private const CRAZY_POSSIBLE_CODES = [
        // 2xx
        Status::OK,
        // 3xx
        Status::NOT_ACCEPTABLE,
        Status::FORBIDDEN,
        Status::NOT_FOUND,
        // 5xx
        Status::INTERNAL_SERVER_ERROR,
        Status::NOT_IMPLEMENTED,
        Status::BAD_GATEWAY,
        Status::SERVICE_UNAVAILABLE,
        Status::GATEWAY_TIMEOUT,
        Status::HTTP_VERSION_NOT_SUPPORTED,
        Status::VARIANT_ALSO_NEGOTIATES,
        Status::INSUFFICIENT_STORAGE,
        Status::LOOP_DETECTED,
        Status::NOT_EXTENDED,
        Status::NETWORK_AUTHENTICATION_REQUIRED,
    ];

    /**
     * @var string
     */
    private $sourcePath;

    /**
     * @var Data
     */
    private $data;

    /**
     * @var Request
     */
    private $request;

    /**
     * Mock constructor.
     * @param string $mockFilepath
     */
    public function __construct(string $mockFilepath)
    {
        $this->sourcePath = $mockFilepath;
        $this->data = $this->parseSource();
    }

    /**
     * @return Data
     */
    private function parseSource(): Data
    {
        if (file_exists($this->sourcePath)) {
            /** @noinspection PhpIncludeInspection */
            $rawData = (array)include $this->sourcePath;
            return new Data($rawData);
        }

        throw new Exception("File not found: {$this->sourcePath}");
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return sha1($this->sourcePath);
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        $rootPath = dirname(__DIR__);
        return (string)str_replace($rootPath, '.', $this->sourcePath);
    }

    #### Request methods ###############################################################################################

    /**
     * @return array
     */
    public function getRequestMethods(): array
    {
        $validMethods = ["GET", "POST", "PUT", "PATCH", "HEAD", "OPTIONS", "DELETE"];
        $methods = $this->data->find('request.method') ?: 'GET';

        if (is_string($methods)) {
            $methods = explode('|', $methods);
        }

        $result = [];
        foreach ($methods as $method) {
            $addMethods = strtoupper(trim($method));
            if ($addMethods === 'ANY' || $addMethods === '*') {
                $addMethods = $validMethods;
            }

            /** @noinspection SlowArrayOperationsInLoopInspection */
            $result = array_merge($result, (array)$addMethods);
        }

        return array_filter(array_unique($result));
    }

    /**
     * @return array
     */
    public function getRequestHeader(): array
    {
        $headers = $this->data->find('request.header') ?: [];
        return (array)$headers;
    }

    /**
     * @return string
     */
    public function getRequestPath(): string
    {
        return (string)$this->data->find('request.path', '/');
    }

    #### Response methods ##############################################################################################

    /**
     * @return int
     */
    public function getResponseCode(): int
    {
        $codeHandler = $this->data->find('response.code', Status::OK);
        $code = $this->handleCallable($codeHandler, 'int');

        $result = (int)$code;
        if ($this->isCrazyMode()) {
            $result = (int)array_rand(array_flip(self::CRAZY_POSSIBLE_CODES));
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getResponseHeaders(): array
    {
        $headerHandler = $this->data->find('response.headers', ['content-type' => 'text/plain']);
        $headers = (array)$this->handleCallable($headerHandler, 'array');

        $debugHeaders = [
            'X-Mock-Server-Fixture'    => $this->getFilename(),
            'X-Mock-Server-Request-Id' => $this->request->getId(),
        ];

        if ($this->isCrazyMode()) {
            return $debugHeaders;
        }

        return array_merge($debugHeaders, $headers);
    }

    /**
     * @return string
     */
    public function getResponseBody(): string
    {
        if ($this->isCrazyMode()) {
            return (string)array_rand(array_flip(self::CRAZY_POSSIBLE_BODIES));
        }

        $bodyHandler = $this->data->find('response.body', '');
        $body = $this->handleCallable($bodyHandler, 'string');

        return (string)$body;
    }

    /**
     * @param Request $request
     */
    public function bindRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @param string $sourcePath
     * @return string|null
     */
    public static function isSourceValid(string $sourcePath): ?string
    {
        try {
            Cli::exec(Sys::getBinary() . " -l {$sourcePath}");
        } catch (ProcessFailedException $exception) {
            return (string)$exception->getProcess()->getOutput();
        }

        return null;
    }

    /**
     * @return int
     */
    public function getDelay(): int
    {
        $delayHandler = $this->data->find('control.delay', 0);
        $delay = $this->handleCallable($delayHandler, 'int');

//        if ($this->isCrazyMode()) {
//            $delay += random_int(0, self::CRAZY_MAX_DELAY);
//        }

        return (int)$delay;
    }

    /**
     * @return bool
     */
    public function isCrazyMode(): bool
    {
        $result = false;
        if ($this->isCrazyEnabled()) {
            $result = random_int(0, 1) === 0; // 50%
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isCrazyEnabled(): bool
    {
        $crazyHandler = $this->data->find('control.crazy', false);
        $crazy = $this->handleCallable($crazyHandler, 'bool');

        return (bool)$crazy;
    }

    /**
     * @param mixed       $handler
     * @param string|null $expectedResultType
     * @return mixed
     */
    private function handleCallable($handler, ?string $expectedResultType = null)
    {
        $result = $handler;

        if (is_callable($handler)) {
            $result = $handler($this->request);
        }

        if (null !== $expectedResultType) {
            if ($expectedResultType === 'bool' && !is_bool($result)) {
                throw new Exception("Expected result of callback is boolean");
            }

            if ($expectedResultType === 'int' && !is_int($result)) {
                throw new Exception("Expected result of callback is integer");
            }

            if ($expectedResultType === 'string' && !is_string($result)) {
                throw new Exception("Expected result of callback is string");
            }

            if ($expectedResultType === 'array' && !is_array($result)) {
                throw new Exception("Expected result of callback is array");
            }
        }

        return $result;
    }
}
