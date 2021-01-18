<?php

/**
 * Class Router
 */
class Router
{
    /** @var Route[] */
    protected array $_routes = [];

    /**
     * Router constructor.
     */
    public function __construct()
    {
        $this->_loadRoutes();
        $this->_loadNotFoundRoute();
    }

    /**
     * @param string $directory
     * @param bool $firstCall
     */
    protected function _loadRoutes($directory = null, $firstCall = true): void
    {
        if ($directory === null) {
            $directory = __APPDIR__ . '/endpoints';
        }
        $files = scandir($directory);
        $routes = [];
        foreach ($files as $file) {
            if (!in_array($file, ['.', '..', '.gitignore'])) {
                if (is_dir($file)) {
                    $this->_loadRoutes($directory . "/$file", false);
                } else {
                    // removes comments /* comment */ from json
                    $fileContent = preg_replace('/\/\*.+\*\/\s*\R/', \PHP_EOL, file_get_contents($directory . "/$file")); 
                    $decoded = json_decode($fileContent, true, 512, \JSON_THROW_ON_ERROR);
                    foreach ($decoded as $path => $route) {
                        $routes[$path] = $route;
                    }
                }
            }
        }
        if ($firstCall) {
            $rts = $sortedRoutes = [];
            foreach ($routes as $path => $routeData) {
                if (strpos($path, '*') !== false) {
                    $firstOccurrence = null;
                    $routeSortingData = ['path' => $path, 'routeData' => $routeData, 'wildcardOccurrences' => []];
                    $pathParts = explode('/', $path);
                    foreach ($pathParts as $index => $pathSegment) {
                        if ($pathSegment == '*') {
                            $routeSortingData['wildcardOccurrences'][] = $index;
                            if ($firstOccurrence === null) {
                                $firstOccurrence = $index;
                            }
                        }
                    }
                    $rts[$firstOccurrence][] = $routeSortingData;
                }
            }
            // it's not perfectly sorted but hopefully it's good enough
            ksort($rts);
            foreach ($rts as $firstOccurrence => $sortingDataSets) {
                usort($sortingDataSets, function (array $a, array $b) {
                    if (count($a['wildcardOccurrences']) == count($b['wildcardOccurrences'])) {
                        return array_sum($a['wildcardOccurrences']) < array_sum($b['wildcardOccurrences']) ? -1 : 1;
                    } else {
                        return count($a['wildcardOccurrences']) < count($b['wildcardOccurrences']) ? -1 : 1;
                    }
                });
                foreach ($sortingDataSets as $index => $sortingData) {
                    $sortedRoutes[$sortingData['path']] = $sortingData['routeData'];
                }
            }
            foreach (array_reverse($sortedRoutes) as $path => $routeConfig) {
                unset($routes[$path]);
                $this->_routes[$path] = new Route($path, $routeConfig);
            }
        }
    }

    /**
     * @return void
     */
    protected function _loadNotFoundRoute(): void
    {
        if (!array_key_exists('not_found', $this->_routes)) {
            $this->_routes['not_found'] = new Route('not_found', [
                'responses' => [
                    'first' => [
                        'statusCode' => 404,
                        'body' => [
                            'message' => "Route '[[[$1]]]' not found",
                            'code' => 'not_found',
                            'status' => 404,
                        ]
                    ]
                ]
            ]);
        }
    }

    /**
     * @param string $requestUri
     * @param string $requestMethod
     * @return Route
     */
    public function matchRoute(string $requestUri, string $requestMethod): Route
    {
        $route = null;
        if (array_key_exists($requestUri, $this->_routes)) {
            $route = $this->_routes[$requestUri];
        } else {
            foreach ($this->_routes as $path => $definedRoute) {
                if (strpos($path, '*') !== false) { // route contains wildcard
                    $pathRegex = str_replace('*', '[a-zA-Z0-9\_\-]+', str_replace('/', '\\/', $path));
                    if (preg_match("/$pathRegex/", $requestUri)) {
                        $route = $definedRoute;
                        break;
                    }
                }
            }
        }
        if (null === $route || !$route->supportsMethod($requestMethod)) {
            $route = $this->_routes['not_found'];
        }

        return $route;
    }
}
