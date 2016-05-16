<?php
namespace ArtaxComposer\ServiceFactory;

use Zend\Cache\Storage\Adapter\Redis;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ArtaxComposer\Service\ArtaxService;

class ArtaxServiceFactory implements FactoryInterface {

    /**
     * @param ServiceLocatorInterface $serviceLocator
     *
     * @return ArtaxService
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new ArtaxService();
    }

}