<?php
namespace ArtaxComposer;

return [
    'service_manager' => [
        'factories'          => [
            __NAMESPACE__ . '\Service\ArtaxService' => __NAMESPACE__ . '\ServiceFactory\ArtaxServiceFactory',
        ],
        'shared'             => [
            __NAMESPACE__ . '\Service\ArtaxService' => false,
        ],
    ],
];