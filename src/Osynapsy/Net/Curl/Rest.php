<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Osynapsy\Net\Curl;

class Rest
{
    private static $baseurl = '';
    private static $channel;
    private static $response;

    private static function init($url, array $rawheaders = [], $proxy = null)
    {
        self::$channel = curl_init(self::$baseurl.$url);
        self::setProxy($proxy);
        self::appendHeaders($rawheaders);
        self::appendOptions([
            \CURLOPT_COOKIEFILE => true,
            \CURLOPT_COOKIEJAR => true,
            \CURLOPT_SSL_VERIFYHOST => false,
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36',
            \CURLOPT_RETURNTRANSFER => true
        ]);
    }

    private static function appendHeaders(array $rawheaders = [])
    {
        if (empty($rawheaders)) {
            return;
        }
        $headers = [];
        foreach($rawheaders as $key => $value) {
            $headers[] = strtolower($key).': '.$value;
        }
        curl_setopt(self::$channel, \CURLOPT_HTTPHEADER, $headers);
    }

    private static function appendOptions(array $options)
    {
        if (empty($options)) {
            return;
        }
        foreach ($options as $option => $value) {
            curl_setopt(self::$channel, $option, $value);
        }
    }

    public static function get($url, $data = [], $headers = [], $proxy = null)
    {
        self::init($url . (empty($data) ? '' : '?'.http_build_query($data)), $headers);
        self::setProxy($proxy);
        self::appendOptions([\CURLOPT_CUSTOMREQUEST => "GET"]);
        return self::getResponse();
    }

    private static function getResponse()
    {
        self::$response  = new \stdClass();
        self::$response->content = curl_exec(self::$channel);
        self::$response->code = curl_getinfo(self::$channel, CURLINFO_HTTP_CODE);
        self::$response->type = curl_getinfo(self::$channel, CURLINFO_CONTENT_TYPE);
        self::validateResponse();
        curl_close(self::$channel);
        return self::$response;
    }

    public static function post($url, $data, array $headers = [], array $options = [])
    {
        self::init($url, $headers);
        $options[\CURLOPT_POST] = true;
        $options[\CURLOPT_POSTFIELDS] = $data;
        self::appendOptions($options);
        return self::getResponse(self::$channel);
    }

    public static function postJson($url, $data)
    {
        $json = json_encode($data, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        return self::post($url, $json, [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($json)
        ]);
    }

    public static function setBaseUrl($url)
    {
        self::$baseurl = $url;
    }

    private static function setProxy($proxy)
    {
        if (empty($proxy)) {
            return;
        }
        $proxyPart = explode(':', $proxy);
        self::appendOptions([
            \CURLOPT_PROXY => $proxyPart[0],
            \CURLOPT_PROXYPORT => $proxyPart[1]
        ]);
    }

    private static function validateResponse()
    {
        if (self::$response->content === false) {
            throw new \Exception(curl_errno(self::$channel), self::$response->code);
        }
        if (self::$response->code !== 200) {
            throw new \Exception(curl_errno(self::$channel), self::$response->code);
        }
    }
}
