<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Solutions\Controller\Solutions' => 'Solutions\Controller\SolutionsController',
            'solutions' => 'Solutions\Controller\SolutionsController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'solutions' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/solutions[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Solutions\Controller\Solutions',
                        'robot' => false,
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'solutions' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'solutions', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'Solutions\Controller\Solutions', 'roles' => array('guest','user') ),
            ),
        ),
    ),
);
