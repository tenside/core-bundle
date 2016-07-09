<?php

/**
 * This file is part of tenside/core-bundle.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/core-bundle
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/core-bundle/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/core-bundle
 * @filesource
 */

namespace Tenside\CoreBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * This class converts exceptions into proper responses.
 */
class ExceptionListener
{
    /**
     * The exception logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * The debug flag.
     *
     * @var bool
     */
    private $debug;

    /**
     * Create a new instance.
     *
     * @param LoggerInterface $logger The logger.
     *
     * @param bool            $debug  The debug flag.
     */
    public function __construct(LoggerInterface $logger, $debug = false)
    {
        $this->logger = $logger;
        $this->debug  = $debug;
    }

    /**
     * Maps known exceptions to HTTP exceptions.
     *
     * @param GetResponseForExceptionEvent $event The event object.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $response  = null;

        switch (true) {
            case ($exception instanceof NotFoundHttpException):
                $response = $this->createNotFoundResponse($event->getRequest(), $exception);
                break;
            case ($exception instanceof AccessDeniedHttpException):
            case ($exception instanceof UnauthorizedHttpException):
            case ($exception instanceof BadRequestHttpException):
            case ($exception instanceof ServiceUnavailableHttpException):
            case ($exception instanceof NotAcceptableHttpException):
            case ($exception instanceof HttpException):
                /** @var HttpException $exception */
                $response = $this->createHttpExceptionResponse($exception);
                break;
            case ($exception instanceof AuthenticationCredentialsNotFoundException):
                $response = $this->createUnauthenticatedResponse($exception);
                break;
            default:
        }

        if (null === $response) {
            $response = $this->createInternalServerError($exception);
        }

        $event->setResponse($response);
    }

    /**
     * Create a 404 response.
     *
     * @param Request    $request   The http request.
     *
     * @param \Exception $exception The exception.
     *
     * @return JsonResponse
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function createNotFoundResponse($request, $exception)
    {
        $message = $exception->getMessage();
        if (empty($message)) {
            $message = 'Uri ' . $request->getRequestUri() . ' could not be found';
        }

        return new JsonResponse(
            [
                'status'  => 'ERROR',
                'message' => $message
            ],
            JsonResponse::HTTP_NOT_FOUND
        );
    }

    /**
     * Create a http response.
     *
     * @param HttpException $exception The exception to create a response for.
     *
     * @return JsonResponse
     */
    private function createHttpExceptionResponse(HttpException $exception)
    {
        return new JsonResponse(
            [
                'status'  => 'ERROR',
                'message' => $exception->getMessage()
            ],
            $exception->getStatusCode(),
            $exception->getHeaders()
        );
    }

    /**
     * Create a http response.
     *
     * @param AuthenticationCredentialsNotFoundException $exception The exception to create a response for.
     *
     * @return JsonResponse
     */
    private function createUnauthenticatedResponse(AuthenticationCredentialsNotFoundException $exception)
    {
        return new JsonResponse(
            [
                'status'  => 'ERROR',
                'message' => $exception->getMessageKey()
            ],
            JsonResponse::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Create a 500 response.
     *
     * @param \Exception $exception The exception to log.
     *
     * @return JsonResponse
     */
    private function createInternalServerError(\Exception $exception)
    {
        $message = sprintf(
            '%s: %s (uncaught exception) at %s line %s',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        $this->logger->error($message, array('exception' => $exception));

        $response = [
            'status'  => 'ERROR',
            'message' => JsonResponse::$statusTexts[JsonResponse::HTTP_INTERNAL_SERVER_ERROR]
        ];

        if ($this->debug) {
            $response['exception'] = $this->formatException($exception);
        }

        return new JsonResponse($response, JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Format an exception (and enclosed child exceptions) as array for a JSON response.
     *
     * @param \Exception $exception The exception to format.
     *
     * @return array
     */
    private function formatException(\Exception $exception)
    {
        $result = [
            'message' => $exception->getMessage(),
            'type'      => get_class($exception),
            'code'      => $exception->getCode(),
            'trace' => [
                [
                    'file' => $exception->getFile() ?: 'unknown',
                    'line' => $exception->getLine() ?: 'unknown',
                ]
            ]
        ];

        foreach ($exception->getTrace() as $frame) {
            $result['trace'][] = $this->formatStackFrame($frame);
        }

        if ($previous = $exception->getPrevious()) {
            $result['previous'] = $this->formatException($previous);
        }

        return $result;
    }

    /**
     * Convert a stack frame to array.
     *
     * @param array $frame The stack frame to convert.
     *
     * @return array
     */
    private function formatStackFrame($frame)
    {
        return [
            'file'      => isset($frame['file']) ? $frame['file'] : 'unknown',
            'line'      => isset($frame['line']) ? $frame['line'] : 'unknown',
            'function'  => (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . $frame['function'],
            'arguments' => $this->formatArguments($frame['args'])
        ];
    }

    /**
     * Reformat an argument list.
     *
     * @param array $arguments The arguments to reformat.
     *
     * @return mixed
     */
    private function formatArguments($arguments)
    {
        $result = [];
        foreach ($arguments as $key => $argument) {
            if (is_object($argument)) {
                $result[$key] = get_class($argument);
                continue;
            }

            if (is_array($argument)) {
                $result[$key] = $this->formatArguments($argument);
                continue;
            }

            $result[$key] = $argument;
        }

        return $result;
    }
}
