<?php

namespace Eep;

//ZEND COMPONENTS
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterServiceFactory;
use Zend\Router\Http\Segment;
use Zend\Router\Http\Literal;
use Zend\Authentication\AuthenticationService;
//SERVICES
use Eep\Service\Factory\AuthenticationServiceFactory;
use Eep\Service\AcademyManager;
use Eep\Service\Factory\AcademyManagerFactory;
use Eep\Service\AuthAdapter;
use Eep\Service\Factory\AuthAdapterFactory;
use Eep\Service\AuthManager;
use Eep\Service\Factory\AuthManagerFactory;
use Eep\Service\CohortManager;
use Eep\Service\Factory\CohortManagerFactory;
use Eep\Service\UserManager;
use Eep\Service\Factory\UserManagerFactory;
use Eep\Service\OrderManager;
use Eep\Service\Factory\OrderManagerFactory;
use Eep\Service\InscriptionManager;
use Eep\Service\Factory\InscriptionManagerFactory;
use Eep\Service\TimetableManager;
use Eep\Service\Factory\TimetableManagerFactory;
use Eep\Service\MassiveLoadManager;
use Eep\Service\Factory\MassiveLoaderManagerFactory;
use Eep\Service\AssignmentManager;
use Eep\Service\Factory\AssignmentManagerFactory;
use Eep\Service\LogManager;
use Eep\Service\Factory\LogManagerFactory;
use Eep\Service\ReportManager;
use Eep\Service\Factory\ReportManagerFactory;
use Eep\Service\SatuManager;
use Eep\Service\Factory\SatuManagerFactory;
use Eep\Service\GradesManager;
use Eep\Service\Factory\GradesManagerFactory;
use Eep\Service\GeneralManager;
use Eep\Service\Factory\GeneralManagerFactory;
use Eep\Service\FormularioAdmisionManager;
use Eep\Service\Factory\FormularioAdmisionManagerFactory;
//PLUGIN
use Eep\Controller\Plugin\PluginHandler;
use Eep\Controller\Plugin\Factory\PluginHandlerFactory;
//CONTROLLERS
use Eep\Controller\AuthController;
use Eep\Controller\UpgCourseController;
use Eep\Controller\AssignmentController;
use Eep\Controller\InscriptionController;
use Eep\Controller\TimetableController;
use Eep\Controller\Factory\TimetableControllerFactory;
use Eep\Controller\TreasuryController;
use Eep\Controller\UserController;
use Eep\Controller\CohortController;
use Eep\Controller\MassiveLoadController;
use Zend\Mvc\Controller\LazyControllerAbstractFactory;
use Eep\Controller\GradesController;
use Eep\Controller\OfficialController;
use Eep\Controller\FormularioAdmisionController;
use Eep\Controller\Factory\FormularioAdmisionControllerFactory;
//OTHERS
use Eep\Form\CategorizeTimetableForm as CTF;

return [
    'controllers' => [
        'factories' => [
            UpgCourseController::class => LazyControllerAbstractFactory::class,
            AssignmentController::class => LazyControllerAbstractFactory::class,
            AuthController::class => LazyControllerAbstractFactory::class,
            CohortController::class => LazyControllerAbstractFactory::class,
            TimetableController::class => TimetableControllerFactory::class,
            TreasuryController::class => LazyControllerAbstractFactory::class,
            InscriptionController::class => LazyControllerAbstractFactory::class,
            UserController::class => LazyControllerAbstractFactory::class,
            MassiveLoadController::class => LazyControllerAbstractFactory::class,
            GradesController::class => LazyControllerAbstractFactory::class,
            OfficialController::class => LazyControllerAbstractFactory::class,
            FormularioAdmisionController::class => FormularioAdmisionControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            PluginHandler::class => PluginHandlerFactory::class,
        ],
        'aliases' => [
            'pg' => PluginHandler::class,
        ]
    ],
    'router' => [
        'routes' => [
            'home' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'controller' => UserController::class,
                        'action' => 'profile',
                    ],
                ],
            ],
            'upgCourse' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/upgCourse[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => UpgCourseController::class,
                        'action' => 'view',
                    ],
                ],
            ],
            'assignment' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/assignment[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => AssignmentController::class,
                        'action' => 'assignedCourses',
                    ],
                ],
            ],
            'auth' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/auth[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => AuthController::class,
                        'action' => 'login',
                    ],
                ],
            ],
            'cohort' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/cohort[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => CohortController::class,
                        'action' => 'cohorts',
                    ],
                ],
            ],
            'etl' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/etl[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => MassiveLoadController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'grades' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/grades/:action/[:timetableCode]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'timetableCode' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => GradesController::class,
                    ],
                ],
            ],
            'inscription' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/inscription[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => InscriptionController::class,
                        'action' => 'view',
                    ],
                ],
            ],
            'admisiones' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/admisiones',
                    'defaults' => [
                        'controller' => FormularioAdmisionController::class,
                        'action'     => 'public',
                    ],
                ],
            ],
            'verificar-cui' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/admisiones/verificar-cui',
                    'defaults' => [
                        'controller' => FormularioAdmisionController::class,
                        'action'     => 'verificarCui',
                    ],
                ],
            ],
            'timetable' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/timetable[/:action][/:year]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'year' => '[0-9]+'
                    ],
                    'defaults' => [
                        'controller' => TimetableController::class,
                        'action' => 'categorize',
                    ],
                ],
            ],
            'treasury' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/treasury[/:action]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => TreasuryController::class,
                        'action' => 'orderListing',
                    ],
                ],
            ],
            'official' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/official[/:action][/:timetableCode]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'timetableCode' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => OfficialController::class,
                        'action' => 'timetables',
                    ],
                ],
            ],
            'order' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/order[/:id]',
                    'constraints' => [
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => TreasuryController::class,
                        'action' => 'downloadOrder',
                    ],
                ],
            ],
            'recover-password' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/recover-password',
                    'defaults' => [
                        'controller' => UserController::class,
                        'action' => 'recoverPassword',
                    ],
                ],
            ],
            'user' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/user[/:action][/:userCode]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'userCode' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => UserController::class,
                        'action' => 'profile',
                    ],
                ],
            ],
            'formulario-admision' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/formulario-admision[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => FormularioAdmisionController::class,
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            AcademyManager::class => AcademyManagerFactory::class,
            Adapter::class => AdapterServiceFactory::class,
            AssignmentManager::class => AssignmentManagerFactory::class,
            AuthenticationService::class => AuthenticationServiceFactory::class,
            AuthAdapter::class => AuthAdapterFactory::class,
            AuthManager::class => AuthManagerFactory::class,
            CohortManager::class => CohortManagerFactory::class,
            MassiveLoadManager::class => MassiveLoaderManagerFactory::class,
            GradesManager::class => GradesManagerFactory::class,
            GeneralManager::class => GeneralManagerFactory::class,
            InscriptionManager::class => InscriptionManagerFactory::class,
            LogManager::class => LogManagerFactory::class,
            OrderManager::class => OrderManagerFactory::class,
            ReportManager::class => ReportManagerFactory::class,
            SatuManager::class => SatuManagerFactory::class,
            TimetableManager::class => TimetableManagerFactory::class,
            UserManager::class => UserManagerFactory::class,
            FormularioAdmisionManager::class => FormularioAdmisionManagerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_map' => [
            // Layout minimal disponible (sin sidebar)
            'layout/empty' => __DIR__ . '/../view/layout/empty.phtml',
            // Alias para layout minimal con prefijo de módulo
            'eep/empty-layout' => __DIR__ . '/../view/layout/empty.phtml',
            // Layout público para admisiones (alias de empty)
            'layout/login' => __DIR__ . '/../view/layout/empty.phtml',
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'layout/flash'            => __DIR__ . '/../view/layout/flash.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
            // Alias para el partial de mensajes usado en muchas vistas
            'eep/msg'                 => __DIR__ . '/../view/partial/msg.phtml',
            // Alias para layout por defecto (fallback)
            'eep/layout'              => __DIR__ . '/../view/layout/layout.phtml',
        ],
        'template_path_stack' => [
            'Eep' => __DIR__ . '/../view',
        ],
        'strategies' => ['ViewJsonStrategy'],
    ],
    'access_filter' => require __DIR__ . '/access_filter.php',
    'menus' => require __DIR__ . '/menus.php',
    'session_containers' => [
        CTF::SESSION_CONTAINER
    ],
];

/*
 * LITERAL ROUTE
//            'eep' => [
//                'type' => 'Literal',
//                'options' => [
//                    // Change this to something specific to your module
//                    'route' => '/login',
//                    'defaults' => [
//                        'controller' => Controller\AuthController::class,
//                        'action' => 'login',
//                    ],
//                ],
//                'may_terminate' => true,
//                'child_routes' => [
//                // You can place additional routes that match under the
//                // route defined above here.
//                ],
//            ],
 */
