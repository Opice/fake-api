<?php

/**
 * Class FakeApi
 */
class FakeApi
{
    /** @var array */
    protected $_routes = [];

    /** @var string */
    protected $_requestPath;

    /** @var int */
    protected $_requestMethod;

    /** @var array */
    protected $_requestQueryParameters = [];

    /** @var bool */
    protected $_defaultResponse200 = false;

    /**
     * FakeApi constructor.
     * @param bool $returnEmpty200whenRouteNotFound
     */
    public function __construct($returnEmpty200whenRouteNotFound = false)
    {
        $this->_defaultResponse200 = $returnEmpty200whenRouteNotFound;
        $this->_requestMethod = $_SERVER['REQUEST_METHOD'];
        @list($uri, $queryString) = explode('?', $_SERVER['REQUEST_URI']);
        $this->_requestPath = $uri;
        if ($queryString) {
            foreach (explode('&', $queryString) as $paramPair) {
                $parts = explode('=', $paramPair);
                $parameterName = urldecode(array_shift($parts));
                if (count($parts) > 1) {
                    $parts = [implode('=', $parts)];
                }
                $parameterValue = urldecode(reset($parts));
                $this->_requestQueryParameters[$parameterName] = $parameterValue;
            }
        }
    }

    /**
     * @param string $directory
     * @param bool $firstCall
     */
    public function loadRoutes($directory, $firstCall = true)
    {
        $files = scandir($directory);
        foreach ($files as $file) {
            if (!in_array($file, ['.', '..', '.gitignore'])) {
                if (is_dir($file)) {
                    $this->loadRoutes($directory . "/$file", false);
                } else {
                    $fileContent = file_get_contents($directory . "/$file");
                    $routes = json_decode($fileContent, true);
                    foreach ($routes as $path => $route) {
                        $this->_routes[$path] = $route;
                    }
                }
            }
        }
        if ($firstCall) {
            $rts = $sortedRoutes = [];
            foreach ($this->_routes as $path => $routeData) {
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
            // it's not perfectly sorted but it's good enough
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
                unset($this->_routes[$path]);
                $this->_routes[$path] = $routeConfig;
            }
        }
    }

    /**
     *
     */
    public function sendResponse()
    {
        if (array_key_exists($this->_requestPath, $this->_routes)) {
            list($body, $responseCode, $headers) = $this->_getRouteResponse($this->_routes[$this->_requestPath]);
        } else {
            foreach ($this->_routes as $path => $route) {
                if (strpos($path, '*') !== false) {
                    $pathRegex = str_replace('*', '[a-zA-Z0-9]+', str_replace('/', '\\/', $path));
                    if (preg_match("/$pathRegex/", $this->_requestPath)) {
                        $arguments = [0];
                        $requestPathParts = explode('/', $this->_requestPath);
                        foreach (explode('/', $path) as $index => $pathPart) {
                            if ($pathPart == '*') {
                                $arguments[] = $requestPathParts[$index];
                            }
                        }
                        unset($arguments[0]);
                        list($body, $responseCode, $headers) = $this->_getRouteResponse($route, $arguments);
                        break;
                    }
                }
            }
        }
        if (!isset($responseCode)) {
            list($body, $responseCode, $headers) = $this->_createNotFoundResponse();
        }
        http_response_code($responseCode);
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        echo $body;
    }

    /**
     * @param array $routeConfig
     * @return array
     */
    protected function _getRouteResponse(array $routeConfig, array $arguments)
    {
        if (in_array($this->_requestMethod, $routeConfig['method'])) {
            switch ($routeConfig['response']['contentType']) {
                case 'application/json':
                    $body = json_encode($routeConfig['response']['body']);
                    foreach ($arguments as $index => $argument) {
                        $body = str_replace('[[[$' . $index .  ']]]', $argument, $body);
                    }
                    $responseCode = $routeConfig['response']['statusCode'];
                    $headers = ['Content-Type' => 'application/json'];
                    break;
                default:
                    $body = $routeConfig['response']['body'];
                    $responseCode = $routeConfig['response']['statusCode'];
                    $headers = ['Content-Type' => 'text/plain'];
            }
        } else {
            $body = json_encode(['message' => 'Method Not Allowed', 'code' => 405]);
            $responseCode = 405;
            $headers = ['Content-Type' => 'application/json'];
        }
        return [$body, $responseCode, $headers];
    }

    /**
     * @return array
     */
    protected function _createNotFoundResponse()
    {
        return $this->_defaultResponse200
            ? ['', 200, ['Content-Type' => 'text/plain']]
            : ['NOT FOUND', 404, ['Content-Type' => 'text/plain']];
    }
}