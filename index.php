<?php

define('__APPDIR__', __DIR__);

require_once __APPDIR__ . '/src/Route.php';
require_once __APPDIR__ . '/src/Router.php';
require_once __APPDIR__ . '/src/FakeApi.php';

$fakeApi = new FakeApi();
$fakeApi->run();
