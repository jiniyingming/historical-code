<?php

namespace Modules\Panels\Http\Controllers;

use Modules\Panels\Services\PanelsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Language\Status;

/**
 * 任务看板
 * 冗余业务接口
 */
class PanelsController extends BaseController
{
	/**
	 * @param Request $request
	 * 查看项目所属关系
	 */
	public function getPanelsBelonging(Request $request): JsonResponse
	{
		$project_number = (string)$request->input('projectSign');
		$doc_id         = (array)$request->input('docSign');
		if ($project_number && $doc_id) {
			$data = PanelsService::getPanelsBelonging($doc_id, $project_number);
		}

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function getPanelsList(Request $request): JsonResponse
	{
		$projectNo = $request->input('project_id');
		if (empty($projectNo)) {
			return sendResponse(Status::MISS_PARAM);
		}
		//权限校验
		$project  = $request->input('projectInfo');
		$itemList = PanelsService::getPanelsList($project->id);

		return sendResponse(Status::SUCCESS, $itemList);

	}
}
