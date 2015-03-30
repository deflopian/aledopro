<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Services\Controller\Services' => 'Services\Controller\ServicesController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'services' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/services[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Services\Controller\Services',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'services' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'services', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'Services\Controller\Services', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
