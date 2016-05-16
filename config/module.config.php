<?php
namespace ArtaxComposer;

return [
    'service_manager' => [
        'factories' => [
            __NAMESPACE__ . '\Service\ArtaxService' => __NAMESPACE__ . '\ServiceFactory\ArtaxServiceFactory',
        ],
        'shared'    => [
            __NAMESPACE__ . '\Service\ArtaxService' => false,
        ],
    ],
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
        'newrelic' => true,
    ],
];