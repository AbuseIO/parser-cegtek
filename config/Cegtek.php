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
            'class'     => 'Copyright Infringement',
            'type'      => 'Abuse',
            'enabled'   => true,
            'fields'    => [
                //
            ],
        ],
    ],
];
