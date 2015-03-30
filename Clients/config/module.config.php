<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Clients\Controller\Client' => 'Clients\Controller\ClientController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'clients' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/clients[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Clients\Controller\Client',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'clients' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'clients', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array(
                    'controller' => 'Clients\Controller\Client',
                    'roles' => array('guest','user')
                ),
            ),
        ),
    ),
);
