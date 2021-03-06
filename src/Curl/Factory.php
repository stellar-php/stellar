<?php declare(strict_types=1);

namespace Stellar\Curl;

use Psr\Http\Message\UriInterface;
use Stellar\Common\Contracts\SingletonInterface;
use Stellar\Container\Abilities\SingletonInstanceTrait;
use Stellar\Curl\Contracts\RequestInterface;
use Stellar\Curl\Contracts\ResponseInterface;
use Stellar\Curl\Request\Request;
use Stellar\Curl\Response\Response;
use Stellar\Factory\Builder;
use Stellar\Factory\Factory as Factorio;

/**
 * todo: make psr7 + psr17 compatible
 */
final class Factory implements SingletonInterface
{
    use SingletonInstanceTrait;

    public const DEFAULT_OPTIONS = [
        \CURLOPT_URL => null,
        \CURLOPT_FOLLOWLOCATION => false,
        \CURLOPT_TIMEOUT => 30,
        \CURLOPT_HEADER => false,

        // default options that should not be changed
        \CURLOPT_RETURNTRANSFER => true,
        \CURLOPT_FAILONERROR => false,
    ];

    // create maakt het daadwerkelijke object en returned deze
    // build maakt een builder met daarin een create method
    public function buildRequest(string $class) : Builder
    {
        return Factorio::build($class)
            ->subclassOf(RequestInterface::class)
            ->withArguments(self::DEFAULT_OPTIONS);
    }

    public function buildResponse(string $class) : Builder
    {
        return Factorio::build($class)
            ->subclassOf(ResponseInterface::class);
    }

    /**
     * Create a new request.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. If
     *     the value is a string, the factory MUST create a UriInterface
     *     instance based on it.
     *
     * @return RequestInterface
     */
    public function createRequest(string $method, $uri) : Request
    {
        /** @var Request $request */
        $request = $this->buildRequest(Request::class)
            ->create();

        $request->withMethod($method);
        $request->withUrl($uri);

        return $request;
    }

    public function createResponse() : Response
    {
        return $this->buildResponse()->create();
    }
}
