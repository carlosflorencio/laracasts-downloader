<?php
/**
 * Wistia.net functions
 */

namespace App\Http;

use App\Exceptions\NoWistiaIDException;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Ubench;

/**
 * Class Wistia
 * @package App\Http
 */
class Wistia
{
    /**
     * Guzzle client
     * @var Client
     */
    private $client;

    /**
     * Guzzle cookie
     * @var CookieJar
     */
    private $cookie;

    /**
     * Ubench lib
     * @var Ubench
     */
    private $bench;

    /**
     * @var $html
     */
    private $html;

    /**
     * @var $wistiaID
     */
    private $wistiaID;

    /**
     * Receives dependencies
     *
     * @param string $html
     * @param Ubench $bench
     */
    public function __construct($html, Ubench $bench)
    {
        $this->client = new \GuzzleHttp\Client(['base_url' => 'http://www.clipconverter.cc']);
        $this->cookie = new CookieJar();
        $this->bench = $bench;
        $this->html = $html;
    }
    /**
     * Get wistia.net video url
     */
    public function getDownloadUrl()
    {
        try {
            $this->getWistiaID();
        } catch(NoWistiaIDException $e) {
            Utils::write(sprintf("Can't find any wistia.net ID! :("));
            return false;
        }
        
        $response =  $this->client->post('check.php', [
            'cookies' => $this->cookie,
            'body'    => [
                'mediaurl'    => 'http://fast.wistia.net/embed/iframe/'.$this->wistiaID
            ],
            'verify' => false
        ]);

        $html = $response->getBody()->getContents();

        $data = json_decode($html,true);

        if(!isset($data['url']))
            return false;

        $finalUrl = '';
        $maxSize = 0;
        foreach($data['url'] as $url) {
            if($url['size'] > $maxSize) {
                $maxSize = $url['size'];
                $finalUrl = $url['url'];
            }
        }
        Utils::writeln(sprintf("Found video URL %s ",
            $finalUrl
        ));

        return $finalUrl;
    }

    /**
     * Get wistia.net video id
     */
    protected function getWistiaID() {
        if(preg_match('~wistia_async_([a-z0-9]+)\s~',$this->html,$match)) {
            $this->wistiaID = trim($match[1]);

            Utils::writeln(sprintf("Found Wistia.net ID %s ",
                $this->wistiaID
            ));
        }
        else {
            return false;
        }
    }
}
