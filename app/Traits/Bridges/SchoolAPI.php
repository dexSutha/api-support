<?php

namespace App\Traits\Bridges;

use App\Enums\HTTPMethod;
use App\Traits\MicroBridge;

class SchoolAPI {

    /**
     * API bridge
     * @var MicroBridge $bridge
     */
    protected $bridge;

    public function __construct(MicroBridge $bridge) {
        $this->bridge = $bridge;
    }

    /**
     * get candidate percentage
     * available params
     * ```
     * [
     *      "to_sql"                    => "boolean",
     *      "with_sql"                  => "boolean",
     *      "only_personal_id"          => "boolean",
     *      "percentage.operator"       => [Rule::in(['<','<=','<>','=','>=', '>'])],
     *      "percentage.value"          => "numeric|min:0",
     *      "percentage_in"             => "array|min:1",
     *      "percentage_in.*"           => "numeric|min:1|max:100",
     *      "personal_info_id_in"       => "array|min:1",
     *      "personal_info_id_in.*"     => "numeric|min:1",
     *      "group_by_completeness"       => "boolean",
     *      "profile_completeness"      => "numeric|min:0",
     *      "profile_completeness_from" => "numeric|min:0",
     *      "profile_completeness_to"   => "numeric|min:0",
     * ]
     * ```
     * @var array $params
     * @return string|object $response
     */
    public function CandidatePercentage(array $params=[])
    {
        return $this->bridge->Send(HTTPMethod::POST, "candidate/list-percentage", $params);
    }
}
