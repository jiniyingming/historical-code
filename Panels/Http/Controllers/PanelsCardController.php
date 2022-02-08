<?php

namespace Modules\Panels\Http\Controllers;

use App\Http\Validator\PanelsCardValidator;
use App\Models\PanelsCardDocModel;
use App\Models\PanelsCardModel;
use App\Models\ProjectDocModel;
use App\Services\OperateLogService;
use Modules\Panels\Services\PanelsBaseService;
use Modules\Panels\Services\PanelsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Language\Status;

class PanelsCardController extends BaseController
{

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function bind(Request $request): JsonResponse
	{
		$params   = $request->all();
		$userInfo = $request->input('userInfo');
		//参数校验
		if ($validator = PanelsCardValidator::bind($params)) {
			return sendResponse($validator, $returnArray);
		}
		//权限校验
		$project = $request->input('projectInfo');

		//数据校验
		if (!PanelsCardModel::where([
			'id'         => $params['card_id'],
			'project_id' => $project->id,
		])->exists()) {
			return sendResponse(Status::NOT_FIND_DATA, $result);
		}
		$docIds = explode(',', $params['doc_id']);
		if (ProjectDocModel::whereIn('id', $docIds)->count() !== count($docIds)) {
			return sendResponse(Status::NOT_FIND_DATA, $result);
		}
		//数量校验
		$count = DB::connection('mysql_project')->table('xy_panels_card_doc as cd')->leftJoin('xy_project_doc as d', 'd.id', '=', 'cd.doc_id')->where('cd.card_id', $params['card_id'])->whereIn('d.status', [
			2,
			4,
		])->whereNull('d.deleted_at')->count();
		if ($count + count($docIds) > 100) {
			return sendResponse(Status::PANEL_BIND_NUM_MAX, $result);
		}
		//是否已经存在
		$isBind = PanelsCardDocModel::where([
			'card_id'    => $params['card_id'],
			'project_id' => $project->id,
		])->whereIn('doc_id', $docIds)->pluck('doc_id')->toArray();
		//处理要插入关联表的数据
		$insertData = [];
		foreach ($docIds as $docId) {
			if (!in_array($docId, $isBind)) {
				$insertData[ $docId ] = [
					'project_id' => $project->id,
				];
			}
		}
		try {
			$card  = PanelsCardModel::find($params['card_id']);
			$_card = clone $card;
			$card->doc()->attach($insertData);
			$_card->updated_at = date('Y-m-d H:i:s');
			$_card->save();
			//日志
			$content_info = [];
			$content_id   = 'p_69';
			if (count($docIds) > 3) {
				$content_info['num'] = count($docIds);
				$content_id          = 'p_70';
				$docIds              = array_slice($docIds, 0, 3);
			}
			$docInfo              = ProjectDocModel::whereIn('id', $docIds)->pluck('name')->toArray();
			$content_info['bind'] = implode(', ', $docInfo);
			OperateLogService::operateLog($project->project_number, (int)$userInfo['u_id'], $userInfo['nickname'], $userInfo['avatar'] ?? config('user.operate_logo_user_logo'), $content_id, 'panel_card', '1', $card->title, $content_info, 'project', 0, $card->id);
			PanelsService::sendPanelsNotice(//--卡片详情页
				$card->item_id, [
				'project_number' => $project->project_number,
				'item_id'        => $card->item_id,
				'panels_card_id' => $card->id,
				'project_id'     => $project->id,
			], PanelsBaseService::PANELS_CARD_INFO_NOTICE);

			return sendResponse(Status::SUCCESS, $result);
		} catch (\Throwable $e) {
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return sendResponse(Status::DATA_ABNORMAL, $result);
		}
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function unBind(Request $request): JsonResponse
	{
		$params   = $request->all();
		$userInfo = $request->input('userInfo');
		//参数校验
		if ($validator = PanelsCardValidator::unBind($params)) {
			return sendResponse($validator, $returnArray);
		}
		//权限校验
		$project     = $request->input('projectInfo');
		$projectUser = $request->input('projectUserInfo');

		//数据校验
		if (!PanelsCardModel::where('id', $params['card_id'])->exists()) {
			return sendResponse(Status::PAMELS_DELED_CARD, $result);
		}
		$docIds = explode(',', $params['doc_id']);
		if (ProjectDocModel::whereIn('id', $docIds)->count() !== count($docIds)) {
			return sendResponse(Status::NOT_FIND_DATA, $result);
		}

		try {
			$card  = PanelsCardModel::find($params['card_id']);
			$_card = clone $card;
			$card->doc()->detach($docIds);
			$_card->updated_at = date('Y-m-d H:i:s');
			$_card->save();
			//日志
			$content_info = [];
			$content_id   = 'p_71';
			if (count($docIds) > 3) {
				$content_info['num'] = count($docIds);
				$content_id          = 'p_72';
				$docIds              = array_slice($docIds, 0, 3);
			}
			$docInfo              = ProjectDocModel::whereIn('id', $docIds)->pluck('name')->toArray();
			$content_info['bind'] = implode(',', $docInfo);
			OperateLogService::operateLog($project->project_number, (int)$userInfo['u_id'], $userInfo['nickname'], $userInfo['avatar'] ?? config('user.operate_logo_user_logo'), $content_id, 'panel_card', '1', $card->title, $content_info, 'project', 0, $card->id);

			PanelsService::sendPanelsNotice(//--卡片详情页
				$card->item_id, [
				'project_number' => $project->project_number,
				'item_id'        => $card->item_id,
				'panels_card_id' => $card->id,
				'project_id'     => $project->id,
			], PanelsBaseService::PANELS_CARD_INFO_NOTICE);

			return sendResponse(Status::SUCCESS, $result);
		} catch (\Throwable $e) {
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return sendResponse(Status::DATA_ABNORMAL, $result);
		}
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function checkBind(Request $request): JsonResponse
	{
		$params = $request->all();
		$uid    = $request->input('userInfo')['u_id'];
		//参数校验
		if ($validator = PanelsCardValidator::checkBind($params)) {
			return sendResponse($validator, $returnArray);
		}

		//权限校验
		$project = $request->input('projectInfo');
		$docIds  = explode(',', $params['doc_id']);

		//数据校验
		if (ProjectDocModel::whereIn('id', $docIds)->where('project_id', $project->id)->count() !== count($docIds)) {
			return sendResponse(Status::NOT_FIND_DATA, $result);
		}
		if (PanelsCardDocModel::whereIn('doc_id', $docIds)->where('project_id', $project->id)->exists()) {
			//存在关联
			$result['isBind'] = 1;

			return sendResponse(Status::SUCCESS, $result);
		}
		//不存在关联
		$request['isBind'] = 2;

		return sendResponse(Status::SUCCESS, $result);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * 检查绑定接口
	 */
	public function checkNum(Request $request): JsonResponse
	{
		$params = $request->all();
		$uid    = $request->input('userInfo')['u_id'];

		//参数校验
		if ($validator = PanelsCardValidator::checkNum($params)) {
			return sendResponse(Status::PARAMS_ERROR, $returnArray, $validator);
		}

		//数据校验
		$bindNum           = DB::connection('mysql_project')->table('xy_panels_card_doc as cd')->leftJoin('xy_project_doc as d', 'd.id', '=', 'cd.doc_id')->where('cd.card_id', $params['card_id'])->whereNull('d.deleted_at')->count();
		$result['canBind'] = 1;
		if ($bindNum + (int)$params['count'] > 100) {
			$result['canBind'] = 2;
		}

		//不存在关联
		return sendResponse(Status::SUCCESS, $result);
	}
}
