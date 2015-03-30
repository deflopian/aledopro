<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Cart\Controller\Cart' => 'Cart\Controller\CartController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'cart' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/cart[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Cart\Controller\Cart',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'cart' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'cart', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Cart\Controller\Cart', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
