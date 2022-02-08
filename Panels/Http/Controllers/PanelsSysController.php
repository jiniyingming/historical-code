<?php

namespace Modules\Panels\Http\Controllers;

use App\Http\Controllers\AuthController;
use App\Http\Validator\PanelsCardValidator;
use App\Http\Validator\PanelsValidator;
use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Language\Status;
use Modules\Panels\Services\Execute\OperatorCardsService;
use Modules\Panels\Services\Execute\OperatorPanelsService;

/**
 * 任务看板写入操作
 * 相关业务统一处理
 */
class PanelsSysController extends BaseController
{

	private $operatorPanelsService;
	private $operatorCardsService;

	public function __construct(OperatorPanelsService $operatorPanelsService, OperatorCardsService $operatorCardsService)
	{
		$this->operatorPanelsService = $operatorPanelsService;
		$this->operatorCardsService  = $operatorCardsService;
	}
	/*===================================================================================================任务看板-任务清单====================================================================================================================================*/

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 * 创建清单
	 */
	public function createPanels(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsValidator::create($params)) {
			return sendResponse($validator, $returnArray);
		}
		//权限校验
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');

		//数据校验
		$count = PanelsItemModel::query()->where('project_id', $project->id)->count();
		if ($count >= 40) {
			//数量超过限制
			return sendResponse(Status::PANEL_NUM_MAX, $returnArray);
		}

		[$result, $value] = $this->operatorPanelsService->create($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value);

	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 * 更新清单
	 */
	public function updatePanels(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsValidator::update($params)) {
			return sendResponse($validator, $returnArray);
		}
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');
		[$result, $value] = $this->operatorPanelsService->update($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function deletePanels(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsValidator::delete($params)) {
			return sendResponse($validator, $returnArray);
		}
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');
		[$result, $value] = $this->operatorPanelsService->delete($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function sortPanels(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsValidator::sort($params)) {
			return sendResponse($validator, $returnArray);
		}
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');
		[$result, $value] = $this->operatorPanelsService->sort($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value);
	}

	/*===================================================================================================任务看板-任务卡片====================================================================================================================================*/
	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function createCard(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::create($params)) {
			return sendResponse($validator, $returnArray);
		}
		//权限校验
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');

		//数据校验
		if (PanelsCardModel::query()->where([
				'item_id'    => $params['item_id'],
				'project_id' => $project->id,
			])->count() >= 200) {
			return sendResponse(Status::PANEL_CARD_NUM_MAX, $returnArray);
		}
		[$result, $value, $error] = $this->operatorCardsService->create($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value, $error);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function updateCard(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::update($params)) {
			return sendResponse($validator, $returnArray);
		}
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');
		[$result, $value, $error] = $this->operatorCardsService->update($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value, $error);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function sortCard(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::mvItem($request->all())) {
			return sendResponse($validator, $returnArray);
		}
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');
		[$result, $value] = $this->operatorCardsService->sort($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function deleteCard(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::delete($params)) {
			return sendResponse($validator, $returnArray);
		}
		$project     = $request->input('projectInfo');
		$userInfo    = $request->input('userInfo');
		$projectUser = $request->input('projectUserInfo');
		$permission  = AuthController::projectAuthority($project->panels_op_permission, $projectUser->type);
		//只能删除自己创建的卡片
		if (!$permission && !PanelsCardModel::where([
				'creator'    => $userInfo['u_id'],
				'project_id' => $project->id,
				'id'         => $params['id'],
			])->exists()) {
			return sendResponse(Status::NO_PERMISSION, $returnArray);
		}

		[$result, $value] = $this->operatorCardsService->delete($params, getUserUid(), (object)[
			'userInfo'        => $userInfo,
			'projectInfo'     => $project,
			'projectUserInfo' => $projectUser,
		]);
		if ($result) {
			return sendResponse(Status::SUCCESS, $value);
		}

		return sendResponse(Status::DATA_ABNORMAL, $value);
	}
}
