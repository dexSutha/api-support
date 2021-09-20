<?php

namespace App\Traits\Bridges;

use App\Enums\HTTPMethod;
use App\Traits\MicroBridge;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class PaymentGateway {

    /**
     * API bridge
     * @var MicroBridge $bridge
     */
    protected MicroBridge $bridge;

    public function __construct(MicroBridge $bridge) {
        $this->bridge = $bridge;
    }

    /**
     * Create new snap payment
     * @var array $params Midtrans params
     * @return string|object
     * @throws Exception|ClientException|ServerException
    */
    public function Create($params)
    {
        return $this->bridge->Send(HTTPMethod::POST, "payment/new", $params);
    }
}
