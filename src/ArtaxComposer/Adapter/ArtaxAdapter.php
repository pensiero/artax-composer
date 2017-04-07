<?php
namespace ArtaxComposer\Adapter;

use Amp;
use Amp\Artax\Message as ArtaxMessage;
use Amp\Artax\Client as ArtaxClient;
use Amp\Artax\Request as ArtaxRequest;
use Amp\Artax\Response as ArtaxResponse;
use Amp\Artax\SocketException as AmpSocketException;
use Amp\Dns\ResolutionException as AmpResolutionException;
use ArtaxComposer\Exception\FlowException;
use ArtaxComposer\Exception\NotProvidedException;
use Nbsock\SocketException as NbsockSocketException;

class ArtaxAdapter extends BaseAdapter implements AdapterInterface
{
    // max connection timeout
    const OP_MS_CONNECT_TIMEOUT = 15000;

    // max attempts for artax requests
    const REQUEST_MAX_ATTEMPTS = 2;

    /**
     * @var ArtaxResponse
     */
    private $response;

    /**
     * Do the request (enabled multiple attempts)
     *
     * @param ArtaxMessage $request
     * @param int          $attempt
     *
     * @throws AmpSocketException
     * @throws NbsockSocketException
     * @throws null
     */
    private function doArtaxRequest(ArtaxMessage $request, $attempt = 1)
    {
        $artaxClient = new ArtaxClient();

        // connection timeout
        $artaxClient->setOption(ArtaxClient::OP_MS_CONNECT_TIMEOUT, self::OP_MS_CONNECT_TIMEOUT);

        try {
            /** @var ArtaxResponse $ampResponse */
            $this->response = Amp\wait($artaxClient->request($request));
        }
        catch (\Exception $exception) {

            if (
                $exception instanceof AmpSocketException
                || $exception instanceof AmpResolutionException
                || $exception instanceof NbsockSocketException
            ) {
                // try a second attempt
                if ($attempt < self::REQUEST_MAX_ATTEMPTS) {
                    $this->doArtaxRequest($request, $attempt + 1);
                    return;
                }
            }

            throw $exception;
        }
    }

    /**
     * Execute the request
     */
    public function doRequest()
    {
        if (!$this->uri) {
            throw new NotProvidedException('URI must be provided in order to execute the request');
        }

        if (!$this->method) {
            throw new NotProvidedException('URI must be provided in order to execute the request');
        }

        $request = (new ArtaxRequest)
            ->setUri($this->uri)
            ->setMethod($this->method)
            ->setAllHeaders($this->headers);

        if ($this->body != null) {
            $request->setBody($this->body);
        }

        // make the request (first attempt)
        $this->doArtaxRequest($request);
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

        return (int) $this->response->getStatus();
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
            throw new FlowException('You have to call the request in order to obtain a status code of the response');
        }

        if (!$this->hasResponseHeader($header)) {
            return null;
        }

        return $this->response->getHeader($header)[0];
    }
}