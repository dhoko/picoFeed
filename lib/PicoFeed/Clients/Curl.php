<?php

namespace PicoFeed\Clients;

use \PicoFeed\Logging;

class Curl extends \PicoFeed\Client
{
    public function execute()
    {
        if ($this->url === '') {
            throw new \LogicException('The URL is missing');
        }

        $response = $this->doRequest();

        if ($response['status'] == 304) {

            $this->is_modified = false;
        }
        else {
            $this->etag = isset($response['headers']['ETag']) ? $response['headers']['ETag'] : '';
            $this->last_modified = isset($response['headers']['Last-Modified']) ? $response['headers']['Last-Modified'] : '';
            $this->content = $response['body'];
        }
    }


    public function doRequest()
    {
        $http_code = 200;
        $http_body = '';
        $http_headers = array();

        Logging::log('Fetch URL: '.$this->url);
        Logging::log('Etag: '.$this->etag);
        Logging::log('Last-Modified: '.$this->last_modified);

        $headers = array('Connection: close');

        if ($this->etag) $headers[] = 'If-None-Match: '.$this->etag;
        if ($this->last_modified) $headers[] = 'If-Modified-Since: '.$this->last_modified;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->max_redirects);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        // Don't check SSL certificates (for auto-signed certificates...)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $http_response = curl_exec($ch);

        $curl_info = curl_getinfo($ch);
        $http_code = $curl_info['http_code'];

        Logging::log('cURL total time: '.curl_getinfo($ch, CURLINFO_TOTAL_TIME));
        Logging::log('cURL dns lookup time: '.curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME));
        Logging::log('cURL connect time: '.curl_getinfo($ch, CURLINFO_CONNECT_TIME));
        Logging::log('cURL speed download: '.curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD));

        if (curl_errno($ch)) {

            Logging::log('cURL error: '.curl_error($ch));

            curl_close($ch);

            return array(
                'status' => $http_code,
                'body' => $http_body,
                'headers' => $http_headers
            );
        }

        curl_close($ch);

        // @todo replace this with list($headers, $body) = explode("\r\n\r\n", $http_response, 2);
        $lines = explode("\r\n", $http_response);
        $body_start = 0;
        $i = 0;

        foreach ($lines as $line) {

            if ($line === '') {

                $body_start = $i;
                break;
            }
            else if (($p = strpos($line, ':')) !== false) {

                $key = substr($line, 0, $p);
                $value = substr($line, $p + 1);

                $http_headers[trim($key)] = trim($value);
            }

            $i++;
        }

        $http_body = implode("\r\n", array_splice($lines, $i + 1));

        Logging::log('HTTP status code: '.$http_code);

        foreach ($http_headers as $header_name => $header_value) {

            Logging::log('HTTP headers: '.$header_name.' => '.$header_value);
        }

        return array(
            'status' => $http_code,
            'body' => $http_body,
            'headers' => $http_headers
        );
    }
}