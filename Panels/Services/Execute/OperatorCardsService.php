<?php

namespace Modules\Panels\Services\Execute;

use App\Models\PanelsCardModel;
use App\Models\UserInfoModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Panels\Services\Factory\PushFactory;
use Modules\Panels\Services\PanelsBaseService;
use Modules\Panels\Services\PanelsCardService;
use Modules\Panels\Services\PanelsService;
use Modules\Search\Services\Es\ProjectService;
use Modules\Search\Services\Es\SearchService;

/**
 * 任务看板 卡片数据处理
 */
class OperatorCardsService extends OperatorAbstract
{
	/**
	 * @param array  $params
	 * @param int    $userId
	 * @param object $systemInfo
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create(array $params, int $userId, object $systemInfo): array
	{
		//数据校验
		$itemData = $this->panelsItemModel::query()->where('id', $params['item_id'])->first();
		if (empty($itemData)) {
			return [false, [], '目标清单不存在或已被删除'];
		}
		[$checkStatus, $panelsCard, $_error] = $checkError = $this->checkCreateParams(...func_get_args());
		if (!$checkStatus) {
			return $checkError;
		}
		DB::connection('mysql_project')->beginTransaction();
		try {
			$panelsCard['sort'] = PanelsCardService::dealPanelsCardSort($params['item_id'], $systemInfo->projectInfo->id, $panelsCard['status'], isset($params['pre_sort']));
			$cardInfo           = $this->panelsCardModel::create($panelsCard);
			$this->incrementData($cardInfo->item_id);
			DB::connection('mysql_project')->commit();
			$cardDetail = PanelsCardService::getPanelCardDetail($cardInfo->id, $systemInfo->projectInfo->id);
		} catch (\Throwable $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, [], null];
		}
		$this->dealOther(PushFactory::DEAL_BY_CREATE_CARD, [
			'cardInfo' => $cardInfo,
			'itemInfo' => $itemData,
		], $userId, $systemInfo);

		return [true, $cardDetail, $_error];
	}

	/**
	 * @param array  $params
	 * @param int    $userId
	 * @param object $systemInfo
	 *
	 * @return array
	 * @throws Exception
	 */
	public function update(array $params, int $userId, object $systemInfo): array
	{
		$cardInfo = $this->panelsCardModel::where([
			'id'         => $params['id'],
			'project_id' => $systemInfo->projectInfo->id,
		])->first();
		if (empty($cardInfo)) {
			return [false, $cardInfo, null];
		}

		$itemInfo = $this->panelsItemModel::query()->where('id', $cardInfo->item_id)->first();
		[
			$cardInfo,
			$isSendNotice,
			$isSendNoticeType,
			$contentId,
			$contentInfo
		] = $this->checkUpdateParams($cardInfo, $params, $userId);

		DB::connection('mysql_project')->beginTransaction();
		try {
			if ($cardInfo->status === $this->panelsCardModel::COMPLETE) {
				$this->panelsCardModel::query()->where([
					'item_id'    => $cardInfo->item_id,
					'project_id' => $cardInfo->project_id,
				])->where('status', 3)->where('sort', '>=', 200)->increment('sort', PanelsBaseService::SORT_INCR_VAL);
				$cardInfo->sort = 200;
			}
			if (in_array($cardInfo->status, [
					$this->panelsCardModel::COMING,
					$this->panelsCardModel::NO_BEGIN,
				], true) && $cardInfo->getOriginal('status') === $this->panelsCardModel::COMPLETE) {
				$max = PanelsCardModel::query()->where([
					'item_id'    => $cardInfo->item_id,
					'project_id' => $cardInfo->project_id,
				])->whereIn('status', [1, 2])->orderBy('sort', 'desc')->first();
				if (empty($max)) {
					$cardInfo->sort = 1;
				} else {
					$cardInfo->sort = $max->sort + PanelsBaseService::SORT_INCR_VAL;
				}
			}
			$cardInfo->save();
			$this->incrementData($cardInfo->item_id, $cardInfo->id);
			DB::connection('mysql_project')->commit();
		} catch (\Throwable $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, [], null];
		}
		$this->dealOther(PushFactory::DEAL_BY_UPDATE_CARD, [
			'cardInfo'         => $cardInfo,
			'itemInfo'         => $itemInfo,
			'contentInfo'      => $contentInfo,
			'contentId'        => $contentId,
			'isSendNoticeType' => $isSendNoticeType,
			'isSendNotice'     => $isSendNotice,
		], $userId, $systemInfo);

		return [true, $cardInfo, null];
	}

	/**
	 * @param array  $params
	 * @param int    $userId
	 * @param object $systemInfo
	 *
	 * @return array
	 * @throws Exception
	 */
	public function delete(array $params, int $userId, object $systemInfo): array
	{
		$cardInfo = PanelsCardModel::where([
			'id'         => $params['id'],
			'project_id' => $systemInfo->projectInfo->id,
		])->first();
		if (empty($cardInfo)) {
			return [false, []];
		}
		DB::connection('mysql_project')->beginTransaction();
		try {
			//删除关联数据
			$cardInfo->doc()->detach();
			$this->panelsCardModel::query()->where('id', $params['id'])->delete();
			//排序
			$this->panelsCardModel::query()->where('sort', '>', $cardInfo->sort)->where([
				'project_id' => $systemInfo->projectInfo->id,
				'item_id'    => $cardInfo->item_id,
			])->decrement('sort');
			$this->incrementData($cardInfo->item_id, $cardInfo->id);
			DB::connection('mysql_project')->commit();
		} catch (\Throwable $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, [], null];
		}

		$this->dealOther(PushFactory::DEAL_BY_DELETE_CARD, [
			'contentInfo' => [],
			'cardInfo'    => $cardInfo,
		], $userId, $systemInfo);

		return [true, []];
	}

	/**
	 * @param array  $params
	 * @param int    $userId
	 * @param object $systemInfo
	 *
	 * @return array
	 * @throws Exception
	 *  任务拖拽排序
	 */
	public function sort(array $params, int $userId, object $systemInfo): array
	{
		$item_id         = (int)$params['item_id'];
		$card_id         = (int)$params['card_id'];
		$trigger_item_id = (int)$params['trigger_item_id'];
		$card_sort       = $params['pre_sort'];
		$res             = PanelsCardService::mvTaskGroup($item_id, $card_id, $trigger_item_id, $systemInfo->projectInfo, $card_sort);
		if ($res === false) {
			return [false, []];
		}

		$this->incrementData($trigger_item_id);

		return [true, $res];
	}

	/**
	 * @param int    $type
	 * @param array  $parameterData
	 * @param int    $userId
	 * @param object $systemInfo
	 *  关联业务逻辑统一处理
	 */
	protected function dealOther(int $type, array $parameterData, int $userId, object $systemInfo)
	{
		PushFactory::setChannel($type, func_get_args())->pushNotifyInfo()->pushLogInfo()->exec();
		switch ($type) {
			case PushFactory::DEAL_BY_CREATE_CARD:
				SearchService::addEsData($parameterData['cardInfo']->toArray(), ProjectService::TASK);
				break;
			case PushFactory::DEAL_BY_UPDATE_CARD:
				SearchService::updEsData([
					'id'       => $parameterData['cardInfo']->id,
					'type_id'  => 4,
					'group_id' => $systemInfo->projectInfo->id,
					'name'     => $parameterData['cardInfo']->title,
					'item_id'  => $parameterData['cardInfo']->item_id,
				]);
				break;
			case PushFactory::DEAL_BY_DELETE_CARD:
				ProjectService::delDoc($parameterData['cardInfo']->id, ProjectService::TASK);
				break;
			default:
				return;
		}
	}

	/**
	 * 成员限制数量
	 */
	private const CARD_MEMBER_NUMBER = 50;

	/**
	 * @param array  $params
	 * @param int    $userId
	 * @param object $systemInfo
	 *
	 * @return array
	 */
	private function checkCreateParams(array $params, int $userId, object $systemInfo): array
	{
		$member = '';
		if ($params['member'] ?? false) {
			if (count($params['member']) > self::CARD_MEMBER_NUMBER) {
				return [false, [], '成员数量至多50人'];
			}
			$member = PanelsService::checkPanelsUsers($params['member'], $systemInfo->projectInfo->id);
			if (count($member) !== count($params['member'])) {
				return [false, [], '选中成员中部分已不在项目中'];
			}
			$member = implode(',', $member);
		}
		$panelData = [
			'item_id'    => $params['item_id'],
			'project_id' => $systemInfo->projectInfo->id,
			'start_time' => $params['start_time'] ?? '',
			'deadline'   => $params['deadline'] ?? '',
			'title'      => $params['title'],
			'status'     => $params['status'] ?? 1,
			'leader'     => $params['leader'] ?? 0,
			'member'     => $member,
			'remark'     => $params['remark'] ?? '',
			'priority'   => $params['priority'] ?? 1,
			'creator'    => $userId,
		];

		return [true, $panelData, null];
	}

	/**
	 * @var array 操作日志对应更新操作 映射表
	 */
	private $operatorLogSignMap = [
		'deadline' => [
			'hasVal'  => 'p_55',
			'default' => 'p_57',
		],
		'priority' => [
			'hasVal'  => [
				1 => 'p_63',
				2 => 'p_62',
			],
			'default' => 'p_61',
		],
		'status'   => [
			'hasVal'  => [
				1 => 'p_59',
				2 => 'p_58',
			],
			'default' => 'p_60',
		],
		'leader'   => [
			'hasVal'  => [
				'self'  => 'p_53',
				'other' => 'p_52',
			],
			'default' => 'p_54',
		],
		'member'   => [
			'hasVal'  => [
				'add'    => 'p_64',
				'remove' => 'p_65',
			],
			'default' => 'p_65',
		],
		'remark'   => [
			'hasVal'  => 'p_66',
			'default' => 'p_67',
		],
	];

	/**
	 * @param       $cardInfo
	 * @param array $params
	 * @param int   $userId
	 *
	 * @return array
	 * 任务看板 更新 数据日志映射
	 */
	private function checkUpdateParams(&$cardInfo, array $params, int $userId): array
	{
		$isSendNotice     = false;//是否发送站内信
		$isSendNoticeType = 0;//任务指派=1;调整任务状态=2
		$contentId        = 'p_49';
		$contentInfo      = [];
		foreach ($this->operatorLogSignMap as $field => $value) {
			if (array_key_exists($field, $params)) {
				$cardInfo->$field = $params[ $field ];
				if ($cardInfo->$field !== $cardInfo->getOriginal($field)) {
					if ($field === 'leader') {
						!empty($cardInfo->$field) && $contentInfo = [
							'new_user' => UserInfoModel::select('user_id', 'real_name', 'avatar')->where('user_id', $cardInfo->$field)->first()->real_name,
						];
						if (empty($cardInfo->$field)) {
							$contentId = $value['default'];
						} elseif ($cardInfo->$field === $userId) {
							$contentId = $value['hasVal']['self'];
						} else {
							$isSendNotice     = true;
							$isSendNoticeType = 1;
							$contentId        = $value['hasVal']['other'];
						}
						continue;
					}
					if ($field === 'member') {
						if (empty($cardInfo->$field)) {
							$contentId = $value['default'];
						} else {
							$member = explode(',', $cardInfo->$field);
							if (count($member) > self::CARD_MEMBER_NUMBER) {
								sendErrorResponse('任务成员最多50人');
							}
							$originMember = explode(',', $cardInfo->getOriginal($field));

							if (substr_count($cardInfo->$field, ',') > substr_count($cardInfo->getOriginal($field), ',')) {
								$contentId           = $value['hasVal']['add'];
								$ids                 = array_diff($member, $originMember);
								$this->_memberStatus = [
									'type'    => 1,
									'members' => $ids,
									'cardId'  => $cardInfo->id,
									'itemId'  => $cardInfo->item_id,
								];
							} else {
								$contentId           = $value['hasVal']['remove'];
								$ids                 = array_diff($originMember, $member);
								$this->_memberStatus = [
									'type'    => 2,
									'members' => $ids,
									'cardId'  => $cardInfo->id,
									'itemId'  => $cardInfo->item_id,
								];
							}
							$ids && $contentInfo['bind'] = implode(',', UserInfoModel::whereIn('user_id', $ids)->pluck('real_name')->toArray());
						}
						continue;
					}
					if (!is_array($value['hasVal'])) {
						$contentId = empty($cardInfo->$field) ? $value['default'] : $value['hasVal'];
					} else {
						$contentId = $value['hasVal'][ $cardInfo->$field ] ?? $value['default'];
					}
					$field === 'deadline' && $contentInfo = ['end_time' => $cardInfo->$field];
					$field === 'status' && ($isSendNotice = true) && ($isSendNoticeType = 2);
					$field === 'remark' && $contentInfo = ['remark' => $cardInfo->$field];
					break;
				}
			}
		}
		$cardInfo->title = $params['title'];
		$operatorLog     = [];
		if ($cardInfo->title !== $cardInfo->getOriginal('title')) {
			$operatorLog = ['task_title' => $cardInfo->title];
		}
		if ($contentId === 'p_49' && !empty($operatorLog)) {
			$contentInfo = array_merge($operatorLog, $contentInfo);
		}

		return [
			$cardInfo,
			$isSendNotice,
			$isSendNoticeType,
			$contentId,
			$contentInfo,
		];
	}

	/**
	 * @var array 成员操作通知处理
	 */
	private $_memberStatus = [
		'type'    => 0,
		'members' => [],
	];

	/**
	 * 成员通知处理
	 * TODO 暂留
	 */
	private function sendTaskMemberNotice(): void
	{
		$data = $this->_memberStatus;
	}

	/**
	 * @param     $itemId
	 * @param int $cardId
	 * 增量更新标识数据
	 */
	private function incrementData($itemId, int $cardId = 0): void
	{
		$cardId && $this->panelsCardModel::query()->where('id', $cardId)->update(['updated_at' => date('Y-m-d H:i:s')]);
		$itemId && $this->panelsItemModel::query()->where('id', $itemId)->update(['updated_at' => date('Y-m-d H:i:s')]);
	}

}
