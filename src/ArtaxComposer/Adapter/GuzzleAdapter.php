<?php
namespace ArtaxComposer\Adapter;

use ArtaxComposer\Exception\FlowException;
use ArtaxComposer\Exception\NotProvidedException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class GuzzleAdapter extends BaseAdapter implements AdapterInterface
{
    /**
     * @var Response
     */
    protected $response;

    /**
     * @throws \Exception
     */
    public function doRequest()
    {
        if (!$this->uri) {
            throw new NotProvidedException('URI must be provided in order to execute the request');
        }

        if (!$this->method) {
            throw new NotProvidedException('URI must be provided in order to execute the request');
        }

        $request = new Request($this->method, $this->uri, $this->headers, $this->body);

        $client = new Client();

        try {
            $this->response = $client->send($request);
        }
        catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * Status code of the response
     *
     * @return int
     * @throws FlowException
     */
    public function getResponseStatusCode()
    {
        if (!$this->response) {
            throw new FlowException('You have to call the request in order to obtain a status code of the response');
        }

        return (int) $this->response->getStatusCode();
    }

    /**
     * Body of the response
     *
     * @return string
     * @throws FlowException
     */
    public function getResponseBody()
    {
        if (!$this->response) {
            throw new FlowException('You have to call the request in order to obtain the body of the response');
        }

        return json_decode($this->response->getBody(), true);
    }

    /**
     * Check if there is an header in the response
     *
     * @param string $header
     *
     * @return bool
     * @throws FlowException
     */
    public function hasResponseHeader($header)
    {
        if (!$this->response) {
            throw new FlowException('You have to call the request in order to check the headers of the response');
        }

        return $this->response->hasHeader($header);
    }

    /**
     * Get the value of a specific header
     *
     * @param string $header
     *
     * @return string
     * @throws FlowException
     */
    public function getResponseHeader($header)
    {
        if (!$this->response) {
            throw new FlowException('You have to call the request in order to obtain the response');
        }

        if (!$this->hasResponseHeader($header)) {
            return null;
        }

        return $this->response->getHeader($header)[0];
    }
}