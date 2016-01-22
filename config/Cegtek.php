<?php

return [
    'parser' => [
        'name'          => 'Cegtek',
        'enabled'       => true,
        'sender_map'    => [
            '/dmca@cegtek.com/',
        ],
        'body_map'      => [
            //
        ],
    ],

    'feeds' => [
        'default' => [
            'class'     => 'COPYRIGHT_INFRINGEMENT',
            'type'      => 'Abuse',
            'enabled'   => true,
            'fields'    => [
                //
            ],
        ],
    ],
];
