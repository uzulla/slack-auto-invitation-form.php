<?php
// Settings will read from ENV (SLACK_AUTO_INVITAION_SETTINGS_JSON).
// or, you can direct settings. see config.sample.php

$json = json_encode([
    'your-sub-domain' => //Team's Slack sub domain. ex: hachiojipm.slack.com => hachiojipm
    'xoxp-xxxxxx....FILL_ME', // API TOKEN. ref: https://api.slack.com/web

    'another-sub-domain' => 'xoxp-xxxxxx....FILL_ME',
]);

define('CONFIG_JSON', $json);
