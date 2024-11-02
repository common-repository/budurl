<?php

class BudURLService {
    public $api_key;
    public $api_endpoint;
    public function __construct(){
    }

    public function init($api_key, $api_endpoint) {
        $this->api_key = $api_key;
        $this->api_endpoint = $api_endpoint;
    }

    public function hasCurl()
    {
        return function_exists('curl_init');
    }

    public function shorten($url, $note = '') {
        if (!$this->hasCurl()) {
            throw new Exception("CURL missing");
        }
        if (!$this->api_endpoint || $this->api_endpoint=='') {
            $this->api_endpoint = 'http://BudURL.Pro/api/v2/links/shrink?api_key=';
        }
        $request =  $this->api_endpoint
         . rawurlencode($this->api_key) . '&long_url='
         . rawurlencode($url) . '&notes='
         . rawurlencode($note) . '&dupe_check=1';
         // var_dump($request);
         // throw new Exception($request);
         
        $session = curl_init($request);
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($session);
        curl_close($session);

        if ($decode = json_decode($response, true)) {
            if (isset($decode['success']) && $decode['success']) {
                return $decode;
            } else {
                throw new Exception($decode['error_message']);
            }
        } else {
            throw new Exception("Error Processing Request");
        }
        return null;
    }
}


