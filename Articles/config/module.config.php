<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Articles\Controller\Articles' => 'Articles\Controller\ArticlesController',
            'articles' => 'Articles\Controller\ArticlesController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'articles' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/articles[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Articles\Controller\Articles',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'articles' =>  __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'articles', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Articles\Controller\Articles', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
