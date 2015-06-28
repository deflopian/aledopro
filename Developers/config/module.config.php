<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Developers\Controller\Developers' => 'Developers\Controller\DevelopersController',
            'developers' => 'Developers\Controller\DevelopersController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'developers' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/developers[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Developers\Controller\Developers',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'developers' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'developers', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'Developers\Controller\Developers', 'roles' => array('guest','user') ),
            ),
        ),
    ),
);
