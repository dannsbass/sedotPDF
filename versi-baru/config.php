<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/constants.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/dom/simple_html_dom.php';

Bot::setToken(TOKEN_BOT, NAMA_BOT);

Dannsheet::setCredentials(CREDENTIALS);
Dannsheet::setSpreasheetId(SPREADSHEET_ID);
