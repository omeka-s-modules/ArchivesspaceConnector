<?php
return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => OMEKA_PATH . '/modules/ArchivesspaceConnector/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'archivesspace_items' => 'ArchivesspaceConnector\Api\Adapter\ArchivesspaceItemAdapter',
            'archivesspace_item_sets' => 'ArchivesspaceConnector\Api\Adapter\ArchivesspaceItemSetAdapter',
            'archivesspace_imports' => 'ArchivesspaceConnector\Api\Adapter\ArchivesspaceImportAdapter',
        ],
    ],
    'controllers' => [
        'factories' => [
            'ArchivesspaceConnector\Controller\Index' => 'ArchivesspaceConnector\Service\Controller\IndexControllerFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/ArchivesspaceConnector/view',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/ArchivesspaceConnector/src/Entity',
        ],
    ],
    'form_elements' => [
        'factories' => [
            'ArchivesspaceConnector\Form\ImportForm' => 'ArchivesspaceConnector\Service\Form\ImportFormFactory',
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'ArchivesSpace Connector', // @translate
                'route' => 'admin/archivesspace-connector',
                'resource' => 'ArchivesspaceConnector\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Import', // @translate
                        'route' => 'admin/archivesspace-connector',
                        'resource' => 'ArchivesspaceConnector\Controller\Index',
                    ],
                    [
                        'label' => 'Past Imports', // @translate
                        'route' => 'admin/archivesspace-connector/past-imports',
                        'controller' => 'Index',
                        'action' => 'past-imports',
                        'resource' => 'ArchivesspaceConnector\Controller\Index',
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'archivesspace-connector' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/archivesspace-connector',
                            'defaults' => [
                                '__NAMESPACE__' => 'ArchivesspaceConnector\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'past-imports' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/past-imports',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'ArchivesspaceConnector\Controller',
                                        'controller' => 'Index',
                                        'action' => 'past-imports',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'ac_past_imports' => [
                'job_id' => 'Job ID', // @translate
                'date' => 'Date', // @translate
            ],
        ],
    ],
];
