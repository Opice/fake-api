<?php

define('__APPDIR__', __DIR__);

require_once __APPDIR__ . '/FakeApi.php';

$fakeApi = new FakeApi();
$fakeApi->loadRoutes();
$fakeApi->sendResponse();