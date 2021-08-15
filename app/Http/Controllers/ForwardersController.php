<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ForwardersController extends Controller {

    public function CandidateMessagingCount(Request $request)
    {
        try {
            return (new CandidateChannelListController)->countChat($request);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function CandidateListChannel(Request $request)
    {
        try {
            return (new CandidateChannelListController)->listChannel($request);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
