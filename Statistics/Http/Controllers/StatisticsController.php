<?php

namespace Modules\Statistics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Language\Status;

class StatisticsController extends Controller
{

    public function statisticsInit(): JsonResponse
    {
       return sendResponse(Status::SUCCESS);
    }

}
