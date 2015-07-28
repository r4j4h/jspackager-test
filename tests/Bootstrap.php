<?php

namespace Webpt\JsPackagerTest;

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

$composer = require __DIR__ . '/../vendor/autoload.php';
if (!$composer) {
    throw new \Exception('Can\'t find autoload.php. Did you install dependencies via composer?');
}