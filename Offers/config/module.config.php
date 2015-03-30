<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Offers\Controller\Offers' => 'Offers\Controller\OffersController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'offers' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/offers[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Offers\Controller\Offers',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'offers' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'offers', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Offers\Controller\Offers', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
