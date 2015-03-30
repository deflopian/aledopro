<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Projects\Controller\Projects' => 'Projects\Controller\ProjectsController',
            'projects' => 'Projects\Controller\ProjectsController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'projects' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/projects[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Projects\Controller\Projects',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'projects' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'projects', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array( 'controller' => 'Projects\Controller\Projects', 'roles' => array('guest','user') ),
            ),
        ),
    ),
);
