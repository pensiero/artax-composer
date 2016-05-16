<?php
namespace ArtaxComposer\ServiceFactory;

use Zend\Cache\Storage\Adapter\AbstractAdapter as AbstractCacheAdapter;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ArtaxComposer\Service\ArtaxService;

class ArtaxServiceFactory implements FactoryInterface
{
    /**
     * Load the cache via the Cache config
     *
     * @param $config
     *
     * @return null|AbstractCacheAdapter
     */
    private function loadCache($config, $serviceLocator)
    {
        if (!isset($config['cache'])) {
            return null;
        }

        if ($config['cache'] == null) {
            return null;
        }

        // cache is an instance of the AbstractCacheAdapter
        if ($config['cache'] instanceof AbstractCacheAdapter) {
            return $config['cache'];
        }

        // cache is a string, find the cache in the service locator
        $cache = $serviceLocator->get($config['cache']);

        // check if the cache is instance of the AbstractCacheAdapter
        if (!$cache instanceof AbstractCacheAdapter) {
            throw new \UnexpectedValueException('Cache must be an instance of \Zend\Cache\Storage\Adapter\AbstractAdapter');
        }

        return $cache;
    }

    /**
     * @param ServiceLocatorInterface $serviceLocator
     *
     * @return ArtaxService
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var array $config */
        $config = $serviceLocator->get('config');
        $config = isset($config['artax_composer']) ? $config['artax_composer'] : [];

        /** @var AbstractCacheAdapter|null $cache */
        $cache = $this->loadCache($config, $serviceLocator);

        return new ArtaxService($config, $cache);
    }
}