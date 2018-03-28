<?php

namespace MessageBird\Common;

use Guzzle\Http\Client as GuzzleHttpClient;
use Guzzle\Http\Exception\RequestException;
use MessageBird\Exceptions;
use MessageBird\Common;

/**
 * Class HttpClient
 *
 * @package MessageBird\Common
 */
class HttpClient
{
    const REQUEST_GET = 'GET';
    const REQUEST_POST = 'POST';
    const REQUEST_DELETE = 'DELETE';
    const REQUEST_PUT = 'PUT';
    const REQUEST_PATCH = "PATCH";

    const HTTP_NO_CONTENT = 204;

    /**
     * @var GuzzleHttpClient
     */
    private $guzzleClient;

    /**
     * @var array
     */
    protected $userAgent = array();

    /**
     * @var Common\Authentication
     */
    protected $Authentication;

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @param string $endpoint
     * @param int    $timeout           > 0
     * @param int    $connectionTimeout >= 0
     * @param array  $headers
     */
    public function __construct($endpoint, $timeout = 10, $connectionTimeout = 2, $headers = array())
    {
        $this->guzzleClient = new GuzzleHttpClient($endpoint, array(
            'request.options' => array(
                'timeout' => 10,
                'connect_timeout' => 2
            )
        ));
        $this->setHeaders($headers);
    }

    /**
     * @param string $userAgent
     */
    public function addUserAgentString($userAgent)
    {
        $this->userAgent[] = $userAgent;
        $this->guzzleClient->setUserAgent(implode(' ', $this->userAgent), false);
    }

    /**
     * @param Common\Authentication $Authentication
     */
    public function setAuthentication(Common\Authentication $Authentication)
    {
        $this->Authentication = $Authentication;
    }

    /**
     * @param string $resourceName
     * @param mixed  $query
     *
     * @return string
     */
    public function getRequestUrl($resourceName, $query)
    {
        $requestUrl = $this->guzzleClient->getBaseUrl() . '/' . $resourceName;
        if ($query) {
            if (is_array($query)) {
                $query = http_build_query($query);
            }
            $requestUrl .= '?' . $query;
        }

        return $requestUrl;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        // BC, this method accepts headers as indexed array, change these into key => value
        $newHeaders = array();
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $parts = explode(':', $value, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                }
            }
            $newHeaders[$key] = $value;
        }
        $this->headers = $newHeaders;
    }

    /**
     * @param string      $method
     * @param string      $resourceName
     * @param mixed       $query
     * @param string|null $body
     *
     * @return array
     *
     * @throws Exceptions\AuthenticateException
     * @throws Exceptions\HttpException
     */
    public function performHttpRequest($method, $resourceName, $query = null, $body = null)
    {
        if ($this->Authentication === null) {
            throw new Exceptions\AuthenticateException('Can not perform API Request without Authentication');
        }

        $uri = $this->getRequestUrl($resourceName, $query);

        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Charset' => 'utf-8',
            'Authorization' => sprintf('AccessKey %s', $this->Authentication->accessKey)
        );

        $headers = array_merge($headers, $this->headers);

        try {
            $response = $this->guzzleClient->createRequest($method, $uri, $headers, $body)
                ->send();
        } catch (RequestException $e) {
            throw new Exceptions\HttpException($e->getMessage(), $e->getCode(), $e);
        }

        $responseStatus = $response->getStatusCode();
        $responseHeader = trim($response->getRawHeaders());
        $responseBody = $response->getBody(true);

        return array ($responseStatus, $responseHeader, $responseBody);
    }
}
