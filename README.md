# artax-composer

ArtaxComposer is a [Zend Framework 2](https://github.com/zendframework/zendframework) service wrapper around the [amphp/artax](https://github.com/amphp/artax) client


## Getting Started

```
composer require pensiero/artax-composer
```

Add `ArtaxComposer` as module in your `application.config.php`

## Usage

You will now have access to the `ArtaxComposer\Service\ArtaxService` service.

You can get it in your factory:
```
/** @var \ArtaxComposer\Service\ArtaxService $artaxService */
$artaxService = $serviceLocator->get('ArtaxComposer\Service\ArtaxService');
```

## Configs

By default ArtaxComposer come with this [configs](blob/master/config/module.config.php)

```
    'artax_composer'  => [

        /*
         * Cache could be:
         *  - null
         *  - an instance of Zend\Cache\Storage\Adapter\AbstractAdapter
         *  - a string rapresenting a service to search inside the serviceLocator
         */
        'cache' => null,

        /*
         * If seeds are enabled, the system will write inside the specified seeds directory the result of each request
         * Clear the seeds directory in order to have fresh results
         */
        'seeds' => [
            'enabled'   => false,
            'directory' => 'data/seeds/',
        ],

        /*
         * Default headers to add inside each request
         */
        'default_headers' => [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
        ],

        /*
         * Enable or not the newrelic extension
         */
        'newrelic' => false,
    ],
```

You can ovveride them in your `module.config.php`


## Available methods

Each methods is chainable, except for the `get()`, `post()`, `put()` and `delete()` methods.

#### setUri(string $uri)

Set the URI of the request.

#### setParams(array $params)

Set the params passed to the request. GET params should not be passed in the uri, but via this method.

#### addHeader(string $name, string $value)

Add an header.

#### setHeaders(array $headers)

Replace all headers via those passed.

#### withHeaders()

Return headers along the response.

#### setAuthToken(string $authToken)

Set an header authorization token in the form key: `Authorization`, value: `Token token="AUTH_TOKEN"`.

#### useCache(int $ttl = null)

Cache each request via the cache defined in `module.config.php` (example below).

#### reset()

Reset all params passed before. Default headers will be restored if previously overwritten.

#### debug()

Instead of the response, return an array of all the configuration passed to the service.

#### returnObject()

The response will be an object.

#### returnArray()

The response will be an array.

#### returnObject()

The response will be a json string.

#### get()

Perform a GET request and `return` a response.

#### post()

Perform a POST request and `return` a response.

#### put()

Perform a PUT request and `return` a response.

#### delete()

Perform a DELETE request and `return` a response.


## Examples

### Simple GET request with params

```
$response = $this
    ->artaxService
    ->setUri('https://api.github.com/users/pensiero')
    ->setParams([
      'bacon' => 'slurp',
    ])
    ->get();
```


### POST request with params and cache

In your `module.config.php`

```
    'service_manager' => [
        'factories'          => [
            'Application\Cache\Redis' => 'Application\Cache\RedisFactory',
        ],
    ],
    'artax_composer'  => [
        'cache' => 'Application\Cache\Redis',
    ],
```

Create `module/src/Application/Cache/RedisFactory.php`
```
<?php
namespace Application\Cache;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Cache\Storage\Adapter\RedisOptions;
use Zend\Cache\Storage\Adapter\Redis;

class RedisFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $redisOptions = new RedisOptions();
        $redisOptions
            ->setServer('YOUR_HOST', 'YOUR_PORT');

        return new Redis($redisOptions);
    }
}
```


Call:
```
$response = $this
    ->artaxService
    ->setUri('https://api.github.com/users/pensiero')
    ->setParams([
      'bacon' => 'slurp',
      'eggs'  => 'top',
    ])
    ->useCache()
    ->post();
```