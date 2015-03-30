<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Team\Controller\Team' => 'Team\Controller\TeamController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'team' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/team[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Team\Controller\Team',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'team' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'team', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array(
                    'controller' => 'Team\Controller\Team',
                    'roles' => array('guest','user')
                ),
            ),
        ),
    ),
);
