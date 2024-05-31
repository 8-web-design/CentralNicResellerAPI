<?php

namespace EightWebDesign\CentralNicResellerApi;

use Exception;
use GuzzleHttp\Client;
use SimpleXMLElement;
use Carbon\Carbon;
use EightWebDesign\CentralNicResellerApi\CentralNicResellerResponseParser as Parser;

class CentralNicResellerConnector
{

    private $username;
    private $password;
    private $sandbox;

    public function __construct(string $username, string $password, bool $sandbox = false)
    {
        $this->username = $username;
        $this->password = $password;
        $this->sandbox = $sandbox;
    }

    public function send_request(array $request)
    {
        $conn_url = sprintf('https://api%s.rrpproxy.net:8083/xmlrpc', $this->sandbox ? '-ote' : '');
        $conn_headers = ['Content-Type' => 'text/xml'];
        $conn_request_body = $this->get_request_body($request);
        $conn_client = new Client(['headers' => $conn_headers]);
        $response = $conn_client->post($conn_url, ['body' => $conn_request_body]);
        $response_body = (string) $response->getBody();
        $xml = simplexml_load_string($response_body);
        $parsed_response = Parser::parse($xml);
        $code = $parsed_response['CODE'];
        if (isset($code) and !in_array($code, [200, 210, 211, 212, 213, 214, 215, 218, 219, 220])) {
            $e = $parsed_response['DESCRIPTION'];
            throw new Exception($e);
        }
        return $parsed_response['PROPERTY'];
    }

    private function get_request_body(array $request)
    {
        $methodCall = new SimpleXMLElement('<methodCall/>');
        $methodCall->addChild('methodName', 'Api.xcall');
        $params = $methodCall->addChild('params');
        $param = $params->addChild('param');
        $value = $param->addChild('value');
        $struct = $value->addChild('struct');
        if ($this->sandbox) :
            $member = $struct->addChild('member');
            $name = $member->addChild('name', 's_opmode');
            $value = $member->addChild('value');
            $value->addChild('string', 'OTE');
        endif;
        $member = $struct->addChild('member');
        $name = $member->addChild('name', 's_login');
        $value = $member->addChild('value');
        $value->addChild('string', $this->username);
        $member = $struct->addChild('member');
        $name = $member->addChild('name', 's_pw');
        $value = $member->addChild('value');
        $value->addChild('string', $this->password);
        foreach ($request as $name => $content) :
            $member = $struct->addChild('member');
            $name = $member->addChild('name', $name);
            $value = $member->addChild('value');
            $value->addChild($content[0], $content[1]);
        endforeach;
        return $methodCall->asXML();
    }

    public function get_expiration_date(string $domain)
    {
        $domain = idn_to_ascii($domain);
        $request = [
            'COMMAND' => ['string', 'StatusDomain'],
            'DOMAIN' => ['string', $domain]
        ];
        $response = $this->send_request($request);
        $date = $response['RENEWALDATE'];
        if (!empty($date)) {
            $date = Carbon::createFromFormat('Y-m-d H:i:s.z', $date, 'UTC');
        }
        return !empty($date) ? $date : null;
    }

    public function status_domain($domain)
    {
        $domain = idn_to_ascii($domain);

        $request = [
            'COMMAND' => ['string', 'StatusDomain'],
            'DOMAIN' => ['string', $domain]
        ];
        return $this->send_request($request);
    }
}
