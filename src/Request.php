<?php namespace Epsilon\Queryfly;

use Closure;
use Exception;

class Request
{
    protected $useRal = false;

    protected $url;

    protected $method = 'GET';

    protected $service;

    protected $data;

    public function __construct($method, $url, $data = array(), $service = '')
    {
        $this->method = strtoupper($method);
        $this->url = $url;
        $this->service = $service;
        $this->data = $data;
    }

    public function request()
    {
        if ($this->useRal)
        {
            return $this->requestWithRal($this->ok(), $this->failed());
        }

        return $this->requestWithCurl($this->ok(), $this->failed());
    }

    public function ok()
    {
        return function ($result)
        {
            $return = [];

            if (is_string($result))
            {
                $return = json_decode($result, true);
                if (is_null($return))
                {
                    $return = [];
                }
            }

            return $return;
        };
    }

    public function failed()
    {
        return function (Exception $e)
        {
            return [];
        };
    }

    protected function requestWithRal(Closure $ok, Closure $failed)
    {
        try
        {
            $return = ral($this->service, $this->url);
            if ($return)
            {
                return $ok($return);
            }

            return [];
        } 
        catch (Exception $e)
        {

        }
    }

    protected function requestWithCurl(Closure $ok, Closure $failed)
    {

        try
        {
            $ch = curl_init();

            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            if ($this->method == 'POST')
            {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
            }

            // grab URL and pass it to the browser
            $return = curl_exec($ch);

            // close cURL resource, and free up system resources
            curl_close($ch);

            if ($return) {
                return $ok($return);
            }

            return [];
        }
        catch(Exception $e)
        {
            return [];
        }
    }
}