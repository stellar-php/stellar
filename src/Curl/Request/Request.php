<?php declare(strict_types=1);

namespace Stellar\Curl\Request;

use Stellar\Common\Contracts\StringableInterface;
use Stellar\Common\Abilities\StringableTrait;
use Stellar\Common\ArrayUtil;
use Stellar\Common\Type;
use Stellar\Curl\ConstList;
use Stellar\Curl\Contracts\RequestInterface;
use Stellar\Curl\Contracts\OptionableInterface;
use Stellar\Curl\Contracts\OptionsInterface;
use Stellar\Curl\Contracts\ResponseInterface;
use Stellar\Curl\Exceptions\RequestExecutionException;
use Stellar\Curl\Response\Response;
use Stellar\Curl\Curl;
use Stellar\Curl\Factory;
use Stellar\Curl\Support\Parse;
use Stellar\Curl\Support\Utils;
use Stellar\Exceptions\Common\InvalidType;
use Stellar\Factory\Exceptions\CreationException;

class Request implements RequestInterface, OptionableInterface, StringableInterface
{
    use StringableTrait;

    /** @var string */
    protected $_method = Curl::METHOD_GET;

    /** @var string */
    protected $_url;

    /** @var array<string,string> */
    protected $_queryParams = [];

    /** @var array<string,string> */
    protected $_headers = [];

    /** @var array<int,mixed> */
    protected $_options;

    /** @var bool */
    protected $_prepOptions = false;

    /** @var bool */
    protected $_throwExceptionOnFailure = false;

    /** @var ?resource */
    protected $_resource;

    /** @var ?int */
    protected $_errorCode;

    /** @var ?string[] */
    protected $_sendHeaders;

    /** @var ?string */
    protected $_rawResponse;

    /** @var ?ResponseInterface */
    protected $_response;

    /** @var string */
    protected $_responseClass = Response::class;

    protected function _parseOption(int $option, $value)
    {
        switch ($option) {
            case \CURLOPT_URL:
                // todo: parse url
                [ $url, $query ] = Utils::parseUrl($value);
                $this->withUrl($url);
                $this->withQueryParams($query);
                break;

            case \CURLOPT_HTTPHEADER:
                // todo: parse headers
                $this->withHeaders(Utils::parseHeaders($value));
                break;

            case \CURLOPT_NOBODY:
                $this->withMethod('HEAD');
                break;

            case \CURLOPT_CUSTOMREQUEST:
                $this->withMethod($value);
                break;
        }

        return $value;
    }

    protected function _prepOptions() : bool
    {
        $result = $this->_prepOptions;
        if ($result) {
            $this->_prepOptions = false;

            $this->_options[ \CURLOPT_URL ] = !empty($this->_queryParams)
                ? $this->_url . '?' . \http_build_query($this->_queryParams, '', '&')
                : $this->_url;

            if (!empty($this->_headers)) {
                $this->_options[ \CURLOPT_HTTPHEADER ] = ArrayUtil::join(': ', $this->_headers);
            }
        }

        return $result;
    }

    protected function _processResponse(string $response) : void
    {
        if (true === $this->getOption(\CURLINFO_HEADER_OUT)) {
            $headers = \curl_getinfo($this->_resource, \CURLINFO_HEADER_OUT);
            if (false !== $headers) {

                $this->_sendHeaders = Parse::headerLines($headers);
            }
        }

        $this->_rawResponse = $response;
    }

    public function __debugInfo()
    {
        $this->_prepOptions();

        $result = \get_object_vars($this);
        $result['_options'] = Utils::constantNamesKeys($this->_options);

        return $result;
    }

    /** {@inheritdoc} */
    public function __destruct()
    {
        $this->close();
    }

    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->_options = Utils::filter($options);
        }
    }

    /** {@inheritdoc} */
    public function with(OptionsInterface $options) : self
    {
        $this->_options = Utils::merge($this->_options, $options->toArray());

        return $this;
    }

    /**
     * @return $this
     */
    public function withMethod(string $method) : self
    {
        $this->_method = $method;

        unset(
            $this->_options[ \CURLOPT_NOBODY ],
            $this->_options[ \CURLOPT_CUSTOMREQUEST ]
        );

        $unsetPost = false;
        switch (\strtolower($method)) {
            case 'get':
                $unsetPost = true;
                break;

            case 'head':
                $this->_options[ \CURLOPT_NOBODY ] = true;
                $unsetPost = true;
                break;

            case 'post':
                $this->_options[ \CURLOPT_POST ] = true;
                $this->_options[ \CURLOPT_POSTFIELDS ] = [];
                break;

            case 'put':
                $this->_options[ \CURLOPT_POST ] = true;
                $this->_options[ \CURLOPT_POSTFIELDS ] = [];
                $this->_options[ \CURLOPT_CUSTOMREQUEST ] = $method;
                break;

            default:
                $this->_options[ \CURLOPT_CUSTOMREQUEST ] = $method;
                $unsetPost = true;
                break;
        }

        if ($unsetPost) {
            unset(
                $this->_options[ \CURLOPT_POST ],
                $this->_options[ \CURLOPT_POSTFIELDS ]
            );
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function withUrl(string $url) : self
    {
        $this->_url = $url;
        $this->_prepOptions = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function withHeaders(array $headers) : self
    {
        $this->_headers = $headers;
        $this->_prepOptions = true;

        return $this;
    }

    /**
     * Add a header to the request.
     *
     * @return $this
     */
    public function withHeader(string $name, string $value) : self
    {
        $this->_headers[ $name ] = $value;
        $this->_prepOptions = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function withReferer(string $referer) : self
    {
        $this->_options[ \CURLOPT_REFERER ] = $referer;

        return $this;
    }

    /**
     * Set the GET query parameters of the request URL.
     *
     * @param array<string,string> $queryParams
     * @return $this
     */
    public function withQueryParams(array $queryParams) : self
    {
        $this->_queryParams = $queryParams;
        $this->_prepOptions = true;

        return $this;
    }

    /**
     * Add a GET query parameter to the request URL.
     *
     * @return $this
     */
    public function withQueryParam(string $name, string $value) : self
    {
        $this->_queryParams[ $name ] = $value;
        $this->_prepOptions = true;

        return $this;
    }

    /**
     * @param array<string,string> $postFields
     * @return $this
     */
    public function withPostFields(array $postFields) : self
    {
        $this->_options[ \CURLOPT_POSTFIELDS ] = $postFields;

        return $this;
    }

    /**
     * @return $this
     */
    public function withPostField(string $name, string $value) : self
    {
        $this->_options[ \CURLOPT_POSTFIELDS ][ $name ] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function withTimeout(float $timeout = 30) : self
    {
        $this->_options[ \CURLOPT_TIMEOUT_MS ] = $timeout * 1000;

        return $this;
    }

    /**
     * @return $this
     * @see getSendHeaders()
     */
    public function withRequestHeaders(bool $bool = true) : self
    {
        $this->_options[ \CURLINFO_HEADER_OUT ] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function withResponseHeaders(bool $bool = true) : self
    {
        $this->_options[ \CURLOPT_HEADER ] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function withResponseAs(string $responseClass) : self
    {
        $this->_responseClass = $responseClass;

        return $this;
    }

    /**
     * @return $this
     */
    public function allowRedirect(bool $allowRedirect = true) : self
    {
        $this->_options[ \CURLOPT_FOLLOWLOCATION ] = $allowRedirect;

        return $this;
    }

    /**
     * @return $this
     */
    public function resumeFrom(int $offset) : self
    {
        $this->_options[ \CURLOPT_RESUME_FROM ] = $offset;

        return $this;
    }

    /**
     * @return $this
     */
    public function throwExceptionOnFailure(bool $bool = true) : self
    {
        $this->_throwExceptionOnFailure = $bool;

        return $this;
    }

    /** {@inheritdoc} */
    public function hasOption(int $option) : bool
    {
        $this->_prepOptions();

        return \array_key_exists($option, $this->_options);
    }

    /** {@inheritdoc} */
    public function getOption(int $option)
    {
        $this->_prepOptions();

        return $this->_options[ $option ] ?? null;
    }

    /** {@inheritdoc} */
    public function getOptions() : array
    {
        $this->_prepOptions();

        return $this->_options;
    }

    public function getUrl() : ?string
    {
        $this->_prepOptions();

        return $this->_options[ \CURLOPT_URL ] ?? $this->_url ?? null;
    }

    /** {@inheritdoc} */
    public function getResource()
    {
        return $this->_resource;
    }

    public function getErrorCode() : ?int
    {
        return $this->_errorCode;
    }

    public function getErrorMessage() : ?string
    {
        return $this->_errorCode ? \curl_strerror($this->_errorCode) : null;
    }

    /**
     * Get the headers sent by the request, but only if the request is executed and the
     * `\CURLINFO_HEADER_OUT` option is configured.
     *
     * @return ?string[]
     * @see    withRequestHeaders()
     */
    public function getSendHeaders() : ?array
    {
        return $this->_sendHeaders;
    }

    /**
     * Get the raw response once the request is executed.
     */
    public function getRawResponse() : ?string
    {
        return $this->_rawResponse;
    }

    public function isInitialized() : bool
    {
        return null !== $this->_resource;
    }

    /** {@inheritdoc} */
    public function isExecuted() : bool
    {
        return null !== $this->_rawResponse;
    }

    /** {@inheritdoc} */
    public function isClosed() : bool
    {
        return $this->isExecuted() && null === $this->_resource;
    }

    /**
     * Indicates if the response failed with an error code.
     */
    public function hasError() : bool
    {
        return $this->_errorCode > 0;
    }

    /** {@inheritdoc} */
    public function init() : self
    {
        if (!$this->isInitialized()) {
            $this->_resource = \curl_init();
            \curl_setopt_array($this->_resource, $this->getOptions());
        }

        return $this;
    }

    /** {@inheritdoc} */
    public function execute() : self
    {
        $this->init();

        $this->_sendHeaders = null;
        $this->_rawResponse = null;
        $this->_response = null;

        $response = \curl_exec($this->_resource) ?: null;
        if (false !== $response) {
            $this->_errorCode = 0;
            $this->_processResponse((string) $response);
        }
        else {
            $this->_errorCode = \curl_errno($this->_resource);
            if ($this->_throwExceptionOnFailure) {
                throw new RequestExecutionException($this->_errorCode, $this->getErrorMessage());
            }
        }

        return $this;
    }

    /**
     * @param resource $multiResource
     * @return $this
     * @throws InvalidType
     */
    public function processMultiResponse($multiResource, int $errorCode = 0) : self
    {
        if (!\is_resource($multiResource)) {
            throw new InvalidType('resource', Type::details($multiResource));
        }

        if (!\in_array($errorCode, ConstList::errorConstants(), true)) {
            // todo: invalid error code
        }

        $this->_errorCode = $errorCode;
        $this->_processResponse(\curl_multi_getcontent($this->_resource));

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws RequestExecutionException
     * @throws CreationException
     */
    public function response(?string $responseClass = null) : ResponseInterface
    {
        if (!$this->isExecuted()) {
            $this->execute();
        }

        if (null === $this->_response) {
            $this->_response = Factory::instance()
                ->buildResponse($responseClass ?? $this->_responseClass)
                ->withArguments($this, $this->_rawResponse)
                ->create();
        }

        return $this->_response;
    }

    /** {@inheritdoc} */
    public function close() : void
    {
        if (null !== $this->_resource) {
            \curl_close($this->_resource);
            $this->_resource = null;
        }
    }

    /**
     * Execute the request, close the resource, and return the raw response as a string.
     *
     * @return string
     * @throws RequestExecutionException
     */
    public function __toString() : string
    {
        if (null === $this->_rawResponse) {
            $this->execute();
            $this->close();
        }

        return $this->_rawResponse ?? '';
    }
}
