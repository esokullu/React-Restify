<?php

namespace CapMousse\ReactRestify\Http;

use React\Http\Request as ReactHttpRequest;
use CapMousse\ReactRestify\Evenement\EventEmitter;

class Request extends EventEmitter
{
    /** @var \React\Http\Request */
    public $httpRequest;

    /** @var string */
    private $content;

    /** @var array */
    private $data = [];

    /**
     * @var string - used to keep newly generated sessionId
     * and also optimizaton to not parse cookies each time
     */
    private $sessionId;

    /**
     * @param ReactHttpRequest $httpRequest
     */
    public function __construct(ReactHttpRequest $httpRequest)
    {
        $this->httpRequest = $httpRequest;
    }

    /**
     * Set the raw data of the request
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * Set the raw data of the request
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get formated headers
     * @return array
     */
    public function getHeaders()
    {
        $headers = array_change_key_case($this->httpRequest->getHeaders(), CASE_LOWER);

        foreach ($headers as $header) {
            $header = array_map(function ($value) {
                return strtolower($value);
            }, $header);
        }

        return $headers;
    }

    public function getCookie($key)
    {
        // react v0.8
        /*
        if(isset($this->httpRequest->getCookieParams()[$key]))
        {
            return $this->httpRequest->getCookieParams()[$key];
        }
        else {
            return null;
        }
        */
        $headers = $this->getHeaders();
        if(!isset($headers["cookie"])||!is_array($headers["cookie"])||count($headers["cookie"])==0) {
            return null;
        }
        $cookies = $headers["cookie"];
        foreach($cookies as $cookie) {
            $nameValuePair = explode('=', $cookie, 2);
            if (count($nameValuePair) === 2) {
                $k = urldecode($nameValuePair[0]);
                if($key == $k)
                    return urldecode($nameValuePair[1]);
            }
        }
    }

    public function hasSession()
    {
        $id = $this->getSessionId();
        return isset($id);
    }

    public function getSessionId()
    {
        if (is_null($this->sessionId)) {
            $this->sessionId = $this->getCookie("id");
        }
        return $this->sessionId;
    }

    /**
     * Used for new requests for which session was generated
     */
    public function setSessionId($newSessionId)
    {
        $this->sessionId = $newSessionId;
    }

    /**
     * Parse request data
     * @return void
     */
    public function parseData()
    {
        $headers = $this->getHeaders();

        if (!in_array($this->httpRequest->getMethod(), ['PUT', 'POST'])) {
            return $this->emit('end');
        }

        $this->httpRequest->on('data', function($data) use ($headers, &$dataResult) {
            $dataResult .= $data;

            if (isset($headers["content-length"])) {
                if (strlen($dataResult) == $headers["content-length"][0]) {
                    $this->httpRequest->close();
                }
            } else {
                $this->httpRequest->close();
            }
        });

        $this->httpRequest->on('end', function() use (&$dataResult) {
            $this->onEnd($dataResult);
        });
    }

    /**
     * On request end
     * @param  string $dataResult
     * @return void
     */
    private function onEnd($dataResult)
    {
        if ($dataResult === null) return $this->emit('end');

        if ($this->isJson()) $this->parseJson($dataResult);
        else $this->parseStr($dataResult);
    }

    /**
     * Parse querystring
     * @param  string $dataString
     * @return void
     */
    private function parseStr($dataString)
    {
        $data = [];
        parse_str($dataString, $data);

        $this->setContent($dataString);
        if(is_array($data)) $this->setData($data);

        $this->emit('end');
    }

    /**
     * Parse json string
     * @param  string $jsonString
     * @return void
     */
    private function parseJson($jsonString)
    {
        $jsonData = json_decode($jsonString, true);

        if ($jsonData === null) {
            $this->emit('error', [json_last_error_msg()]);
            return;
        }

        $this->setContent($jsonString);
        $this->setData($jsonData);
        $this->emit('end');
    }

    /**
     * Check if current request is a json request
     * @return boolean
     */
    public function isJson()
    {
        $headers = $this->getHeaders();

        return isset($headers['content-type']) && $headers['content-type'][0] == 'application/json';
    }

    /**
     * Set the data array
     * @param array $data array of data
     */
    public function setData($data)
    {
        $this->data = array_merge($data, $this->data);
    }

    /**
     * Get the data array
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
    
    /**
     * To handle methods like:
     * httpRequest->getQueryParams
     */
    public function __call($method, $params)
    {
        $res = $this->httpRequest->$method(...$params);
        return $res;
    }
}
