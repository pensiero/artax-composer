<?php
namespace ArtaxComposer\Service;

use Amp;
use Amp\Artax\Request as ArtaxRequest;
use Amp\Artax\Response as ArtaxResponse;
use Amp\Artax\Message as ArtaxMessage;
use Amp\Artax\Client as ArtaxClient;
use Amp\Artax\SocketException as AmpSocketException;
use Amp\Dns\ResolutionException as AmpResolutionException;
use Nbsock\SocketException as NbsockSocketException;
use Zend\Cache\Storage\Adapter\AbstractAdapter as CacheAdapter;
use Zend\Validator\AbstractValidator;

class ArtaxService
{
    // default ttl of the cache
    const CACHE_TTL = 300;

    // max connection timeout
    const OP_MS_CONNECT_TIMEOUT = 15000;

    // max attempts for artax requests
    const REQUEST_MAX_ATTEMPTS = 2;

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var string
     */
    protected $resultFormat = 'array';

    /**
     * @var string
     */
    protected $authToken;

    /**
     * @var CacheAdapter
     */
    protected $cache;

    /**
     * @var bool
     */
    public $useCache = false;

    /**
     * @var int
     */
    protected $cacheTtl = null;

    /**
     * @var array
     */
    protected $headersToReturn = [];

    /**
     * @var boolean
     */
    protected $debug = false;


    public function __construct($cache = null)
    {
        $this->cache = $cache;
    }

    protected function getHeaders()
    {
        // locale
        $translator = AbstractValidator::getDefaultTranslator();
        $locale = $translator->getLocale();

        $headers = [
            'Accept'          => 'application/json',
            'Content-Type'    => 'application/json; charset=utf-8',
            'X-Client-Auth'   => getenv('API_KEY'),
            'Accept-Language' => $locale,
        ];

        if ($this->authToken) {
            $headers['Authorization'] = 'Token token="' . $this->authToken . '"';
        }

        return $headers;
    }

    /**
     * Decide in which format response should be returned
     *
     * @param $response
     *
     * @return mixed|string
     */
    private function formatResponseToReturn($response)
    {
        switch ($this->resultFormat) {
            case 'array':
                return $response;
            case 'object':
                return json_decode(json_encode($response), false);
            case 'json':
                return json_encode($response);
        }

        return $response;
    }

    /**
     * Cache key used for caching
     *
     * @return string
     */
    private function generateCacheKey()
    {
        return md5(
            implode('|', [
                $this->uri,
                $this->method,
                json_encode($this->getHeaders()),
                json_encode($this->params),
            ])
        );
    }

    /**
     * Do the request (enabled multiple attempts)
     *
     * @param ArtaxMessage $request
     * @param string       $seedFilepath
     * @param int          $attempt
     *
     * @return ArtaxResponse|mixed
     * @throws AmpSocketException
     * @throws NbsockSocketException
     * @throws null
     */
    private function doRequest(ArtaxMessage $request, $seedFilepath, $attempt = 1)
    {
        $artaxClient = new ArtaxClient();
        $artaxClient->setOption(ArtaxClient::OP_MS_CONNECT_TIMEOUT, self::OP_MS_CONNECT_TIMEOUT); // connection timeout

        try {
            /** @var ArtaxResponse $ampResponse */
            $ampResponse = Amp\wait($artaxClient->request($request));
        }
        catch (\Exception $exception) {

            if (
                $exception instanceof AmpSocketException
                || $exception instanceof AmpResolutionException
                || $exception instanceof NbsockSocketException
            ) {
                // try a second attempt
                if ($attempt < self::REQUEST_MAX_ATTEMPTS) {
                    return $this->doRequest($request, $seedFilepath, $attempt + 1);
                }

                // use seeds if we are offline (SocketException mean that we are offline)
                if (getenv('ENV') == 'development' && file_exists($seedFilepath)) {
                    return json_decode(file_get_contents($seedFilepath), true);
                }
            }

            throw $exception;
        }

        return $ampResponse;
    }

    /**
     * @return mixed
     * @throws \Exception
     * @throws null
     *
     * Generic request
     */
    public function request()
    {
        if ($this->method == null || empty($this->method)) {
            throw new \Exception('METHOD is not defined');
        }

        if ($this->uri == null || empty($this->uri)) {
            throw new \Exception('URI is not defined');
        }

        // return debug information if debug is enabled
        if ($this->debug) {
            return $this->debugInfo();
        }

        try {

            // cache key
            $cacheKey = $this->generateCacheKey();

            if ($this->useCache && $this->cache) {

                // if cached response is found, return it formatted
                if ($this->cache->hasItem($cacheKey)) {
                    $response = $this->cache->getItem($cacheKey);
                    return $this->formatResponseToReturn($response);
                }
            }

            // log time (START)
            $timeStart = microtime(true);

            // seeds (only development)
            $seedFilepath = 'data/seeds/' . $cacheKey;
            if (getenv('USE_API_SEEDS') == 'true' && getenv('ENV') == 'development' && file_exists($seedFilepath)) {
                return json_decode(file_get_contents($seedFilepath), true);
            }

            $request = (new ArtaxRequest)->setUri($this->uri)
                                         ->setMethod($this->method)
                                         ->setAllHeaders($this->getHeaders());

            if ($this->params != null) {
                $request->setBody(json_encode($this->params));
            }

            // make the request (first attempt)
            $ampResponse = $this->doRequest($request, $seedFilepath);

            // return response if it's not an ArtaxResponse (it could be a string cached in local file)
            if (!$ampResponse instanceof ArtaxResponse) {
                return $ampResponse;
            }

            // log time (END)
            $timeEnd = microtime(true);
            if (extension_loaded('newrelic')) {
                newrelic_custom_metric('Custom/Artax/Load_time', round($timeEnd - $timeStart));
            }

            // code and body
            $response['code'] = (int) $ampResponse->getStatus();
            $response['body'] = json_decode($ampResponse->getBody(), true);

            // optional headers
            foreach ($this->headersToReturn as $headerToReturn) {
                if ($ampResponse->hasHeader($headerToReturn)) {
                    $response['headers'][$headerToReturn] = $ampResponse->getHeader($headerToReturn)[0];
                }
            }

            // store response in cache
            if ($this->useCache && $this->cache) {
                $cacheKey = $this->generateCacheKey();
                $this->cache->setItem($cacheKey, $response);

                // TODO cache ttl (set timeout on specific cache key)
                if ($this->cacheTtl) {}
            }

            // reformat response
            $response = $this->formatResponseToReturn($response);

            // seeds
            if (getenv('ENV') == 'development') {
                file_put_contents($seedFilepath, json_encode($response));
            }

            return $response;

        } catch (\Exception $error) {
            throw new \Exception($error);
        }
    }

    /**
     * Headers to return with the response
     *
     * @param array $headers
     *
     * @return $this
     */
    public function withHeaders($headers = [])
    {
        $this->headersToReturn = $headers;

        return $this;
    }

    /**
     * Use the cache
     *
     * @param int $ttl
     *
     * @return $this
     */
    public function useCache($ttl = self::CACHE_TTL)
    {
        $this->useCache = true;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * @return mixed
     * @throws \Exception
     *
     * GET request
     */
    public function get()
    {
        $this->method = 'GET';

        return $this->request();
    }

    /**
     * @return mixed
     * @throws \Exception
     *
     * POST request
     */
    public function post()
    {
        $this->method = 'POST';

        if ($this->params == null || empty($this->params)) {
            throw new \Exception('POST params are not defined');
        }

        return $this->request();
    }

    /**
     * @return mixed
     * @throws \Exception
     *
     * PUT request
     */
    public function put()
    {
        $this->method = 'PUT';

        return $this->request();
    }

    /**
     * @return mixed
     * @throws \Exception
     *
     * DELETE request
     */
    public function delete()
    {
        $this->method = 'DELETE';

        return $this->request();
    }

    public function returnObject()
    {
        $this->resultFormat = 'object';

        return $this;
    }

    public function returnArray()
    {
        $this->resultFormat = 'array';

        return $this;
    }

    public function returnJson()
    {
        $this->resultFormat = 'json';

        return $this;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param mixed $uri
     *
     * @return $this
     */
    public function setUri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @param mixed $params
     *
     * @return $this
     */
    public function setParams($params)
    {
        $this->params = (array) $params;

        return $this;
    }

    /**
     * @param string $authToken
     *
     * @return $this
     */
    public function setAuthToken($authToken)
    {
        $this->authToken = $authToken;

        return $this;
    }

    /**
     * Reset all informations previously setted
     */
    public function reset()
    {
        $this->method = 'GET';
        $this->uri = null;
        $this->params = null;
        $this->resultFormat = 'array';
        $this->authToken = null;
        $this->useCache = false;
        $this->cacheTtl = null;
        $this->headersToReturn = [];

        return $this;
    }

    /**
     * Show debug information
     *
     * @return array
     */
    public function debug()
    {
        $this->debug = true;
    }

    /**
     * Return debug informations
     *
     * @return array
     */
    private function debugInfo()
    {
        return [
            'method'          => $this->method,
            'uri'             => $this->uri,
            'params'          => $this->params,
            'paramsJson'      => json_encode($this->params),
            'resultFormat'    => $this->resultFormat,
            'authToken'       => $this->authToken,
            'cache'           => $this->cache,
            'useCache'        => $this->useCache,
            'cacheTtl'        => $this->cacheTtl,
            'headers'         => $this->getHeaders(),
            'headersToReturn' => $this->headersToReturn,
        ];
    }
}
