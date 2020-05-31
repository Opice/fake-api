<?php

define('__APPDIR__', __DIR__);

require_once __APPDIR__ . '/FakeApi.php';

$fakeApi = new FakeApi();
$fakeApi->loadRoutes(__APPDIR__ . '/endpoints');
$fakeApi->sendResponse();