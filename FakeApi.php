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

    /** @var bool */
    protected $_defaultResponse200 = false;

    /**
     * FakeApi constructor.
     * @param bool $returnEmpty200whenRouteNotFound
     */
    public function __construct($returnEmpty200whenRouteNotFound = false)
    {
        $this->_defaultResponse200 = $returnEmpty200whenRouteNotFound;
        $this->_requestPath = $_SERVER['REQUEST_URI'];
        $this->_requestMethod = $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @param string $directory
     */
    public function loadRoutes($directory)
    {
        $files = scandir($directory);
        foreach ($files as $file) {
            if (!in_array($file, ['.', '..', '.gitignore'])) {
                if (is_dir($file)) {
                    $this->loadRoutes($directory . "/$file");
                } else {
                    $fileContent = file_get_contents($directory . "/$file");
                    $routes = json_decode($fileContent, true);
                    foreach ($routes as $path => $route) {
                        $this->_routes[$path] = $route;
                    }
                }
            }
        }
    }

    /**
     *
     */
    public function sendResponse()
    {
        list($body, $responseCode, $headers) = $this->_createNotFoundResponse();
        if (array_key_exists($this->_requestPath, $this->_routes)) {
            list($body, $responseCode, $headers) = $this->_getRouteResponse($this->_routes[$this->_requestPath]);
        } elseif (strpos($this->_requestPath, '*') !== false) {
            foreach ($this->_routes as $path => $route) {
                $pathRegex = str_replace('*', '[a-zA-Z0-9]+', str_replace('/', '\\/', $path));
                if (preg_match($pathRegex, $this->_requestPath)) {
                    list($body, $responseCode, $headers) = $this->_getRouteResponse($route);
                    break;
                }
            }
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
    protected function _getRouteResponse(array $routeConfig)
    {
        if (in_array($this->_requestMethod, $routeConfig['method'])) {
            switch ($routeConfig['response']['contentType']) {
                case 'application/json':
                    $body = json_encode($routeConfig['response']['body']);
                    $responseCode = $routeConfig['response']['statusCode'];
                    $headers = ['Content-Type' => 'application/json'];
                    break;
                default:
                    $body = $routeConfig['response']['body'];
                    $responseCode = $routeConfig['response']['statusCode'];
                    $headers = ['Content-Type' => 'text/plain'];
            }
        } else {
            if ($routeConfig['response']['contentType'] == 'application/json') {
                $body = json_encode(['message' => 'Method Not Allowed', 'code' => 405]);
                $responseCode = 405;
                $headers = ['Content-Type' => 'application/json'];
            } else {
                $body = 'METHOD NOT ALLOWED';
                $responseCode = 405;
                $headers = ['Content-Type' => 'text/plain'];
            }

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