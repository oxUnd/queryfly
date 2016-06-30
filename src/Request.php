<?php namespace Epsilon\Queryfly;

use Closure;
use Exception;

class Request
{
    const STATUS_OK = 0;
    
    const STATUS_FAILED = 10;

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

    /**
     * callback function, if HTTP request success.
     * 
     * return Closure
     */
    public function ok()
    {
        return function ($result)
        {
            $return = [];

            if (is_string($result))
            {
                $result = json_decode($result, true);
                if (is_null($return))
                {
                    $return = $this->buildReturn([], self::STATUS_FAILED, 'json paser failed: ' . json_last_error());
                }
                else
                {
                    $return = $this->buildReturn($result, self::STATUS_OK);
                }
            }

            return $return;
        };
    }

    /**
     * callback function, if HTTP request failed.
     * 
     * return Closure
     */
    public function failed()
    {
        return function (Exception $e)
        {

            return $this->buildReturn([], self::STATUS_FAILED, $e);
        };
    }

    /**
     * normolize HTTP request result.
     * 
     * @param array $data  
     * @param int $status
     * @param string $message
     */
    public function buildReturn($data, $status, $message = '')
    {
        return [
            'data' => $data,
            'status' => $status,
            'error_message' => $message, 
        ];
    }

    /**
     * request remote resource with RAL.
     *
     * @param \Closure $ok
     * @param \Closure $failed
     * @return array
     */
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
            return $failed($e);
        }
    }

    /**
     * request remote resource with cURL
     *
     * @param Closure $ok
     * @param Closure $failed
     * @return array
     */
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

            $curlError = curl_error($ch);

            // close cURL resource, and free up system resources
            curl_close($ch);

            if ($return) {
                return $ok($return);
            }

            return $failed(new Exception($curlError));
        }
        catch(Exception $e)
        {
            return $failed($e);
        }
    }
}