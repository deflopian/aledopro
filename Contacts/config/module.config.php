<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Contacts\Controller\Contacts' => 'Contacts\Controller\ContactsController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'contacts' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/contacts[/]',
                    'defaults' => array(
                        'controller' => 'Contacts\Controller\Contacts',
                        'action'     => 'index',
                    ),
                ),
            ),
            'sitemap' => array(
                'type'    => 'literal',
                'options' => array(
                    'route'    => '/sitemap/',
                    'defaults' => array(
                        'controller' => 'Contacts\Controller\Contacts',
                        'action'     => 'sitemap',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'sitemap' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'sitemap', 'roles' => array('guest', 'user')),
                array('route' => 'contacts', 'roles' => array('guest', 'user')),

            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Contacts\Controller\Contacts', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
