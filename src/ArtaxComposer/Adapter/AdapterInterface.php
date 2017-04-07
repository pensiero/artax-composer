<?php
namespace ArtaxComposer\Adapter;

interface AdapterInterface
{
    /**
     * Execute the request
     */
    public function doRequest();

    /**
     * Set the URI for the request
     *
     * @param string $uri
     *
     * @return $this
     */
    public function setUri($uri);

    /**
     * Set the method for the request
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method);

    /**
     * Set the headers for the request
     *
     * @param array $headers
     *
     * @return $this
     */
    public function setHeaders($headers);

    /**
     * Set the body for the request
     *
     * @param string $body
     *
     * @return $this
     */
    public function setBody($body);

    /**
     * Get the status code of the response (after the request is done)
     *
     * @return string
     */
    public function getResponseStatusCode();

    /**
     * Get the body of the response (after the request is done)
     *
     * @return string
     */
    public function getResponseBody();

    /**
     * Check if an header was returned in the response (after the request is done)
     *
     * @param string $header
     *
     * @return bool
     */
    public function hasResponseHeader($header);

    /**
     * Get an header from the response (after the request is done)
     *
     * @param string $header
     *
     * @return string
     */
    public function getResponseHeader($header);
}