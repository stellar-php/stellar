<?php declare(strict_types=1);

namespace Stellar\Curl\Response;

use Stellar\Curl\Contracts\RequestInterface;
use Stellar\Curl\Contracts\ResponseInterface;
use Stellar\Curl\Support\Parse;
use Stellar\Exceptions\Common\InvalidType;
use Stellar\Common\Type;

class Response implements ResponseInterface
{
    /** @var int */
    protected $_httpCode;

    /** @var ?string[] */
    protected $_headerLines;

    /** @var ?string */
    protected $_body;

    /**
     * @throws InvalidType
     */
    public function __construct(RequestInterface $request, string $response)
    {
        $resource = $request->getResource();
        if (!\is_resource($resource)) {
            throw new InvalidType('resource (curl)', Type::details($resource));
        }

        $this->_httpCode = \curl_getinfo($resource, \CURLINFO_HTTP_CODE);

        $usedOptions = $request->getOptions();
        if ($usedOptions[ \CURLOPT_NOBODY ] ?? false) {
            $this->_headerLines = Parse::headerLines($response);
        }
        elseif ($usedOptions[ \CURLOPT_HEADER ] ?? false) {
            $headerSize = \curl_getinfo($resource, \CURLINFO_HEADER_SIZE);
            $this->_headerLines = Parse::headerLines(\substr($response, 0, $headerSize));
            $this->_body = \trim(\substr($response, $headerSize));
        }
        else {
            $this->_body = \trim($response);
        }
    }

    public function getHttpCode() : int
    {
        return $this->_httpCode;
    }

    /**
     * Get the headers per line from the request.
     */
    public function getHeaderLines() : ?array
    {
        return $this->_headerLines;
    }

    /**
     * Get the body from the request.
     */
    public function getBody() : ?string
    {
        return $this->_body;
    }
}
