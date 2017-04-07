<?php
namespace ArtaxComposer\Service;

use ArtaxComposer\Adapter\AdapterInterface;
use ArtaxComposer\Exception\NotProvidedException;
use Zend\Cache\Storage\Adapter\AbstractAdapter as AbstractCacheAdapter;

class ArtaxService
{
    // default ttl of the cache
    const CACHE_TTL = 300;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var AbstractCacheAdapter
     */
    protected $cache;

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
    protected $headers = [];

    /**
     * @var array
     */
    protected $headersToReturn = [];

    /**
     * @var boolean
     */
    protected $debug = false;

    /**
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * ArtaxService constructor.
     *
     * @param array                     $config
     * @param AbstractCacheAdapter|null $cache
     */
    public function __construct($config, $cache = null)
    {
        $this->config = $config;
        $this->cache = $cache;

        // initialize the adapter
        $this->initAdapter();

        // default headers specificied in config
        if (isset($this->config['default_headers'])) {
            $this->setHeaders($this->config['default_headers']);
        }
    }

    private function initAdapter()
    {
        // check if class exit
        if (!class_exists($this->config['adapter'])) {
            throw new NotProvidedException('An adapter must be provided via config in order to run ArtaxComposer');
        }

        // instanciate the adapter
        $this->adapter = new $this->config['adapter']();

        // check if the adapter implements the AdapterInterface
        if (!($this->adapter instanceof AdapterInterface)) {
            throw new NotProvidedException('The adapter must implement an AdapterInterface in order to run ArtaxComposer');
        }
    }

    /**
     * Decide in which format response should be returned
     *
     * @param array $response
     *
     * @return mixed|string
     */
    private function formatResponseToReturn(array $response)
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
                json_encode($this->headers),
                json_encode($this->params),
            ])
        );
    }

    /**
     * Elaborate seeds filepath based on cachekey
     *
     * @return string
     */
    private function getSeedsFilepath()
    {
        // directory and filepath
        $directory = rtrim($this->config['seeds']['directory'], '/') . '/';

        // create directory if not exist
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        // cache key
        $cacheKey = $this->generateCacheKey();

        return $directory . $cacheKey;
    }

    /**
     * Return seeds if there are
     *
     * @return bool|mixed
     * @throws NotProvidedException
     */
    private function findSeeds()
    {
        if (!isset($this->config['seeds'])) {
            return false;
        }

        // check if seeds are enabled
        if ($this->config['seeds']['enabled'] !== true) {
            return false;
        }

        // seeds directory
        if (empty($this->config['seeds']['directory'])) {
            throw new NotProvidedException('Seeds directory must be provided in order to write seeds.');
        }

        // filepath
        $filepath = $this->getSeedsFilepath();

        // return only if seeds are present
        if (file_exists($filepath)) {
            $response = json_decode(file_get_contents($filepath), true);
            return $this->formatResponseToReturn($response);
        }

        return false;
    }

    /**
     * Write seeds if it can
     *
     * @param $response
     *
     * @return bool|null
     * @throws NotProvidedException
     */
    private function writeSeeds($response)
    {
        if (!isset($this->config['seeds'])) {
            return false;
        }

        // check if seeds are enabled
        if ($this->config['seeds']['enabled'] !== true) {
            return false;
        }

        // seeds directory
        if (empty($this->config['seeds']['directory'])) {
            throw new NotProvidedException('Seeds directory must be provided in order to write seeds.');
        }

        // filepath
        $filepath = $this->getSeedsFilepath();

        // return only if seeds are present
        if (!file_exists($filepath)) {

            file_put_contents($filepath, json_encode($response));

            return true;
        }

        return null;
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

            // cache
            if ($this->useCache) {

                // if cached response is found, return it formatted
                if ($this->cache->hasItem($cacheKey)) {
                    $response = $this->cache->getItem($cacheKey);
                    return $this->formatResponseToReturn($response);
                }
            }

            // log time (START)
            $timeStart = microtime(true);

            // return seeds if present
            if ($seeds = $this->findSeeds()) {
                return $seeds;
            }

            $this
                ->adapter
                ->setUri($this->uri)
                ->setMethod($this->method)
                ->setHeaders($this->headers);

            // body
            if ($this->params != null) {
                $this->adapter->setBody(json_encode($this->params));
            }

            // log time (END)
            $timeEnd = microtime(true);
            if (isset($this->config['newrelic']) && $this->config['newrelic'] === true && extension_loaded('newrelic')) {
                newrelic_custom_metric('Custom/Artax/Load_time', round($timeEnd - $timeStart));
            }

            // execute the request
            $this->adapter->doRequest();

            // code and body
            $response['code'] = $this->adapter->getResponseStatusCode();
            $response['body'] = $this->adapter->getResponseBody();

            // optional headers to return
            foreach ($this->headersToReturn as $headerToReturn) {
                if ($this->adapter->hasResponseHeader($headerToReturn)) {
                    $response['headers'][$headerToReturn] = $this->adapter->getResponseHeader($headerToReturn);
                }
            }

            // store response in cache
            if ($this->useCache) {
                $this->cache->setItem($cacheKey, $response);

                // TODO cache ttl (set timeout on specific cache key)
                if ($this->cacheTtl) {}
            }

            // seeds
            $this->writeSeeds($response);

            // reformat response
            return $this->formatResponseToReturn($response);

        } catch (\Exception $error) {
            throw new \Exception($error);
        }
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
     * Add an header
     *
     * @param $name
     * @param $value
     */
    public function addHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * Replace all headers
     *
     * @param $headers
     *
     * @internal param $name
     * @internal param $value
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
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
     * @param string $authToken
     *
     * @return $this
     */
    public function setAuthToken($authToken)
    {
        $this->authToken = $authToken;

        // add authorization token header
        $this->addHeader('Authorization', 'Token token="' . $this->authToken . '"');

        return $this;
    }

    /**
     * Use the cache
     *
     * @param int $ttl
     *
     * @return $this
     * @throws NotProvidedException
     */
    public function useCache($ttl = self::CACHE_TTL)
    {
        if (!$this->cache) {
            return $this;
        }

        $this->useCache = true;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Return debug information
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
            'headers'         => $this->headers,
            'headersToReturn' => $this->headersToReturn,
        ];
    }

    /**
     * Show debug information
     *
     * @return array
     */
    public function debug()
    {
        $this->debug = true;

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
        $this->headers = [];
        $this->headersToReturn = [];
        $this->debug = false;

        // default headers specificied in config
        if (isset($this->config['default_headers'])) {
            $this->setHeaders($this->config['default_headers']);
        }

        return $this;
    }

    /**
     * The response will be in object format
     *
     * @return $this
     */
    public function returnObject()
    {
        $this->resultFormat = 'object';

        return $this;
    }

    /**
     * The response will be in array format
     *
     * @return $this
     */
    public function returnArray()
    {
        $this->resultFormat = 'array';

        return $this;
    }

    /**
     * The response will be in JSON format
     *
     * @return $this
     */
    public function returnJson()
    {
        $this->resultFormat = 'json';

        return $this;
    }

    /**
     * GET request
     *
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
     * POST request
     *
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
     * PUT request
     *
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
     * DELETE request
     *
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
}
