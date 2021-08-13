<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ResponseHelper
{

    public function makeJSONResponse($data, int $code)
    {
        if($code === 4){
            $data = array_merge($data, request()->all());
        }
        return response()->json($data, $code);
        exit;
    }

    public function customErrorMessage()
    {
        return [
            'school_id.required'    => 'School id is required custom.',
            'school_id.exists'      => 'School id is invalid.',
            'school_id.numeric'      => 'School id must be a number'
        ];
    }
}
