<?php

use Zend\Session\Storage\SessionArrayStorage;
use Zend\Session\Validator\RemoteAddr;
use Zend\Session\Validator\HttpUserAgent;
use Zend\Db\Adapter\AdapterAbstractServiceFactory;

return [
//    'db' => [
//        'driver' => 'Pdo',
//        'dsn' => 'mysql:dbname=eepdb;host=localhost;charset=utf8', //pruebas: 192.168.10.248
//    ],
    'db' => [
        'driver' => 'Pdo',
        'dsn' => 'mysql:dbname=db_postgrados;host=localhost;charset=utf8',
        //'dsn' => 'mysql:dbname=eepdb;host=192.168.10.248;charset=utf8', //pruebas: 192.168.10.248
        'driver_options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
        ],
        'adapters' => [
            'satu' => [
                'driver' => 'Pdo',
                'dsn' => 'mysql:dbname=satu;host=localhost;charset=utf8',
                //'dsn' => 'mysql:dbname=satu;host=192.168.10.248;charset=utf8',
                'driver_options' => [
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
                ],
            ],
        ],
    ],
    'session_config' => [
        'cookie_lifetime' => 60 * 60 * 1, // Session cookie will expire in 1 hour.
        'gc_maxlifetime' => 60 * 60 * 24 * 30, // How long to store session data on server (for 1 month).
    //SPECIFY THE SAVE_PATH LOCATION IN LOCAL
    ],
    // Session manager configuration.
    'session_manager' => [
        'abstract_factories' => [
            AdapterAbstractServiceFactory::class,
        ],
        // Session validators (used for security).
        'validators' => [
            RemoteAddr::class,
            //HttpUserAgent::class,
        ]
    ],
    // Session storage configuration.
    'session_storage' => [
        'type' => SessionArrayStorage::class
    ],
];
