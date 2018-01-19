<?php

namespace CapMousse\ReactRestify\Http;

class Session
{
    private $prefix = "session_";
    private $dir = "/Users/groups/Code/graphpress-server/sessions";


    public function start($request, $response)
    {
        if(!$request->hasSession()) {
            $r = rand();
            $file = $this->getSessionFile($request);
            touch($file);
            $response->addCookie("id", $r);
        }
    }

    public function get($request, $key) {
        if(!$request->hasSession())
            return null;
        $session =  $this->fetchSession($request);
        if(!isset($session[$key]))
            return null;
        return $session[$key];
    }

    public function set($request, $key, $value) {
        if(!$request->hasSession())
            return null;
        $session =  $this->fetchSession($request);
        $session[$key] = $value;
        file_put_contents($this->getSessionFile($request), serialize($session));
    }

    protected function getSessionFile($request) {
        $id = $request->getSessionId();
        $file = $this->dir . DIRECTORY_SEPARATOR . $this->prefix . $id;
        return $file;
    }

    protected function fetchSession($request) {
        $file = $this->getSessionFile($request);
        if(!file_exists($file))
            return array();
        $session =  unserialize(file_get_contents($file));
        return $session;
    }
}