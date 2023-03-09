<?php

namespace ivuorinen\Curly;

use CurlHandle;
use League\Uri\Uri;

/**
 * Class Curly
 */
class Curly
{
    /** @var string|null Remote host address (complete url) */
    private ?string $address = null;

    /** @var integer Remote host port */
    private int $port = 80;

    /** @var string Conversation method (GET or POST) */
    private string $method = 'GET';

    /** @var integer Timeout for request, in seconds. */
    private int $timeout = 30;

    /** @var string HTTP Version (1.0/1.1) */
    private string $httpVersion = '1.1';

    /**
     * Auth method to use. It currently supports only:
     * - BASIC
     * - NTLM
     *
     * @var string|null
     */
    private ?string $authenticationMethod = null;

    /** @var string|null Remote host auth username */
    private ?string $user = null;

    /** @var string|null Remote host auth password */
    private ?string $pass = null;

    /** @var string Request user agent */
    private string $userAgent = 'ivuorinen-curly';

    /** @var string Content type */
    private string $contentType = 'application/x-www-form-urlencoded';

    /** @var array|string[] Array of headers to send */
    private array $headers = [
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-us,en;q=0.5',
        'Accept-Encoding' => 'gzip,deflate',
        'Accept-Charset'  => 'UTF-8;q=0.7,*;q=0.7',
    ];

    /** @var string|null Should we use a proxy? */
    private ?string $proxy = null;

    private ?string $proxyAuth = null;

    /** @var array|string[] Allowed HTTP methods */
    private array $supportedAuthMethods = ["BASIC", "DIGEST", "SPNEGO", "NTLM"];

    /** @var array|string[] Allowed HTTP authentication */
    private array $supportedHttpMethods = ["GET", "POST", "PUT", "DELETE"];

    /** @var boolean Are we using curl? */
    private bool $curl = true;

    /** @var array|string[] Received headers */
    private array $receivedHeaders = [];

    /** @var int|null Received http status code */
    private ?int $receivedHttpStatus = null;

    private CurlHandle|false $ch = false;

    private ?string $streamGetData = null;

    /** @var boolean Output debug messages? */
    private bool $verbose = false;

    /** @var boolean Should we blindly trust host? */
    private bool $skipSSLVerify = false;

    /**
     * Class constructor
     *
     * @param string $address Remote host address
     * @param bool   $curl    Use curl (true) or stream (false)
     *
     * @throws Exceptions\HTTPException
     */
    public function __construct(string $address = '', bool $curl = true)
    {
        $this->reset();

        if (!empty($address)) {
            $this->setHost($address);
        }

        $this->setCurl($curl);
    }

    /**
     * Reset the data channel for new request
     */
    public function reset(): void
    {
        $this->address              = null;
        $this->port                 = 80;
        $this->method               = "GET";
        $this->timeout              = 30;
        $this->httpVersion          = "1.0";
        $this->authenticationMethod = null;
        $this->user                 = null;
        $this->pass                 = null;
        $this->userAgent            = "ivuorinen-curly";
        $this->contentType          = "application/x-www-form-urlencoded";
        $this->headers              = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language" => "en-us,en;q=0.5",
            "Accept-Encoding" => "deflate",
            "Accept-Charset"  => "UTF-8;q=0.7,*;q=0.7",
        ];
        $this->proxy                = null;
        $this->proxyAuth            = null;
        $this->receivedHeaders      = [];
        $this->receivedHttpStatus   = null;
        $this->streamGetData        = null;
        $this->verbose              = false;
        $this->skipSSLVerify        = false;
        if ($this->ch) {
            $this->closeTransport();
        }
    }

    /**
     * Close transport layer
     */
    private function closeTransport(): void
    {
        if ($this->curl && $this->ch !== false) {
            curl_close($this->ch);
            $this->ch = false;
        }
    }

    /**
     * Set remote host address
     *
     * @param string $address Remote host address
     *
     * @return self
     * @throws Exceptions\HTTPException
     */
    public function setHost(string $address = ''): self
    {
        $url = filter_var($address, FILTER_VALIDATE_URL);

        if ($url === false) {
            throw new Exceptions\HTTPException("Invalid remote address");
        }

        $this->address = $address;

        return $this;
    }

    public function getHost(): ?string
    {
        return $this->address;
    }

    /**
     * Force lib to use curl (default if available) or stream
     *
     * @param bool $mode Use curl (true) or stream (false)
     *
     * @return self
     */
    public function setCurl(bool $mode = true): self
    {
        $curl = filter_var($mode, FILTER_VALIDATE_BOOLEAN);

        $this->curl = !(!function_exists("curl_init") || !$curl);

        return $this;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if ($this->ch !== false) {
            $this->closeTransport();
        }
    }

    public function getVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * Set Curl verbosity on, or off
     *
     * @param bool $value true = verbose
     *
     * @return self
     */
    public function setVerbose(bool $value = false): self
    {
        $this->verbose = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Are we skipping SSL Verify?
     *
     * @return bool
     */
    public function getSkipSSLVerify(): bool
    {
        return $this->skipSSLVerify;
    }

    /**
     * Should we blindly trust host validity?
     * This helps cases when we have self-signed certificates
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setSkipSSLVerify(bool $value = false): self
    {
        $this->skipSSLVerify = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * Set http authentication
     *
     * @param string      $method Auth method (BASIC or NTLM)
     * @param string      $user   Username to use
     * @param string|null $pass   User password
     *
     * @return self
     * @throws Exceptions\HTTPException
     */
    public function setAuth(string $method, string $user, string $pass = null): self
    {
        $method = strtoupper($method);

        if (!in_array($method, $this->supportedAuthMethods, true)) {
            throw new Exceptions\HTTPException("Unsupported authentication method");
        }

        $this->authenticationMethod = $method;

        if (empty($user)) {
            throw new Exceptions\HTTPException("User name cannot be empty");
        }

        $this->user = $user;
        $this->pass = $pass;

        return $this;
    }

    /**
     * Set http version (1.0/1.1)
     *
     * @param string $ver 1.0 or 1.1
     *
     * @return self
     */
    public function setHttpVersion(string $ver = "1.1"): self
    {
        $this->httpVersion = !in_array($ver, ["1.0", "1.1"])
            ? "NONE"
            : $ver;

        return $this;
    }

    /**
     * Unset header component
     *
     * @param string $header Header name to remove
     *
     * @return self
     */
    public function unsetHeader(string $header): self
    {
        if (array_key_exists($header, $this->headers)) {
            unset($this->headers[$header]);
        }

        return $this;
    }

    /**
     * @return array|string[]
     */
    public function getReceivedHeaders(): array
    {
        return $this->receivedHeaders;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->receivedHttpStatus;
    }

    public function getChannel(): CurlHandle|false
    {
        return $this->ch;
    }

    /**
     * Init transport and send data to the remote host.
     *
     * @param object|array|string[]|string|null $data
     *
     * @return string
     * @throws Exceptions\HTTPException
     */
    public function send(object|array|string $data = null): string
    {
        if ($this->curl) {
            $this->initCurl($data);

            return $this->sendUsingCurl();
        }

        $this->initStream($data);

        return $this->sendUsingStream();
    }

    /**
     * Init the CURL channel
     *
     * @param object|array|string[]|string|null $data
     *
     * @throws Exceptions\HTTPException
     */
    private function initCurl(object|array|string|null $data): void
    {
        $this->ch = curl_init();

        if ($this->ch === false) {
            throw new Exceptions\HTTPException("Could not init data channel");
        }

        $this->initCurlHTTPVersion();

        $this->initCurlAuthenticationMethod();

        $this->initCurlProxy();

        $this->initCurlMethod($data);

        $headers = [];
        if (count($this->headers) !== 0) {
            foreach ($this->getHeaders() as $header => $value) {
                if (is_null($value)) {
                    $headers[] = $header;
                } else {
                    $headers[] = $header . ': ' . $value;
                }
            }
        }

        if (!$this->ch) {
            return;
        }

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_PORT, $this->port);
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_ENCODING, "");

        if ($this->verbose) {
            curl_setopt($this->ch, CURLOPT_VERBOSE, true);
        }

        if ($this->skipSSLVerify) {
            /** @noinspection CurlSslServerSpoofingInspection */
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
            /** @noinspection CurlSslServerSpoofingInspection */
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
    }

    private function initCurlHTTPVersion(): bool
    {
        if (!$this->ch) {
            return false;
        }

        match ($this->httpVersion) {
            '1.0' => curl_setopt(
                $this->ch,
                CURLOPT_HTTP_VERSION,
                CURL_HTTP_VERSION_1_0
            ),
            '1.1' => curl_setopt(
                $this->ch,
                CURLOPT_HTTP_VERSION,
                CURL_HTTP_VERSION_1_1
            ),
            default => curl_setopt(
                $this->ch,
                CURLOPT_HTTP_VERSION,
                CURL_HTTP_VERSION_NONE
            ),
        };

        return true;
    }

    private function initCurlAuthenticationMethod(): void
    {
        if (!$this->ch) {
            return;
        }

        switch ($this->authenticationMethod) {
            case 'BASIC':
                curl_setopt(
                    $this->ch,
                    CURLOPT_HTTPAUTH,
                    CURLAUTH_BASIC
                );
                curl_setopt(
                    $this->ch,
                    CURLOPT_USERPWD,
                    $this->user . ":" . $this->pass
                );
                break;

            case 'DIGEST':
                curl_setopt(
                    $this->ch,
                    CURLOPT_HTTPAUTH,
                    CURLAUTH_DIGEST
                );
                curl_setopt(
                    $this->ch,
                    CURLOPT_USERPWD,
                    $this->user . ":" . $this->pass
                );
                break;

            case 'SPNEGO':
                curl_setopt(
                    $this->ch,
                    CURLOPT_HTTPAUTH,
                    CURLAUTH_GSSNEGOTIATE
                );
                curl_setopt(
                    $this->ch,
                    CURLOPT_USERPWD,
                    $this->user . ":" . $this->pass
                );
                break;

            case 'NTLM':
                curl_setopt(
                    $this->ch,
                    CURLOPT_HTTPAUTH,
                    CURLAUTH_NTLM
                );
                curl_setopt(
                    $this->ch,
                    CURLOPT_USERPWD,
                    $this->user . ":" . $this->pass
                );
                break;
        }
    }

    private function initCurlProxy(): void
    {
        if (!is_null($this->proxy) && $this->ch) {
            curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);

            if (!is_null($this->proxyAuth)) {
                curl_setopt(
                    $this->ch,
                    CURLOPT_PROXYUSERPWD,
                    $this->proxyAuth
                );
            }
        }
    }

    private function initCurlMethod(object|array|string|null $data): void
    {
        if (!$this->ch) {
            return;
        }

        switch ($this->method) {
            default:
            case 'GET':
                $payload = empty($data)
                    ? $this->address
                    : $this->address . "?" . $this->parseData($data);

                curl_setopt(
                    $this->ch,
                    CURLOPT_URL,
                    $payload
                );
                break;

            case 'PUT':
                curl_setopt(
                    $this->ch,
                    CURLOPT_CUSTOMREQUEST,
                    "PUT"
                );

                if (!empty($data)) {
                    curl_setopt(
                        $this->ch,
                        CURLOPT_POSTFIELDS,
                        $this->parseData($data)
                    );

                    $this->setHeader(
                        "Content-Type",
                        $this->contentType
                    );
                }

                curl_setopt(
                    $this->ch,
                    CURLOPT_URL,
                    $this->address
                );

                break;

            case 'POST':
                curl_setopt(
                    $this->ch,
                    CURLOPT_POST,
                    true
                );

                if (!empty($data)) {
                    curl_setopt(
                        $this->ch,
                        CURLOPT_POSTFIELDS,
                        $this->parseData($data)
                    );
                    $this->setHeader(
                        "Content-Type",
                        $this->contentType
                    );
                }

                curl_setopt(
                    $this->ch,
                    CURLOPT_URL,
                    $this->address
                );

                break;

            case 'DELETE':
                curl_setopt(
                    $this->ch,
                    CURLOPT_CUSTOMREQUEST,
                    "DELETE"
                );

                if (!empty($data)) {
                    curl_setopt(
                        $this->ch,
                        CURLOPT_POSTFIELDS,
                        $this->parseData($data)
                    );

                    $this->setHeader(
                        "Content-Type",
                        $this->contentType
                    );
                }

                curl_setopt(
                    $this->ch,
                    CURLOPT_URL,
                    $this->address
                );

                break;
        }
    }

    /**
     * Convert string, array or object to http_build_query string
     *
     * @param object|array|string $data
     *
     * @return string
     */
    public function parseData(object|array|string $data = ''): string
    {
        return (is_array($data) || is_object($data))
            ? http_build_query($data)
            : $data;
    }

    /**
     * Set header component
     *
     * @param string      $header Header name
     * @param string|null $value  Header content (optional)
     *
     * @return self
     */
    public function setHeader(string $header, string $value = null): self
    {
        $this->headers[$header] = $value;

        return $this;
    }

    /**
     * Get the whole headers array
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Send data via CURL
     *
     * @return string
     * @throws Exceptions\HTTPException
     */
    private function sendUsingCurl(): string
    {
        if (!$this->ch) {
            throw new Exceptions\HTTPException(
                "No CURL available"
            );
        }

        $request = curl_exec($this->ch);

        if (is_bool($request)) {
            throw new Exceptions\HTTPException(
                curl_error($this->ch),
                curl_errno($this->ch)
            );
        }

        $this->receivedHttpStatus = curl_getinfo(
            $this->ch,
            CURLINFO_HTTP_CODE
        );

        $headerSize = curl_getinfo(
            $this->ch,
            CURLINFO_HEADER_SIZE
        );

        $headers = substr(
            $request,
            0,
            $headerSize
        );

        $body = substr(
            $request,
            $headerSize
        );

        $this->receivedHeaders = self::tokenizeHeaders($headers);

        return $body;
    }

    /**
     * Tokenize received headers
     *
     * @param string $headers
     *
     * @return array
     */
    public static function tokenizeHeaders(string $headers): array
    {
        $return = [];

        $headersArray = explode("\r\n", $headers);

        foreach ($headersArray as $header) {
            if (empty($header)) {
                continue;
            }

            $headerComponents = explode(":", $header);

            if (!isset($headerComponents[1]) || @empty($headerComponents[1])) {
                $return[] = $headerComponents[0];
            } else {
                $return[$headerComponents[0]] = $headerComponents[1];
            }
        }

        return $return;
    }

    /**
     * Init the STREAM channel
     *
     * @param object|array|string|null $data
     *
     * @throws Exceptions\HTTPException
     */
    private function initStream(object|array|string|null $data): void
    {
        if (in_array($this->authenticationMethod, ["DIGEST", "SPNEGO", "NTLM"])) {
            throw new Exceptions\HTTPException(
                sprintf(
                    "Selected auth method %s not available in stream mode",
                    $this->authenticationMethod
                )
            );
        }

        $streamOptions = [
            "http" => [
                "method"           => $this->method,
                "protocol_version" => $this->httpVersion === "NONE"
                    ? "1.0"
                    : $this->httpVersion,
                "user_agent"       => $this->userAgent,
                "timeout"          => $this->timeout,
                "header"           => [
                    "Connection: close",
                ],
            ],
        ];

        if (!is_null($this->proxy)) {
            $streamOptions['http']['proxy'] = $this->proxy;

            if (!is_null($this->proxyAuth)) {
                $streamOptions['http']['header'][] = 'Proxy-Authorization: Basic '
                                                     . base64_encode($this->proxyAuth);
            }
        }

        if ($this->authenticationMethod === "BASIC") {
            $streamOptions["http"]["header"][] = "Authorization: Basic "
                                                 . base64_encode($this->user . ":" . $this->pass);
        }

        foreach ($this->getHeaders() as $header => $value) {
            if (is_null($value)) {
                $streamOptions["http"]["header"][] = $header;
            } else {
                $streamOptions["http"]["header"][] = $header . ': ' . $value;
            }
        }

        if (!empty($data)) {
            $dataQuery = $this->parseData($data);

            if ($this->method === "GET") {
                $this->streamGetData = $dataQuery;
            } else {
                $streamOptions['http']['header'][] = "Content-Type: " . $this->contentType;
                $streamOptions['http']['header'][] = "Content-Length: " . strlen($dataQuery);
                $streamOptions['http']['content']  = $dataQuery;
            }
        }

        $this->ch = stream_context_create($streamOptions);

        if (!$this->ch) {
            throw new Exceptions\HTTPException("Cannot init data channel");
        }
    }

    /**
     * Send data via STREAM
     *
     * @uses \League\Uri\Uri
     * @uses \League\Uri\Uri
     * @return string
     *
     * @throws Exceptions\HTTPException
     */
    private function sendUsingStream(): string
    {
        if (empty($this->address)) {
            throw new Exceptions\HTTPException(
                "No address to use with stream"
            );
        }

        $parts = parse_url($this->address);

        $url = Uri::createFromComponents([
            'scheme'   => $parts['scheme'] ?? null,
            'user'     => $parts['user'] ?? $this->user,
            'pass'     => $parts['pass'] ?? $this->pass,
            'host'     => $parts['host'] ?? null,
            'port'     => $parts['port'] ?? $this->port,
            'path'     => $parts['path'] ?? null,
            'query'    => !is_null($this->streamGetData)
                ? $this->streamGetData
                : null,
            'fragment' => $parts['fragment'] ?? null,
        ]);

        set_error_handler(
            static function ($severity, $message, $file, $line) {
                unset($severity, $file, $line);
                throw new Exceptions\HTTPException($message);
            }
        );

        $received = file_get_contents(
            $url,
            false,
            $this->ch
        );

        if ($received === false) {
            throw new Exceptions\HTTPException(
                sprintf(
                    "No response from %s",
                    $url
                )
            );
        }

        restore_error_handler();

        if (!is_string($received)) {
            throw new Exceptions\HTTPException(
                "Could not read stream socket"
            );
        }

        $this->receivedHeaders = self::tokenizeHeaders(
            implode("\r\n", $http_response_header)
        );

        $contentEncoding = array_key_exists(
            "Content-Encoding",
            $this->receivedHeaders
        );

        [$version, $receivedHttpStatus, $msg] = explode(
            ' ',
            $this->receivedHeaders[0],
            3
        );

        $this->receivedHttpStatus = (int)$receivedHttpStatus;

        unset($version, $msg);

        $gzipped = strpos(
            $this->receivedHeaders["Content-Encoding"],
            "gzip"
        );

        $response = ($contentEncoding === true && $gzipped !== false
            ? gzinflate(substr($received, 10, -8))
            : $received
        );

        return !is_string($response)
            ? ''
            : $response;
    }

    public function getHeader(string $header): string|false
    {
        return $this->headers[$header] ?? false;
    }

    /**
     * Init transport and get remote content
     *
     * @return string
     *
     * @throws Exceptions\HTTPException
     */
    public function get(): string
    {
        if ($this->curl) {
            $this->initCurl(null);

            $received = $this->sendUsingCurl();
        } else {
            $this->initStream(null);

            $received = $this->sendUsingStream();
        }

        return $received;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Set TCP port to connect to
     *
     * @param integer $port TCP port (default 80)
     *
     * @return self
     */
    public function setPort(int $port = 80): self
    {
        $this->port = filter_var(
            $port,
            FILTER_VALIDATE_INT,
            [
                "options" => [
                    "min_range" => 1,
                    "max_range" => 65535,
                    "default"   => 80,
                ],
            ]
        );

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Set HTTP method to use
     *
     * @param string $method HTTP Method (GET, POST, ...)
     *
     * @return self
     * @throws Exceptions\HTTPException
     */
    public function setMethod(string $method = 'GET'): self
    {
        $method = strtoupper($method);

        if (!in_array($method, $this->supportedHttpMethods, true)) {
            throw new Exceptions\HTTPException(
                sprintf(
                    "Unsupported HTTP method: %s. Should be one of: %s",
                    $method,
                    implode(', ', $this->supportedHttpMethods)
                )
            );
        }

        $this->method = $method;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set connection timeout
     *
     * @param int $sec Timeout to wait for (in second)
     *
     * @return self
     */
    public function setTimeout(int $sec): self
    {
        $time = filter_var($sec, FILTER_VALIDATE_INT);

        $this->timeout = $time;

        return $this;
    }

    public function getAuthenticationMethod(): ?string
    {
        return $this->authenticationMethod;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    /**
     * Set HTTP method to use
     *
     * @param string      $address Proxy URL or IP address
     * @param string|null $user    (optional) Username for proxy auth
     * @param string|null $pass    (optional) User password for proxy auth
     *
     * @return self
     * @throws Exceptions\HTTPException
     */
    public function setProxy(string $address, string $user = null, string $pass = null): self
    {
        $proxy = filter_var($address, FILTER_VALIDATE_URL);

        if ($proxy === false) {
            throw new Exceptions\HTTPException(
                "Invalid proxy address or URL"
            );
        }

        $this->proxy = $proxy;

        $this->proxyAuth = !is_null($user) && !is_null($pass)
            ? $user . ':' . $pass
            : $user;

        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Set user agent for request
     *
     * @param string $userAgent User Agent
     *
     * @return self
     * @throws Exceptions\HTTPException
     */
    public function setUserAgent(string $userAgent = ''): self
    {
        if (empty($userAgent)) {
            throw new Exceptions\HTTPException("User Agent cannot be empty");
        }

        $this->userAgent = $userAgent;

        return $this;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Set http content type
     *
     * @param string $type Content type, application/json, etc.
     *
     * @return self
     * @throws Exceptions\HTTPException
     */
    public function setContentType(string $type): self
    {
        if (empty($type)) {
            throw new Exceptions\HTTPException("Content Type cannot be empty");
        }

        $this->contentType = $type;

        return $this;
    }

    public function getHttpVersion(): string
    {
        return $this->httpVersion;
    }

    public function getPass(): ?string
    {
        return $this->pass;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }
}
