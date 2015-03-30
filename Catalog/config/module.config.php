<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Catalog\Controller\Catalog' => 'Catalog\Controller\CatalogController',
            'Catalog\Controller\Cron' => 'Catalog\Controller\CronController',
            'catalog' => 'Catalog\Controller\CatalogController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'catalog' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/catalog[/:action][/:id][/][:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[a-zA-Z0-9_-]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Catalog\Controller\Catalog',
                        'action'     => 'index',
                    ),
                ),
            ),
            'cron' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/cron[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Catalog\Controller\Cron',
                        'action' => 'parsexls',
                    ),
                ),
            ),

        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'catalog' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'catalog', 'roles' => array('guest', 'user')),
                array('route' => 'cron', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Catalog\Controller\Catalog', 'roles' => array('guest','user')),
                array('controller' => 'Catalog\Controller\Cron', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
