<?php

declare(strict_types=1);

namespace Conia\Error;

use ErrorException;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;
use Psr\Http\Message\StreamInterface as Stream;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/** @psalm-api */
class Handler implements Middleware
{
    public function __construct(
        protected readonly ResponseFactory $responseFactory,
        protected readonly StreamFactory $streamFactory,
        protected readonly ?Logger $logger = null
    ) {
        set_error_handler([$this, 'handleError'], E_ALL);
        set_exception_handler([$this, 'emitException']);
    }

    public function __destruct()
    {
        restore_error_handler();
        restore_exception_handler();
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->getResponse($e, $request);
        }
    }

    public function handleError(
        int $level,
        string $message,
        string $file = '',
        int $line = 0,
    ): bool {
        if ($level & error_reporting()) {
            throw new ErrorException($message, $level, $level, $file, $line);
        }

        return false;
    }

    public function emitException(Throwable $exception): void
    {
        $this->log($exception);
        $response = $this->getResponse($exception, null);

        echo (string)$response->getBody();
    }

    public function getResponse(Throwable $exception, ?Request $request): Response
    {
        $response = $this->responseFactory->createResponse(404)
            ->withBody($this->render($exception, $request));

        return $response;
    }

    public function render(Throwable $exception, ?Request $request): Stream
    {
        return $this->streamFactory->createStream($exception->getMessage());
    }

    public function log(Throwable $exception): void
    {
        if ($this->logger) {
            $this->logger->error('Uncaught Exception:', ['exception' => $exception]);
        }
    }
}