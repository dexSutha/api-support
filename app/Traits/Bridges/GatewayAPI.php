<?php

namespace App\Traits\Bridges;

use App\Enums\HTTPMethod;
use App\Traits\MicroBridge;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GatewayAPI {

    /**
     * API bridge
     * @var MicroBridge $bridge
    */
    protected $bridge;

    public PaymentGateway $payment;

    public function __construct(MicroBridge $bridge) {
        $this->bridge = $bridge;
        $this->payment = new PaymentGateway($bridge);
    }

    /**
     * get candidate percentage
     * available params
     * ```
     * [
     *      'subdomain'    => "string|required"
     * ]
     * ```
     * @var array|object $params
     * @return string|object $response
     * @throws BadRequestHttpException|GuzzleException|Exception
    */
    public function GenerateSubdomain(array $params=[])
    {
        return $this->bridge->Send(HTTPMethod::PUT, "generate/sub-domain", $params);
    }

    /**
     *  generate invoice PDF
     *  params:
     * ```
     * [
     *      "category"                  => "required|string",
     *      "transaction.created"       => "required|date",
     *      "transaction.grand_total"   => "required|numeric",
     *      "transaction.order_id"      => "required|string",
     *      "transaction.status_display"=> "required|string",
     *      "item_details"              => "required|array|min:1",
     *      "item_details.*.name"       => "required|string",
     *      "item_details.*.quantity"   => "required|numeric|min:1",
     *      "item_details.*.ammount"    => "required|string",
     *      "item_details.*.price"      => "required|numeric",
     *      "company.company_name"      => "required|string",
     *      "company.email"             => "required|string",
     *      "company.phone"             => "required|string",
     *      "user.first_name"           => "required|string",
     *      "user.last_name"            => "required|string",
     *      "config.pageSize"           => "string", // DEFAULT: "A5"
     *      "config.orientation"        => "string", // DEFAULT: "Landscape"
     *      "config.DPI"                => "string", // DEFAULT: "100"
     *      "config.marginTop"          => "string", // DEFAULT: "0"
     *      "config.marginLeft"         => "string", // DEFAULT: "0"
     *      "config.marginRight"        => "string", // DEFAULT: "0"
     *      "config.imageQuality"       => "string", // DEFAULT: "50"
     *      "config.TargetEndpoint"     => "string", // DEFAULT: "http://internal:percobaan123@127.0.0.1:8000/template/cv-invoice"
     * ]
     * ```
     * @var array|object $params
     * @return object {token: string, redirect_url: string}
     * @throws BadRequestHttpException|GuzzleException|Exception
    */
    public function GeneratePaymentInvoicePDF($params=[])
    {
        $validation = Validator::make($params, [
            "category"                  => "required|string",
            "transaction.created"       => "required|date",
            "transaction.grand_total"   => "required|numeric",
            "transaction.order_id"      => "required|string",
            "transaction.status_display"=> "required|string",
            "item_details"              => "required|array|min:1",
            "item_details.*.category"   => "required|string",
            "item_details.*.subtotal"   => "required|numeric",
            "item_details.*.name"       => "required|string",
            "item_details.*.quantity"   => "required|numeric|min:1",
            "item_details.*.ammount"    => "required|string",
            "item_details.*.price"      => "required|numeric",
            "company.company_name"      => "required|string",
            "company.email"             => "required|string",
            "company.phone"             => "required|string",
            "user.first_name"           => "required|string",
            "user.last_name"            => "required|string",
            "config.pageSize"           => "string", // DEFAULT: "A5"
            "config.orientation"        => "string", // DEFAULT: "Landscape"
            "config.DPI"                => "string", // DEFAULT: "100"
            "config.marginTop"          => "string", // DEFAULT: "0"
            "config.marginLeft"         => "string", // DEFAULT: "0"
            "config.marginRight"        => "string", // DEFAULT: "0"
            "config.imageQuality"       => "string", // DEFAULT: "50"
            "config.TargetEndpoint"     => "string", // DEFAULT: "http://internal:percobaan123@127.0.0.1:8000/template/cv-invoice"
        ]);
        if($validation->fails()) throw new BadRequestHttpException($validation->errors()->first());

        $response = $this->bridge->Send(HTTPMethod::POST, "generate/payment/invoice", $params);
        
        return json_decode($response, true);
    }
}
