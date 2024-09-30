<?php

namespace Dolyame\Payment;

class Client
{
    protected $url = 'https://partner.dolyame.ru/v1/orders/';
    protected $login = '';
    protected $password = '';
    protected $certPath = '';
    protected $keyPath = '';
//    protected $useFileRequestHandler = false;

    public function __construct($login, $password) {
        $this->login = $login;
        $this->password = $password;
    }

    public function setCertPath($certPath)
    {
        $this->certPath = $certPath;
    }

    public function setKeyPath($keyPath)
    {
        $this->keyPath = $keyPath;
    }

//    public function useFileRequestHandler()
//    {
//        $this->useFileRequestHandler = true;
//    }

    public function generateCorrelationId()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function create($data, $correlationId = '')
    {
        return $this->execute('create', $data, 'POST', $correlationId);
    }

    public function cancel($orderId, $correlationId = '')
    {
        $orderId = self::prepareOrderId($orderId, true);
        return $this->execute($orderId.'/cancel', [], 'POST', $correlationId);
    }

    public function commit($orderId, $data, $correlationId = '')
    {
        $orderId = self::prepareOrderId($orderId, true);
        return $this->execute($orderId.'/commit', $data, 'POST', $correlationId);
    }

    public function info($orderId, $correlationId = '')
    {
        $orderId = self::prepareOrderId($orderId, true);
        return $this->execute($orderId.'/info', [], 'GET', $correlationId);
    }

    public function refund($orderId, $data, $correlationId = '')
    {
        $orderId = self::prepareOrderId($orderId, true);
        return $this->execute($orderId.'/refund', $data, 'POST', $correlationId);
    }

    protected function execute($action, $data, $method, $correlationId) {
        if($correlationId === '') {
            $correlationId = $this->generateCorrelationId();
        }
        if ($this->useFileRequestHandler) {
            return $this->fileRequestHandler($action, $data, $method, $correlationId);
        }
        return $this->curlRequestHandler($action, $data, $method, $correlationId);
    }

    protected function curlRequestHandler($action, $data, $method, $correlationId)
    {
        $headers = [
            "Content-Type: application/json",
            "X-Correlation-ID: $correlationId",
            "Authorization: Basic ".base64_encode("{$this->login}:{$this->password}")
        ];

        $responseHeaders = '';
        if (!function_exists("curl_init")) {
            throw new \PrestaShopException("Curl error");
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url.$action);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
            $responseHeaders .= $header;
            return strlen($header);
        });

        if ($this->certPath) {
            if (!file_exists($this->certPath)) {
                throw new \PrestaShopException('Cert path did\'t exist:'.$this->certPath);
            }
            if (!file_exists($this->keyPath)) {
                throw new \PrestaShopException('Key path did\'t exist:'.$this->keyPath);
            }
            if (!is_readable($this->certPath)) {
                throw new \PrestaShopException('Can\'t read cert file:'.$this->certPath);
            }

            if (!is_readable($this->keyPath)) {
                throw new \PrestaShopException('Can\'t read key file:'.$this->keyPath);
            }
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
            curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPath);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $encodedData = '';
        if (!empty($data) || $method == 'POST') {
            $encodedData = $this->encode($data);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        }

        $out = curl_exec($ch);

        $curlError = curl_error($ch);
        if ($curlError) {
            throw new \PrestaShopException($curlError);
        }

        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($out, true);
        if ($code == 200) {
            return $response;
        } elseif ($code == 429) {
            $headers = $this->parseHeadersToArray($responseHeaders);
            sleep($headers['X-Retry-After']);
            return $this->execute($action, $data, $method, $correlationId);
        }

        $error = 'Error: ' . $code;

        if (isset($response['type']) && $response['type'] == 'error') {
            $error .= ' ' . $response['description'];
        }
        if (isset($response['message'])) {
            $error .= ' '.$response['message'];
        }
        if (!empty($response['details'])) {
            $list = array_map(
                function($key, $value){return "$key - $value";},
                array_keys($response['details']),
                array_values($response['details'])
            );
            $error .= ': '.implode($list);
        }

        if (!$response) {
            $error .= $out;
        }
        throw new \PrestaShopException($error, $code);
    }

    protected function fileRequestHandler($action, $data, $method, $correlationId)
    {
        $headers = [
            "Content-Type: application/json",
            "X-Correlation-ID: $correlationId",
            "Authorization: Basic ".base64_encode("{$this->login}:{$this->password}")
        ];

        $streamOptions = [
            'http' => [
                'method'  => "GET",
                'header'  => implode("\r\n", $headers),
                'ignore_errors' => true
            ],
        ];

        if ($this->certPath) {
            if (!file_exists($this->certPath)) {
                throw new \PrestaShopException('Cert path did\'t exist:'.$this->certPath);
            }
            if (!file_exists($this->keyPath)) {
                throw new \PrestaShopException('Key path did\'t exist:'.$this->keyPath);
            }
            if (!is_readable($this->certPath)) {
                throw new \PrestaShopException('Can\'t read cert file:'.$this->certPath);
            }

            if (!is_readable($this->keyPath)) {
                throw new \PrestaShopException('Can\'t read key file:'.$this->keyPath);
            }
            $streamOptions['ssl'] = [
                'verify_peer' => true,
                'local_cert' => $this->certPath,
                'local_pk' => $this->keyPath
            ];
        }
        $encodedData = '';
        if (!empty($data) || $method == 'POST') {
            $encodedData = $this->encode($data);
            $streamOptions['http']['method'] = 'POST';
            $streamOptions['http']['content'] = $encodedData;
        }

        $context = stream_context_create($streamOptions);
        $url = $this->url.$action;
        $out = file_get_contents($url, false, $context);

        $statusLine = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
        $code = $match[1];

        $response = json_decode($out, true);
        if ($code == 200) {
            return $response;
        } elseif ($code == 429) {
            $headers = $this->parseHeadersToArray(implode("\r\n", $http_response_header));
            sleep($headers['X-Retry-After']);
            return $this->execute($action, $data, $method, $correlationId);
        }

        $error = 'Error: ' . $code;

        if (isset($response['type']) && $response['type'] == 'error') {
            $error .= ' ' . $response['description'];
        }
        if (isset($response['message'])) {
            $error .= ' '.$response['message'];
        }
        if (!empty($response['details'])) {
            $list = array_map(
                function($key, $value){return "$key - $value";},
                array_keys($response['details']),
                array_values($response['details'])
            );
            $error .= ': '.implode($list);
        }

        if (!$response) {
            $error .= $out;
        }
        throw new \PrestaShopException($error, $code);
    }

    protected function parseHeadersToArray($rawHeaders)
    {
        $lines = explode("\r\n", $rawHeaders);
        $headers = [];
        foreach($lines as $line) {
            if (strpos($line, ':') === false ){
                continue;
            }
            list($key, $value) = explode(': ', $line);
            $headers[$key] = $value;
        }
        return $headers;
    }

    protected function encode($data)
    {
        if (is_string($data)) {
            return $data;
        }
        $result = json_encode($data);
        $error = json_last_error();
        if ($error != JSON_ERROR_NONE) {
            throw new \PrestaShopException('JSON Error: ' . json_last_error_msg());
        }
        return $result;
    }

    public static function prepareOrderId($orderId, $forUrl = false)
    {
        $orderId = str_replace(['/','#','?','|',' '], ['-'], $orderId);
        if ($forUrl) {
            $orderId = urlencode($orderId);
        }
        return $orderId;
    }

}
