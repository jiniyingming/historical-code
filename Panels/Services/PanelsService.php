<?php

namespace Modules\Panels\Services;

use Illuminate\Support\Facades\Request;
use Modules\Panels\Jobs\PanelsIncrementJob;
use App\Models\PanelsCardDocModel;
use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use App\Models\ProjectDocModel;
use App\Models\ProjectModel;
use App\Models\ProjectUserModel;
use App\Models\UserInfoModel;
use App\Services\HelperServiceTrait;
use App\Services\OperateLogService;
use App\Services\ProjectFileService;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Language\Status;
use Notice\Notice;

class PanelsService extends PanelsBaseService
{

	public static function initItems($projectId, $uid): void
	{
		//校验并创建初始化数据
		if (!PanelsItemModel::withTrashed()->select('project_id')->where('project_id', $projectId)->exists()) {
			PanelsItemModel::itemInit($projectId, $uid);
		}
	}

	/**
	 * @param array $filterParams
	 * @param       $projectId
	 * @param int   $userId
	 *
	 * @return array
	 * 任务看板清单重构
	 */
	public static function getItemList(array $filterParams, $projectId, int $userId = 0): array
	{

		$updatedAt   = $filterParams['updated'] ?? 0;
		$pageSize    = $filterParams['num'] ?? 10;
		$spiltItemId = $filterParams['page_id'] ?? false;
		//---基础数据信息获取
		$query    = PanelsItemModel::query()->where('project_id', $projectId)->when($updatedAt, function ($query, $update_time) {
			--$update_time;
			$updatedAt = date('Y-m-d H:i:s', $update_time);
			$query->where('updated_at', '>=', $updatedAt);
		});
		$cardsNum = 30;

		//--按顺序值排序
		if ($spiltItemId) {
			$query->where('sort', '>', PanelsItemModel::query()->where('id', $spiltItemId)->first()->sort ?? 0);
		}

		$panelsData = $query->limit($pageSize)->cursor();

		if ($panelsData) {
			$panelsData = $panelsData->toArray();

			$panelsCardData = [];
			foreach ($panelsData as $row) {
				$query                        = PanelsCardModel::query()->where([
					'item_id'    => $row['id'],
					'project_id' => $projectId,
				]);
				$query                        = PanelsItemModel::setItemListFilter($filterParams, $query)->limit($cardsNum)->get();
				$panelsCardData[ $row['id'] ] = $query ? $query->toArray() : [];
			}
		} else {
			return self::setItemList($panelsData, null);
		}

		$panelsCardData = PanelsCardService::getPanelsCardList($panelsCardData, $projectId, $userId);

		$cardQueryObj = PanelsItemModel::setItemListFilter($filterParams, PanelsCardModel::query()->where('project_id', $projectId));

		return self::setItemList($panelsData, $cardQueryObj, $panelsCardData);

	}

	/**
	 * @param         $panelsData
	 * @param Builder $cardQueryObj
	 * @param array   $panelsCardData
	 *
	 * @return array
	 */
	private static function setItemList($panelsData, Builder $cardQueryObj, array $panelsCardData = []): array
	{
		$returnVal = [
			'list'            => [],
			'card_all_num'    => 0,
			'card_finish_num' => 0,
		];
		if (!$panelsData || func_num_args() !== 3) {
			return $returnVal;
		}
		$cardNumMap                   = clone $cardQueryObj;
		$cardFinishNumMap             = clone $cardQueryObj;
		$cardNum                      = clone $cardQueryObj;
		$cardFinishNum                = clone $cardQueryObj;
		$cardNumMap                   = $cardNumMap->select(DB::raw('count(*) as nums, item_id'))->groupBy('item_id')->pluck('nums', 'item_id');
		$cardFinishNumMap             = $cardFinishNumMap->select(DB::raw('count(*) as nums, item_id'))->where('status', 3)->groupBy('item_id')->pluck('nums', 'item_id');
		$returnVal['card_all_num']    = $cardNum->count();
		$returnVal['card_finish_num'] = $cardFinishNum->where('status', 3)->count();

		$project = getProjectInfo();
		foreach ($panelsData as &$item) {
			$item['cards']      = PanelsCardService::docHandle($panelsCardData[ $item['id'] ] ?? [], $project, false);
			$item['cards_num']  = $cardNumMap[ $item['id'] ] ?? 0;
			$item['finish_num'] = 0;
			$item['cards_num'] > 0 && $item['finish_num'] = $cardFinishNumMap[ $item['id'] ] ?? 0;
		}

		$returnVal['list'] = $panelsData;

		return $returnVal;
	}

	/**
	 * @param     $triggerId
	 * @param     $content
	 * @param     $type
	 * @param int $receiverType
	 * 任务看板相关通知 统一入口
	 */
	public static function sendPanelsNotice($triggerId, $content, $type, int $receiverType = 2): void
	{
		Log::channel('notice')->info('sendPanelsNotice', func_get_args());
		$userInfo       = json_encode(['real_name' => '', 'avatar' => '']);
		$project_number = $content['project_number'] ?? 0;
		Notice::createNotice(getUserUid(), $userInfo, $content['project_id'] ?? 0, $content, $project_number, $receiverType, $type, $project_number);

		return;

		if (($content['project_id'] ?? false) && (getProjectInfo()->company_id ?? false)) {
			$noticeUsers = ProjectUserModel::query()->where([
				'company_id' => getProjectInfo()->company_id,
				'project_id' => $content['project_id'],
				'status'     => 1,
			])->pluck('user_id')->toArray();
			if ($noticeUsers) {
				$noticeUsers = self::checkOutsideUsers($noticeUsers);
				Log::channel('notice')->info('sendPanelsNotice', [
					'params'      => func_get_args(),
					'noticeUsers' => $noticeUsers,
				]);
				$noticeUsers = array_unique($noticeUsers);
				foreach ($noticeUsers as $notice) {
					(int)getUserUid() !== (int)$notice && Notice::createNotice(getUserUid(), $userInfo, $notice, $content, $project_number, 1, $type, $project_number);
				}
			}
		}

	}

	/**
	 * @param      $list
	 * @param      $project
	 * @param      $usersInfo
	 * @param      $extMap
	 * @param bool $showComment
	 *
	 * @return array
	 * 任务看板 数据结构标准统一化
	 */
	public static function processList($list, $project, $usersInfo, $extMap, bool $showComment = true): array
	{

		$tree   = $project->tree_cache;
		$result = ProjectFileService::dealPanelsFileList($list, $tree, $usersInfo, $extMap, $showComment);

		return is_object($result) ? $result->all() : $result;
	}

	/**
	 * @param      $operatorData
	 * @param bool $isSendNotice
	 * @param bool $isRecordOperatorLog
	 * 文件操作 关联任务看板连带处理
	 */
	public static function removeProjectFileByPanel($operatorData, bool $isSendNotice = true, bool $isRecordOperatorLog = true): void
	{
		$chargeFileMap = $operatorData['chargeFileMap'];
		$doc_ids       = $operatorData['doc_map'] ?? [];
		if (empty($doc_ids)) {
			return;
		}
		$doc_ids          = self::getDocFolderTreeIds($doc_ids);
		$panels_exist_doc = PanelsCardDocModel::query()->whereIn('doc_id', $doc_ids)->get();
		if (!$panels_exist_doc) {
			return;
		}
		$panels_exist_doc = $panels_exist_doc->toArray();

		$card_map  = [];
		$card_docs = [];
		foreach ($panels_exist_doc as $item) {
			if (isset($chargeFileMap[ $item['doc_id'] ])) {
				$card_map[ $item['card_id'] ][] = $chargeFileMap[ $item['doc_id'] ];
			}
			$card_docs[ $item['card_id'] ][] = $item['doc_id'];
		}
		$project_number = $operatorData['project_number'] ?? null;
		PanelsIncrementJob::dispatch([$project_number => $doc_ids])->onQueue(config('queue.high'));

		foreach ($card_map as $card_id => $item) {
			if ($isRecordOperatorLog) {
				$name_map        = array_unique($item);
				$name_over_limit = count($name_map);
				$resource_name   = implode(',', array_slice($name_map, 0, 3));

				if ($name_over_limit > 3) {
					$content_id = 'p_74';
					$chard_info = [
						'resource_names' => $resource_name,
						'bind_num'       => $name_over_limit,
					];
				} else {
					$content_id = 'p_73';
					$chard_info = ['resource_names' => $resource_name];
				}
				OperateLogService::operateLog($project_number, (int)$operatorData['user_id'], $operatorData['nick_name'] ?? '', $operatorData['avatar'] ?? config('user.operate_logo_user_logo'), $content_id, 'panel_card', $operatorData['platform'], $resource_name, $chard_info, $operatorData['source'] ?? 'project', 0, $card_id);
			}
			if ($isSendNotice) {
				$panel_item = PanelsCardModel::query()->where('id', $card_id)->limit(1)->first();
				if ($panel_item) {
					self::sendPanelsNotice(//--卡片详情页
						$panel_item->item_id, [
						'project_number' => $project_number,
						'item_id'        => $panel_item->item_id,
						'panels_card_id' => $card_id,
						'doc_ids'        => $card_docs[ $card_id ] ?? [],
						'project_id'     => $panel_item->project_id,
					], self::PANELS_UNBIND_PROJECT);
				}
			}
		}
	}

	/**
	 * @param array  $docIds
	 * @param string $projectNumber
	 *
	 * @return array
	 * 获取项目内文件与任务面板的关系所属
	 */
	public static function getPanelsBelonging(array $docIds, string $projectNumber): array
	{
		$return_val = [
			'delete_card_ids' => [],
			'delete_doc_map'  => [],
			'panels_map'      => [],
			'project_id'      => 0,
		];
		$project    = ProjectModel::query()->where('project_number', $projectNumber)->first();
		if (!$project) {
			return $return_val;
		}
		$return_val['project_id'] = $project->id;
		$docIds                   = self::getDocFolderTreeIds($docIds);

		$doc_exec    = ProjectDocModel::withTrashed()->whereIn('id', $docIds)->select('id', 'version_no', 'deleted_at')->get()->toArray();
		$doc_deleted = [];

		$version_map = [];
		array_map(static function ($val) use (&$doc_deleted, &$docIds, &$version_map) {
			if (!empty($val['deleted_at'])) {
				$doc_deleted[] = $val['id'];
				$val['version_no'] > 0 && $doc_deleted[] = $val['version_no'];
			}
			if ($val['version_no'] > 0) {
				$docIds        = (array_unique(array_merge($docIds, [$val['version_no']])));
				$version_map[] = $val['version_no'];
			}
		}, $doc_exec);
		$docIds = array_merge(ProjectDocModel::withTrashed()->whereIn('version_no', $version_map)->pluck('id')->toArray(), $docIds);

		$card_data = PanelsCardDocModel::query()->where(['project_id' => $project->id])->whereIn('doc_id', $docIds)->get()->toArray();
		if (!$card_data) {
			return $return_val;
		}
		$delete_doc_map                = [];
		$return_val['delete_card_ids'] = array_values(array_filter(array_unique(array_map(static function ($v) use ($doc_deleted, &$delete_doc_map) {
			if (in_array($v['doc_id'], $doc_deleted, true)) {
				$delete_doc_map[ $v['card_id'] ][] = $v['doc_id'];

				return $v['card_id'];
			}

			return null;
		}, $card_data))));

		foreach ($delete_doc_map as $key => $val) {
			$return_val['delete_doc_map'][] = [
				'card_id' => $key,
				'doc_ids' => $val,
			];
		}
		$card_ids = array_column($card_data, 'card_id');
		$data     = PanelsCardModel::query()->whereIn('id', $card_ids)->select('id as card_id', 'item_id')->get()->toArray();
		if (!$data) {
			return $return_val;
		}
		$chunk_val = [];
		array_map(static function ($val) use (&$chunk_val) {
			$chunk_val[ $val['item_id'] ][] = $val['card_id'];
		}, $data);

		foreach ($chunk_val as $key => $card_ids) {
			$return_val['panels_map'][] = [
				'item_id'  => $key,
				'card_ids' => $card_ids,
			];
		}

		return $return_val;
	}

	/**
	 * @param array  $docIds
	 * @param string $projectNumber
	 * @param false  $isSendCardNotice
	 * @param int    $overTime
	 * 任务面板 增量处理
	 */
	public static function checkPanelsIncrement(array $docIds, string $projectNumber, bool $isSendCardNotice = false, int $overTime = 0): void
	{
		try {
			$check_data = self::getPanelsBelonging($docIds, $projectNumber);
			$panels_map = $check_data['panels_map'];
			$items_ids  = array_column($panels_map, 'item_id');
			$time       = date('Y-m-d H:i:s', time() + $overTime);
			PanelsItemModel::query()->where(['id' => $items_ids])->update(['updated_at' => $time]);
			foreach ($panels_map as $value) {
				PanelsCardModel::query()->whereIn('id', $value['card_ids'])->update(['updated_at' => $time]);
				$isSendCardNotice && self::sendPanelsNotice($projectNumber, [
					'project_number' => $projectNumber,
					'item_id'        => $value['item_id'],
					'project_id'     => $check_data['project_id'] ?? 0,
				], self::PANELS_CARD_NOTICE);
			}
		} catch (Exception $exception) {
			Log::error('checkPanelsIncrement', [
				'params' => func_get_args(),
				'msg'    => $exception->getMessage(),
			]);
		}
	}

	/**
	 * @param  $docIds
	 *
	 * @return array
	 * 递归获取文件所属文件夹树 doc id集合
	 */
	public static function getDocFolderTreeIds($docIds): array
	{
		$docIds     = is_array($docIds) ? $docIds : [$docIds];
		$docIds     = array_filter($docIds);
		$folderIds  = collect(ProjectDocModel::withTrashed()->whereIn('id', $docIds)->pluck('top_id')->toArray())->filter(function ($val) {
			return $val > 0;
		})->toArray();
		$versionNos = collect(ProjectDocModel::withTrashed()->whereIn('version_no', $docIds)->pluck('id')->toArray())->filter(function ($val) {
			return $val > 0;
		})->toArray();
		$folderIds  = array_merge($versionNos, $folderIds);
		if ($folderIds) {
			return array_unique(array_merge(self::getDocFolderTreeIds($folderIds), $docIds, $folderIds));
		}

		return array_unique(array_merge($docIds, $folderIds));
	}

	/**
	 * @param array $userList
	 * @param int   $projectId
	 *
	 * @return array
	 * 检查成员属性
	 */
	public static function checkPanelsUsers(array $userList, int $projectId): array
	{
		$checkUser = ProjectUserModel::query()->whereIn('user_id', $userList)->where(['project_id' => $projectId])->pluck('user_id')->toArray();
		if ($checkUser) {
			return self::checkOutsideUsers($checkUser);
		}

		return [];
	}

	public static function verify($verifyResult, $params)
	{
		if (!$verifyResult['result']) {
			return Status::VERIFY_ERROR;
		}
		//权限校验
		$project = ProjectModel::getInfo($params['project_id']);
		if (!$project) {
			return Status::PROJECT_NOT_EXIST;
		}
		$projectUser = ProjectUserModel::getInfo([
			'project_id' => $project->id,
			'user_id'    => getUserUid(),
		]);
		if (!$projectUser) {
			return Status::NOT_PROJECT_MEMBER;
		}

		return true;
	}

	/**
	 * @param int $projectId
	 * @param int $num
	 *
	 * @return Builder[]|Collection
	 */
	public static function getPanelsList(int $projectId, int $num = 40)
	{

		$panelsQuery = PanelsItemModel::query()->where('project_id', $projectId);

		return $panelsQuery->select(['id', 'name'])->orderBy('sort', 'ASC')->limit($num)->get();
	}

	/**
	 * @param $projectUser
	 * @param $projectInfo
	 *
	 * @return bool
	 * 移动权限验证
	 */
	public static function checkMovePermission($projectUser, $projectInfo): bool
	{
		return in_array($projectUser->type, self::MOVE_PERMISSION_MAP[ $projectInfo->panels_op_permission ], true);
	}

}
