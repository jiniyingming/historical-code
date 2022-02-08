<?php

namespace Modules\Panels\Http\Controllers;

use App\Http\Validator\PanelsCardValidator;
use App\Http\Validator\PanelsValidator;
use App\Models\PanelsCardModel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Language\Status;
use Modules\Panels\Services\PanelsCardService;
use Modules\Panels\Services\PanelsService;

/**
 * 任务看板数据读取
 * 相关业务统一处理
 */
class PanelsV2Controller extends BaseController
{

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * 任务看板清单页
	 */
	public function itemView(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsValidator::list($params)) {
			return sendResponse($validator, $returnArray);
		}
		$project = $request->input('projectInfo');
		$userId  = getUserUid();

		PanelsService::initItems($project->id, $userId);
		$data = PanelsService::getItemList($params, $project->id, $userId);

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * 任务卡片增量接口
	 */
	public function cardView(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::list($params)) {
			return sendResponse($validator, $returnArray);
		}
		//权限校验
		$project   = $request->input('projectInfo');
		$projectId = $project->id;
		$cards     = PanelsCardService::getCardList($params, $projectId);
		if (empty($cards)) {
			return sendResponse(Status::SUCCESS, $returnArray);
		}
		$cards = PanelsCardService::docHandle($cards, $project, false, false);

		return sendResponse(Status::SUCCESS, $cards);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function cardDetail(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::detail($params)) {
			return sendResponse($validator, $returnArray);
		}
		//权限校验
		$project = $request->input('projectInfo');
		//数据校验
		if (!PanelsCardModel::query()->where([
			'id'         => $params['id'],
			'project_id' => $project->id,
		])->exists()) {
			return sendResponse(Status::PANEL_CARD_NOT_FOUND, $result);
		}
		$card = PanelsCardService::getPanelCardDetail($params['id'], $project);

		return sendResponse(Status::SUCCESS, $card);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function calendarList(Request $request): JsonResponse
	{
		$params = $request->all();

		//参数校验
		if ($validator = PanelsCardValidator::calendarList($params)) {
			return sendResponse($validator);
		}
		$projectId = $request->input('projectInfo')->id;
		$data      = PanelsCardService::searchCardListByCalendar($projectId, $params);

		return sendResponse(Status::SUCCESS, $data);
	}
}
