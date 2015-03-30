<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Info\Controller\Info' => 'Info\Controller\InfoController',
            'Info\Controller\Request' => 'Info\Controller\RequestController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'info' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/info[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'index',
                    ),
                ),
            ),
            'job' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/job[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'job',
                    ),
                ),
            ),
            'service' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/service[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'service',
                    ),
                ),
            ),
            'pluses' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/pluses[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'pluses',
                    ),
                ),
            ),
            'guarantee' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/guarantee[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'guarantee',
                    ),
                ),
            ),
            'partners' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/partners[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'partners',
                    ),
                ),
            ),
            'files' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/files[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Info',
                        'action'     => 'files',
                    ),
                ),
            ),
            'request' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/request[/:action][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Info\Controller\Request',
                        'action'     => 'newpassword',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'info' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'info', 'roles' => array('guest', 'user')),
                array('route' => 'request', 'roles' => array('guest', 'user')),
                array('route' => 'job', 'roles' => array('guest', 'user')),
                array('route' => 'guarantee', 'roles' => array('guest', 'user')),
                array('route' => 'files', 'roles' => array('guest', 'user')),
                array('route' => 'service', 'roles' => array('guest', 'user')),
                array('route' => 'pluses', 'roles' => array('guest', 'user')),
                array('route' => 'partners', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'Info\Controller\Info',  'roles' => array('guest','user')),
                array( 'controller' => 'Info\Controller\Request', 'action'=>'newpassword', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
