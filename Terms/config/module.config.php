<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'Terms\Controller\Terms' => 'Terms\Controller\TermsController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'terms' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/terms[/:action][/:id][/]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Terms\Controller\Terms',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            'terms' => __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),

    'bjyauthorize' => array(
        'guards'                => array(
            'BjyAuthorize\Guard\Route' => array(
                array('route' => 'terms', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'Terms\Controller\Terms', 'roles' => array('guest','user')),
            ),
        ),
    ),
);
