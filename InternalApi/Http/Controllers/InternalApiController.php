<?php

namespace Modules\InternalApi\Http\Controllers;

use App\Jobs\TranscodeCallbackV2Job;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Language\Status;
use Modules\InternalApi\Services\ImageSpotService;

/**
 * 内部API业务处理
 */
class InternalApiController extends Controller
{
	protected $imageSpotService;

	public function __construct(ImageSpotService $imageSpotService)
	{
		$this->imageSpotService = $imageSpotService;
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * 鉴黄鉴暴业务处理
	 */
	public function contentReview(Request $request): JsonResponse
	{
		$data      = $request->post();
		$validator = Validator::make($data, [
			'channel' => 'required|in:1,2,3,4',
			'fileId'  => 'required|int',
			//            'userId' => 'required|int'
		]);
		if ($validator->fails()) {
			return sendResponse(Status::PARAMS_ERROR, $response, 'params not found');
		}
		$this->imageSpotService->contentReview($data['channel'], $data['fileId']);

		return sendResponse(Status::SUCCESS);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 * 内容识别
	 */
	public function spot(Request $request): JsonResponse
	{
		$data      = $request->post();
		$validator = Validator::make($data, [
			'image_url' => 'required',
		]);
		if ($validator->fails()) {
			return sendResponse(Status::PARAMS_ERROR, $response, 'params not found');
		}

		$result = $this->imageSpotService->spotImage($data['image_url']);

		return sendResponse(Status::SUCCESS, $result);

	}

	/**
	 * 转码回调处理
	 *
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function transcodeCallback(Request $request): JsonResponse
	{
		$data = $request->post();
        $data = is_array($data) ? json_encode($data) : $data;

		TranscodeCallbackV2Job::dispatch($data)->onQueue(config('queue.high'));

		return sendResponse(Status::SUCCESS);
	}

}
