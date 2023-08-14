<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Osynapsy\Net\Curl\Rest;

final class CurlTest extends TestCase
{
    public function testGetCookie(): void
    {
        $cookie = Rest::getCookie('https://fc.yahoo.com/');        
        $this->assertIsArray($cookie);
    }
    
    public function testGetQuote(): void
    {
        $cookie = Rest::getCookie('https://fc.yahoo.com/');
        //Get crumb value (like a token)
        $crumb = Rest::get('https://query1.finance.yahoo.com/v1/test/getcrumb', [], ['Cookie' => implode(';', $cookie)])->content;
        $response = Rest::get(sprintf('https://query1.finance.yahoo.com/v7/finance/quote?lang=it_IT&region=EU&corsDomain=finance.yahoo.com&symbols=%s&crumb=%s', 'ENI.MI', $crumb));
        var_dump($response->content);
        $this->assertIsArray($cookie);
    }
}

