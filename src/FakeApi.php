<?php

/**
 * Class FakeApi
 */
class FakeApi
{
    /** @var Router */
    protected Router $router;

    /** @var string */
    protected $_logsDirectorySlashSymbol = '⁄';

    /** @var string */
    protected $_logsDirectoryStarSymbol = '🞯';

    /**
     * FakeApi constructor.
     *
     * @param array $options
     * [
     *     'LOGS_SLASH' => '⁄',
     *     'LOGS_STAR' => '🞯',
     * ]
     */
    public function __construct(array $options = array())
    {
        $this->_loadOptions($options);
        $this->router = new Router();
    }

    /**
     * @param array $options
     */
    protected function _loadOptions(array $options)
    {
        $definedOptions = [
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

    public function run(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        @list($requestUri, $queryString) = explode('?', $_SERVER['REQUEST_URI']);
        if (null === $queryString) {
            $queryParams = [];
        } else {
            parse_str($queryString, $queryParams);
        }
        if (array_key_exists('responsecode', $queryParams ?? [])) {
            $responseCode = $queryParams['responsecode'];
            unset($queryParams['responsecode']);
        } elseif (array_key_exists('responsecode', $headers = getallheaders())) {
            $responseCode = $headers['responsecode'];
        } else {
            $responseCode = null;
        }
        $route = $this->router->matchRoute($requestUri, $requestMethod);

        // extract wildcard arguments from request uri
        $wildcardArguments = [];
        if (strpos($route->getPath(), '*') !== false) {
            $arguments = [];
            $requestPathParts = explode('/', $requestUri);
            foreach (explode('/', $route->getPath()) as $index => $pathPart) {
                $arguments = [];
                if ($pathPart == '*') {
                    $arguments[] = $requestPathParts[$index];
                }
            }
            $wildcardArguments = array_combine(range(1, count($arguments)), $arguments);
        }
        foreach ($queryParams as $name => $value) {
            if (preg_match('/w\d+/', $name, $matches)) {

            }
        }
        if ($route->getPath() === 'not_found') {
            $wildcardArguments= [1 => $requestUri];
        }
        if ($route->isLoggable()) {
            $requestBody = file_get_contents('php://input') ?? '';
            $this->_logRequest($requestMethod, $requestUri, $requestBody, $route->getPath(), $route->getResponseData($responseCode));
        }

        $route->dispatchResponse($responseCode, $wildcardArguments);
    }

    /**
     * @param string $requestMethod
     * @param string $requestUri
     * @param string $requestBody
     * @param string $routePath
     * @param array $responseData
     */
    protected function _logRequest(string $requestMethod, string $requestUri, string $requestBody, string $routePath, array $responseData)
    {
        $separator = '=============';
        $rawRequest = [$separator, '== REQUEST ==', $separator, $requestMethod . ' ' . $requestUri . ' HTTP/1.1'];
        foreach (getallheaders() as $name => $value) {
            $rawRequest[] = "$name: $value";
        }
        if ($requestBody) {
            $rawRequest[] = '';
            $rawRequest[] = $requestBody;
        }
        $separator .= '=';
        $logContent = implode("\r\n", $rawRequest);
        if (!empty($responseData)) {
            $rawResponse = ['', $separator, '== RESPONSE ==', $separator, 'HTTP/1.1 ' . $responseData[0]];
            foreach ($responseData[1] as $headerName => $headerValue) {
                $rawResponse[] = "$headerName: $headerValue";
            }
            $rawResponse[] = '';
            $rawResponse[] = $responseData[2];
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
}
