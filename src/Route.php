<?php

/**
 * Class Route
 */
class Route
{
    /** @var string */
    protected string $path;

    /** @var null|string[] */
    protected ?array $methods;

    /** @var bool */
    protected bool $loggable;

    /** @var array */
    protected array $responses;

    /**
     * Route constructor.
     *
     * @param string $path
     * @param array $config
     */
    public function __construct(string $path, array $config)
    {
        $this->path = $path;
        foreach ($config['responses'] as $code => $data) {
            $this->responses[$code] = $data;
        }
        $this->methods = $config['request']['methods'] ?? null;
        $this->loggable = (bool)($config['log'] ?? false);
    }

    /**
     * @param string|int $responseCode
     * @param array $wildcardValues
     */
    public function dispatchResponse($responseCode, array $wildcardValues = [])
    {
        $responseData = $this->responses[$responseCode] ?? reset($this->responses);
        http_response_code($responseData['statusCode']);
        $body = $responseData['body'] ?? '';
        $headers = $responseData['headers'];
        $htc = [
            'ct' => ['Content-Type', 'Content-type', 'content-Type', 'content-type', 'CONTENT-TYPE'],
            'cl' => ['Content-Length', 'Content-length', 'content-Length', 'content-length', 'CONTENT-LENGTH']
        ];
        if (($responseData['json'] ?? true) == true) {
            $body = json_encode($body, \JSON_UNESCAPED_UNICODE);
            foreach ($htc['ct'] as $headerToCheck) {
                if (isset($headers[$headerToCheck])) {
                    $doNotSetContentType = true;
                }
            }
            if (!isset($doNotSetContentType)) {
                $headers['Content-Type'] = 'application/json';
            }
        }
        foreach ($htc['cl'] as $headerToCheck) {
            if (isset($headers[$headerToCheck])) {
                $doNotSetContentLength = true;
            }
        }
        foreach ($wildcardValues as $index => $wildcardValue) {
            $body = str_replace("[[[$$index]]]", $wildcardValue, $body);
        }
        if (!isset($doNotSetContentLength)) {
            $headers['Content-Length'] = strlen($body);
        };
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        if (!empty($body)) {
            echo $body;
        }
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $method
     * @return bool
     */
    public function supportsMethod(string $method): bool
    {
        return $this->methods === null
            ? false
            : in_array($method, $this->methods);
    }

    /**
     * @return bool
     */
    public function isLoggable(): bool
    {
        return $this->loggable;
    }

    /**
     * @param mixed $responseCode
     * @return array
     */
    public function getResponseData($responseCode): array
    {
        $response = $this->responses[$responseCode] ?? reset($this->responses);
        $headers = $response['headers'];
        $status = $response['statusCode'];
        $body = $response['body'];
        if (($response['json'] ?? true) == true) {
            $body = json_encode($body, \JSON_UNESCAPED_UNICODE);
        }

        return [$status, $headers, $body];
    }
}
