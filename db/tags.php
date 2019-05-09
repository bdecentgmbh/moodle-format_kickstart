<?php

$tagareas = [
    [
        'itemtype' => 'kickstart_template',  // This must be a name of the database table (without prefix).
        'component' => 'format_kickstart', // This can be omitted for plugins since it can only be full frankenstyle name of the plugin.
        'callback' => 'format_kickstart_get_tagged_templates',
        'callbackfile' => '/course/format/loclstart/lib.php',
    ],
];
