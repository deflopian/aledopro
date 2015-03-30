<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Blog\Controller\Blog' => 'Blog\Controller\BlogController',
            'blog' => 'Blog\Controller\BlogController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'blog' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/blog[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Blog\Controller\Blog',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'blog' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'blog', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Blog\Controller\Blog', 'roles' => array('guest', 'user', 'admin')),
            ),
        ),
    ),
);
