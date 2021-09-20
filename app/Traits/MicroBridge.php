<?php
 
namespace App\Traits;

use App\Traits\Bridges\GatewayAPI;
use App\Traits\Bridges\SchoolAPI;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 
 * ### Career Support API Bridge
 * The bridge to connect all available services in `Career Support` System. API based that uses 'guzzlehttp` as a core.
 * see [**Guzzle Documentation for details**](https://docs.guzzlephp.org/en/stable/)
 * 
*/
class MicroBridge
{
    /**
     * portal url
     * @var string $BASE_URL
     */
    protected $BASE_URL;

    /**
     * URI string
     * @var string $URI
     */
    protected $URI;

    /**
     * request method
     * @var string $METHOD
     */
    protected $METHOD;

    public function Send(string $method, string $uri ,array $params=[])
    {
        try {
            $this->METHOD = $method;

            if(empty($this->BASE_URL)) throw new Exception("BASE URL not found");
            $uri = ($uri[0]==="/")?substr($uri, 1):$uri;
            $this->URI = "{$this->BASE_URL}/{$uri}";

            $request = clone request();
            $request->replace($params);

            $client = new Client();

            $headers = [
                "Authorization" => $request->header('Authorization'),
                "Accept"        => "application/json"
            ];
            $params = CareerSupportHelper::GuzzleBuildRequest($request);

            $client = $client->request($this->METHOD, $this->URI,array_merge(['headers'=>$headers], $params));
            $response = $client->getBody()->getContents();

            return $response;
        } catch (\Throwable $exception) {
            if($exception instanceof ClientException || $exception instanceof ServerException){
                response(
                    $exception->getResponse()->getBody()->getContents(),
                    $exception->getResponse()->getStatusCode(),
                    $exception->getResponse()->getHeaders()
                )->send();
                exit();
                
            }else throw $exception;
        }
    }

    public static function school(string $portal = null, string $prefix = "/api/v1/school")
    {
        $api = (new self);

        $portal = $portal??env("SCHOOL_URL");
        $portal = substr($portal, -1)==="/"?substr($portal,0,-1):$portal;

        $prefix = ($prefix[0]==="/")?$prefix:"/$prefix";
        $prefix = substr($prefix, -1)==="/"?substr($prefix,0,-1):$prefix;

        $api->BASE_URL = trim("{$portal}{$prefix}", '/');
        if(empty($api->BASE_URL)) throw new HttpException("environment CANDIDATE_URL is empty");

        $schoolAPI = new SchoolAPI($api);

        return $schoolAPI;
    }

    public static function gateway(string $portal = null, string $prefix = "/api/gateway")
    {
        $api = (new self);

        $portal = $portal??env("GATEWAY_URL");
        if(empty($portal)) throw new Exception("Gateway Portal not found");
        $portal = substr($portal, -1)==="/"?substr($portal,0,-1):$portal;

        $prefix = ($prefix[0]==="/")?$prefix:"/$prefix";
        $prefix = substr($prefix, -1)==="/"?substr($prefix,0,-1):$prefix;

        $api->BASE_URL = trim("{$portal}{$prefix}", '/');
        if(empty($api->BASE_URL)) throw new HttpException("environment GATEWAY_URL is empty");

        $schoolAPI = new GatewayAPI($api);

        return $schoolAPI;
    }
}
