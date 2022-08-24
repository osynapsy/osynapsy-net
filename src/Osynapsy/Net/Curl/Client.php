<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Osynapsy\Net\Curl;

/**
 * Description of CurlClient
 *
 * @author Pietro
 */
class Client
{
    const REQUEST_GET = 1;
    const REQUEST_POST = 2;
    const REQUEST_USERAGENT = '';

    private $optionDecoder = [
        'Proxy' => \CURLOPT_PROXY,
        'ProxyPort' => \CURLOPT_PROXYPORT,
        'CookieFile' => \CURLOPT_COOKIEFILE,
        'SslVerifyHost' => \CURLOPT_SSL_VERIFYHOST,
        'SslVerifyPeer' => \CURLOPT_SSL_VERIFYPEER,
        'UserAgent' => \CURLOPT_USERAGENT
    ];

    private $parameters = [
        \CURLOPT_PROXY => null,
        \CURLOPT_PROXYPORT => null,
        \CURLOPT_COOKIEFILE => true,
        \CURLOPT_COOKIEJAR => true,
        \CURLOPT_SSL_VERIFYHOST => false,
        \CURLOPT_SSL_VERIFYPEER => false,
        \CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36',
        \CURLOPT_RETURNTRANSFER => true
    ];
    private $handle;

    public function execute()
    {
        foreach ($this->parameters as $option => $value) {
            curl_setopt($this->handle, $option, $value);
        }
        $response = curl_exec($this->handle);
        return $response;
    }

    public function prepare($url, array $data = [], $type = self::REQUEST_GET)
    {
        $this->handle = curl_init($url);
        if ($type === self::REQUEST_GET) {
            curl_setopt($this->handle, \CURLOPT_CUSTOMREQUEST, "GET");
        }
    }

    public function __call($name, $args)
    {
        if (substr($name,0,3) != 'set' || empty($args)) {
            return false;
        }
        $parameter = substr($name, 3);
        if (!array_key_exists($parameter, $this->optionDecoder)) {
            return false;
        }
        $this->parameters[$this->optionDecoder[$parameter]] = $args[0];
    }

    public function setProxy($proxy, $port)
    {
        $this->parameters[\CURLOPT_PROXY] = $proxy;
        $this->parameters[\CURLOPT_PROXYPORT] = $port;
    }
}
