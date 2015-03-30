<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Discount\Controller\Discount' => 'Discounts\Controller\DiscountController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'discount' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/discounts[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Discount\Controller\Discount',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'discount' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'discounts', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array(
                    'controller' => 'Discount\Controller\Discount',
                    'roles' => array('guest','user')
                ),
            ),
        ),
    ),
);
