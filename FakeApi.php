<?php

/**
 * Class FakeApi
 */
class FakeApi
{
    /** @var array */
    protected $_routes = [];

    /** @var string */
    protected $_requestUri;

    /** @var string */
    protected $_rawRequestUri;

    /** @var int */
    protected $_requestMethod;

    /** @var array */
    protected $_requestQueryParameters = [];

    /** @var bool */
    protected $_defaultResponse200 = false;

    /** @var mixed */
    protected $_requestBody;

    /** @var string */
    protected $_logsDirectorySlashSymbol = '⁄';

    /** @var string */
    protected $_logsDirectoryStarSymbol = '🞯';

    /**
     * FakeApi constructor.
     *
     * @param array $options
     * [
     *     'DEFAULT_200_RESPONSE' => true|false,
     *     'LOGS_SLASH' => '⁄',
     *     'LOGS_STAR' => '🞯',
     * ]
     */
    public function __construct(array $options = array())
    {
        $this->_loadOptions($options);
        $this->_requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->_rawRequestUri = $_SERVER['REQUEST_URI'];
        @list($uri, $queryString) = explode('?', $this->_rawRequestUri);
        $this->_requestUri = $uri;
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
        $this->_requestBody = file_get_contents('php://input');
    }

    /**
     * @param string $directory
     * @param bool $firstCall
     */
    public function loadRoutes($directory = null, $firstCall = true)
    {
        if ($directory === null) {
            $directory = __APPDIR__ . '/endpoints';
        }
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
        if (array_key_exists($this->_requestUri, $this->_routes)) {
            list($body, $responseCode, $headers) = $this->_getRouteResponse($this->_requestUri, $this->_routes[$this->_requestUri], []);
        } else {
            foreach ($this->_routes as $path => $route) {
                if (strpos($path, '*') !== false) {
                    $pathRegex = str_replace('*', '[a-zA-Z0-9]+', str_replace('/', '\\/', $path));
                    if (preg_match("/$pathRegex/", $this->_requestUri)) {
                        $arguments = [0];
                        $requestPathParts = explode('/', $this->_requestUri);
                        foreach (explode('/', $path) as $index => $pathPart) {
                            if ($pathPart == '*') {
                                $arguments[] = $requestPathParts[$index];
                            }
                        }
                        unset($arguments[0]);
                        list($body, $responseCode, $headers) = $this->_getRouteResponse($path, $route, $arguments);
                        break;
                    }
                }
            }
        }
        if (!isset($responseCode)) {
            list($body, $responseCode, $headers) = $this->_createNotFoundResponse();
            $this->_logRequest('404');
        }
        http_response_code($responseCode);
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        echo $body;
    }

    /**
     * @param array $options
     */
    protected function _loadOptions(array $options)
    {
        $definedOptions = [
            'DEFAULT_200_RESPONSE' => '_defaultResponse200',
            'LOGS_SLASH' => '_logsDirectorySlashSymbol',
            'LOGS_STAR' => '_logsDirectoryStarSymbol'
        ];
        foreach ($definedOptions as $optionName => $property) {
            if (array_key_exists($optionName, $options)) {
                $this->$property = $options[$optionName];
            } elseif ($envValue = getenv($optionName)) {
                $this->$property = $envValue;
            }
        }
    }

    /**
     * @param string $routePath
     * @param array $routeConfig
     * @param array $arguments
     * @return array
     */
    protected function _getRouteResponse(string $routePath, array $routeConfig, array $arguments)
    {
        if (!isset($routeConfig['request']['methods'])) {
            $routeConfig['request']['methods'] = [$this->_requestMethod];
        }
        if (in_array($this->_requestMethod, $routeConfig['request']['methods'])) {
            $responseCode = $routeConfig['response']['statusCode'];
            $headers = $routeConfig['response']['headers'];
            switch ($routeConfig['response']['Content-Type']) {
                case 'application/json':
                    $body = json_encode($routeConfig['response']['body']);
                    foreach ($arguments as $index => $argument) {
                        $body = str_replace('[[[$' . $index .  ']]]', $argument, $body);
                    }
                    $headers['Content-Type'] = 'application/json';
                    break;
                default:
                    $body = $routeConfig['response']['body'];
                    $headers['Content-Type'] = 'text/plain';
            }
        } else {
            $body = json_encode(['message' => 'Method Not Allowed', 'code' => 405]);
            $responseCode = 405;
            $headers = ['Content-Type' => 'application/json'];
        }
        $responseData = [$body, $responseCode, $headers];
        if (array_key_exists('log', $routeConfig) && $routeConfig['log'] == true) {
            $this->_logRequest($routePath, $responseData);
        }
        return $responseData;
    }

    /**
     * @param string $routePath
     * @param array $responseData
     */
    protected function _logRequest(string $routePath, array $responseData = array())
    {
        $logContent = '';
        $rawRequest = [$this->_requestMethod . ' ' . $_SERVER['REQUEST_URI'] . ' HTTP/1.1'];
        foreach (getallheaders() as $name => $value) {
            $rawRequest[] = "$name: $value";
        }
        if ($this->_requestBody) {
            $rawRequest[] = '';
            $rawRequest[] = $this->_requestBody;
        }
        $logContent = implode("\r\n", $rawRequest);
        if (!empty($responseData)) {
            $separator = '================================================================================================================================';
            $rawResponse = ['', $separator, $separator, '', 'HTTP/1.1 ' . $responseData[1]];
            foreach ($responseData[2] as $headerName => $headerValue) {
                $rawResponse[] = "$headerName: $headerValue";
            }
            $rawResponse[] = '';
            $rawResponse[] = $responseData[0];
            $logContent .= "\r\n" . implode("\r\n", $rawResponse);
        }

        $directoryName = str_replace('*', $this->_logsDirectoryStarSymbol, str_replace('/', $this->_logsDirectorySlashSymbol, $routePath));
        $directory = __APPDIR__ . '/logs/' . $directoryName;
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        $fileName = date('Y-m-d_H-i-s');
        file_put_contents($directory . '/' . $fileName, $logContent);
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