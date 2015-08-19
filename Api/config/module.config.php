<?php
return array(
    'controllers' => array(
        'invokables' => array(
            'ApiController' => 'Api\Controller\ApiController',
            'FileApiController' => 'Api\Controller\FileController',
            'TreeApiController' => 'Api\Controller\TreeController',
            'EntityApiController' => 'Api\Controller\EntityController',
            'FieldApiController' => 'Api\Controller\FieldController',
            'CommercialApiController' => 'Api\Controller\CommercialController',
            'RoomApiController' => 'Api\Controller\RoomController',
            'CommercialProdApiController' => 'Api\Controller\CommercialProdController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'api' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/:type[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'ApiController',
                    ),
                ),
            ),
            'file' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/file[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'FileApiController',
                    ),
                ),
            ),
            'entity' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/entity[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'EntityApiController',
                    ),
                ),
            ),
            'tree' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/tree[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'TreeApiController',
                    ),
                ),
            ),
            'api/commercials' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/commercials[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'CommercialApiController',
                    ),
                ),
            ),
            'api/rooms' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/rooms[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'RoomApiController',
                    ),
                ),
            ),
            'api/comprod' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/comprod[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'CommercialProdApiController',
                    ),
                ),
            ),
            'field' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/field[/:id][/]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'FieldApiController',
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
                array('route' => 'api', 'roles' => array('guest', 'user')),
                array('route' => 'file', 'roles' => array('guest', 'user')),
                array('route' => 'tree', 'roles' => array('guest', 'user')),
                array('route' => 'entity', 'roles' => array('guest', 'user')),
                array('route' => 'field', 'roles' => array('guest', 'user')),
                array('route' => 'api/commercials', 'roles' => array('guest', 'user')),
                array('route' => 'api/rooms', 'roles' => array('guest', 'user')),
                array('route' => 'api/comprod', 'roles' => array('guest', 'user')),
            ),
            'BjyAuthorize\Guard\Controller' => array(
                array('controller' => 'ApiController', 'roles' => array('guest','user')),
                array('controller' => 'FileApiController', 'roles' => array('guest','user')),
                array('controller' => 'TreeApiController', 'roles' => array('guest','user')),
                array('controller' => 'EntityApiController', 'roles' => array('guest','user')),
                array('controller' => 'FieldApiController', 'roles' => array('guest','user')),
                array('controller' => 'CommercialApiController', 'roles' => array('guest','user')),
                array('controller' => 'RoomApiController', 'roles' => array('guest','user')),
                array('controller' => 'CommercialProdApiController', 'roles' => array('guest','user')),
                array(
                    'controller' => array(
                        'ApiController',
                        'FileApiController',
                        'TreeApiController',
                        'EntityApiController',
                        'FieldApiController',
                    ),
                    'action' => array(
                        'create',
                        'update',
                        'delete',
                    ),
                    'roles' => array('admin', 'manager')
                ),
            ),
        ),
    ),
);
