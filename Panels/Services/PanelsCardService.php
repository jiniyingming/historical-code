<?php

namespace Modules\Panels\Services;

use App\Models\FileCoverExtModel;
use App\Models\PanelsCardDocModel;
use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use App\Models\ProjectDocModel;
use App\Models\ProjectModel;
use App\Models\UserInfoModel;
use App\Services\ProjectFileService;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Language\Status;

class PanelsCardService extends PanelsBaseService
{

	/**
	 * @param int        $item_id         目标 清单编号  to
	 * @param int        $card_id         卡片id
	 * @param int        $trigger_item_id 目标 清单编号 from
	 * @param object     $project         项目
	 * @param float|bool $card_sort       卡片顺序 上一个卡片排序值
	 *                                    拖拽移动
	 *
	 * @throws Exception
	 */
	public static function mvTaskGroup(int $item_id, int $card_id, int $trigger_item_id, object $project, $card_sort = false)
	{
		try {
			DB::connection('mysql_project')->beginTransaction();
			$card_exec = PanelsCardModel::query()->find($card_id);

			if (!$card_exec) {
				sendErrorResponse('任务已被删除', Status::PAMELS_DELED_CARD);
			}

			$existItem = PanelsItemModel::query()->where('id', $item_id)->first();
			if (!$existItem) {
				sendErrorResponse('任务清单已被删除', Status::PAMELS_DELED_ITEM);
			}

			if (($trigger_item_id !== $item_id) && PanelsCardModel::query()->where([
					'item_id'    => $item_id,
					'project_id' => $project->id,
				])->count() >= 200) {
				$itemName = $existItem->name ?? '';
				sendErrorResponse(sprintf('%s 任务清单已达上限（200个任务）', (self::substr($itemName, 0, 8))), Status::WARING_NOTICE);
			}
			$card_exec->item_id = $item_id;
			$newSortVal         = self::dealPanelsCardSort($card_exec->item_id, $project->id, $card_exec->status, $card_sort);

			$card_exec->sort       = $newSortVal;
			$card_exec->updated_at = date('Y-m-d H:i:s');
			if (PanelsCardModel::query()->where([
					'item_id'    => $item_id,
					'sort'       => $newSortVal,
					'project_id' => $project->id
				])->count() > 0) {
				PanelsCardModel::query()->where([
					'item_id'    => $item_id,
					'project_id' => $project->id
				])->where('sort', '>=', $newSortVal)->increment('sort', self::SORT_INCR_VAL);
			}
			$card_exec->save();
			PanelsItemModel::query()->whereIn('id', [
				$item_id,
				$trigger_item_id
			])->update(['updated_at' => date('Y-m-d H:i:s')]);

			$trigger_item_id !== $item_id && PanelsService::sendPanelsNotice(//--卡片详情页
				$trigger_item_id, [
				'project_number' => $project->project_number,
				'item_id'        => $trigger_item_id,
				'panels_card_id' => $card_id,
				'project_id'     => $project->id
			], self::PANELS_CARD_DELETED_NOTICE);
			DB::connection('mysql_project')->commit();

			$trigger_item_id !== $item_id && PanelsService::sendPanelsNotice(//--卡片详情页
				$trigger_item_id, [
				'project_number' => $project->project_number,
				'item_id'        => $trigger_item_id,
				'panels_card_id' => $card_id,
				'project_id'     => $project->id,
			], self::PANELS_CARD_DELETED_NOTICE);
			PanelsService::sendPanelsNotice(//--卡片列表页
				$project->project_number, [
				'project_number' => $project->project_number,
				'item_id'        => $item_id,
				'project_id'     => $project->id,
			], self::PANELS_CARD_NOTICE);

			return PanelsCardModel::query()->where([
				'item_id'    => $card_exec->item_id,
				'project_id' => $project->id,
			])->select('id as card_id', 'sort')->orderBy('sort')->limit(200)->get()->toArray();

		} catch (Exception $e) {
			DB::connection('mysql_project')->rollBack();
			Log::error('mvTaskGroup', [
				'error'  => $e->getMessage(),
				'params' => func_get_args(),
			]);
			sendErrorResponse(null, Status::WARING_NOTICE);

			return false;
		}
	}

	/**
	 * @param int        $itemId
	 * @param int        $projectId
	 * @param int        $status
	 * @param bool|float $preSort
	 *
	 * @return float
	 * 处理任务面板排序
	 */
	public static function dealPanelsCardSort(int $itemId, int $projectId, int $status, $preSort = false)
	{

		$obj = PanelsCardModel::query()->select('sort', 'status')->where([
			'item_id'    => $itemId,
			'project_id' => $projectId
		]);

		//---创建至首位
		if ($preSort === true || $preSort === 'true') {
			if ($status !== 3) {
				$map       = $obj->whereIn('status', [1, 2])->orderBy('sort')->first();
				$firstSort = $map->sort ?? 0;

				return round($firstSort - self::SORT_INCR_VAL, 4);
			}

			$map       = $obj->where('status', 3)->orderBy('sort')->first();
			$firstSort = $map->sort ?? 200;

			return round($firstSort - self::SORT_INCR_VAL, 4);

		}
		//--拖拽非已完成类型
		if ($status !== 3) {
			if ($preSort) {
				$check = clone $obj;
				$map   = $check->where(['sort' => $preSort, 'status' => 3])->orderBy('sort', 'asc')->first();
				if ($map) {
					$preSort = false;
				} else {
					return round($preSort + self::SORT_INCR_VAL, 4);
				}
			}
			if ($preSort === false) {
				$map       = $obj->whereIn('status', [1, 2])->orderBy('sort', 'desc')->first();
				$firstSort = $map->sort ?? 0;

				return $firstSort + self::SORT_INCR_VAL;
			}
			$map       = $obj->whereIn('status', [1, 2])->orderBy('sort')->first();
			$firstSort = $map->sort ?? 0;

			return round($firstSort - self::SORT_INCR_VAL, 4);
		}

		$lastSort = clone $obj;

		if ($preSort >= ($lastSort->where('status', 3)->orderBy('sort')->first()->sort ?? 200)) {
			return round($preSort + self::SORT_INCR_VAL, 4);
		}
		if ($preSort === false) {
			$map       = $obj->where('status', 3)->orderBy('sort', 'desc')->first();
			$firstSort = $map->sort ?? 200;

			return round($firstSort + self::SORT_INCR_VAL, 4);
		}
		$map      = $obj->where('status', 3)->orderBy('sort')->first();
		$lastSort = $map->sort ?? 200;
		$preSort >= $lastSort && $lastSort = $preSort;

		return round($lastSort - self::SORT_INCR_VAL, 4);
	}

	/**
	 * @param $cardId
	 * @param $project
	 *
	 * @return array|mixed
	 * 任务看板 任务详情页
	 */
	public static function getPanelCardDetail($cardId, $project)
	{
		$userId = getUserUid();
		//处理数据
		$card = PanelsCardModel::query()->where('id', $cardId)->first();
		if (!$card) {
			return [];
		}
		$card = $card->toArray();

		$card['bind'] = [];
		$docIdsMap    = PanelsCardDocModel::query()->where('card_id', $cardId)->orderBy('id', 'desc')->pluck('doc_id')->toArray();

		$fileMap = false;
		$docIdsMap && $fileMap = ProjectFileService::queryFileList($project->id, $userId, [
			'searchByIds' => $docIdsMap,
			'isAll'       => true,
		], false);
		$card['bind_docs'] = $docIdsMap;
		if ($fileMap) {
			$fileMap      = array_column($fileMap->toArray(), null, 'id');
			$card['bind'] = array_values(array_filter(array_map(static function ($val) use ($fileMap) {
				return $fileMap[ $val ] ?? [];
			}, $docIdsMap)));

		}

		//处理member和bind数据
		return self::docHandle([$card], $project, false)[0] ?? [];
	}

	/**
	 * @param      $cards
	 * @param      $project
	 * 任务卡片结构体处理
	 * @param bool $showComment
	 *
	 * @return mixed
	 */
	public static function docHandle($cards, $project, bool $showComment = true, $showBindDesc = true)
	{
		$usersInfo = [];
		if ($cards) {
			$cardData = is_object($cards) ? $cards->toArray() : $cards;
			$userIds  = array_filter(array_unique(array_column($cardData, 'leader')));
			collect(array_column($cardData, 'bind'))->map(function ($val) use (&$userIds) {
				$userIds = array_merge($userIds, array_column($val, 'user_id'));
			});

			$usersInfo = UserInfoModel::query()->select('user_id', 'real_name', 'avatar')->whereIn('user_id', array_unique(array_filter($userIds)))->get();
			$usersInfo && $usersInfo = array_column($usersInfo->toArray(), null, 'user_id');
		}
		$extMap = FileCoverExtModel::query()->limit(200)->pluck('ext')->toArray();

		foreach ($cards as &$card) {

			if (!is_object($card)) {
				$card = (object)$card;
			} else {
				$card = (object)$card->toArray();
			}

			$card->project_id     = $card->bind[0]['project_id'] ?? '';
			$card->bindNum        = 0;
			$card->cover_img_thum = '';
			$card->cover_img      = '';
			$card->ext            = '';
			$card->type           = '';
			$card->file_type      = 'other';

			if ($card->bind ?? false) {
				$list                 = PanelsService::processList($card->bind, $project, $usersInfo, $extMap, $showComment);
				$card->bind           = $list;
				$card->bindNum        = count($card->bind);
				$card->cover_img_thum = $card->bind[0]['cover_img_thum'] ?? '';
				$card->cover_img      = $card->bind[0]['cover_img'] ?? '';
				$card->ext            = $card->bind[0]['ext'] ?? '';
				$card->type           = $card->bind[0]['type'] ?? '';
				$card->file_type      = $card->bind[0]['file_type'] ?? '';
			}
			if (!isset($card->bind_docs)) {
				$card->bind_docs = array();
			}
			if ($card->member) {
				$memberIds        = explode(',', $card->member);
				$users            = UserInfoModel::query()->select('user_id', 'real_name', 'avatar')->whereIn('user_id', $memberIds)->get();
				$card->memberInfo = $users;
			}
			if ($card->leader) {
				$card->leaderInfo = $usersInfo[ $card->leader ] ?? [];
			}
			if (!$showBindDesc) {
				$card->bind = [];
			}
		}

		return $cards;
	}

	/**
	 * @param $params
	 * @param $projectId
	 *
	 * @return array
	 * 增量处理
	 */
	public static function getCardList($params, $projectId): array
	{
		$itemId               = $params['item_id'];
		$cardId               = $params['page_id'] ?? 0;
		$updatedTime          = $params['updated'] ?? false;
		$num                  = $params['num'] ?? 30;
		$params['project_id'] = $projectId;
		$query                = PanelsItemModel::setItemListFilter($params, PanelsCardModel::query(), [
			'priority',
			'leader',
			'member',
			'item_id',
			'deadline',
		]);
		if ($cardId > 0) {
			$card  = PanelsCardModel::query()->find($cardId);
			$query = $query->where('sort', '>', $card->sort);
		} else {
			if (!$updatedTime) {
				return [];
			}
		}
		//--增量更新
		$query = $query->when($updatedTime, function ($query, $updateTime) use (&$num) {
			--$updateTime;
			$updatedAt = date('Y-m-d H:i:s', $updateTime);
			$query->where('updated_at', '>=', $updatedAt);
		});
		$updatedTime && $num = 100;
		$cardList = $query->limit($num)->get();

		if ($cardList) {
			$panelsCardData[ $itemId ] = $cardList->toArray();

			return self::getPanelsCardList($panelsCardData, $projectId, getUserUid())[ $itemId ] ?? [];
		}

		return [];

	}

	public static $calendarMax = 3;

	/**
	 * 任务看板 日历模式
	 *
	 * @param int   $projectId    项目ID 主键
	 * @param array $filterParams 年 year 月 month 日 day 搜索内容 search_data 负责人
	 *                            leader 优先级 priority 状态 status  增量 updated 页码
	 *                            page_no 每页数量 page_size
	 *
	 * @throws Exception
	 */
	public static function searchCardListByCalendar(int $projectId, array $filterParams): array
	{

		$filterParams = array_filter($filterParams);

		$query = PanelsCardModel::query()->select([
			'title',
			'priority',
			'status',
			'leader',
			'deadline',
			'item_id',
			'id',
		])->where(['project_id' => $projectId])->orderBy('deadline')->orderBy('created_at');

		return self::dealQueryFilter($query, $filterParams, static function ($dataMap) use ($filterParams) {
			$periodDate = static function () {
				$startTime = self::$calendarStartTime;
				$endTime   = self::$calendarEndTime;
				while ($startTime <= $endTime) {
					yield date('Y-m-d', $startTime);
					$startTime = strtotime('+1 day', $startTime);
				}
			};
			$updatedAt  = $filterParams['updated'] ?? 0;
			//--日历面板 每个日期块限制数量
			$calendarMax = $filterParams['calendar_max'] ?? self::$calendarMax;
			$pagePass    = false;

			if (isset($filterParams['page_no'], $filterParams['page_size'])) {
				$calendarMax = $filterParams['page_size'];
			} else {
				$pagePass = true;
			}
			self::$calendarMax = $calendarMax;
			$overNumMap        = [];
			$pass              = $pagePass || $updatedAt <= 0;
			$map               = [];
			$leaders           = [];

			collect($dataMap)->map(function ($item) use ($calendarMax, $pass, &$overNumMap, &$map, &$leaders) {
				$date = date('Y-m-d', $item['deadline']);
				isset($overNumMap[ $date ]) ? ++$overNumMap[ $date ] : $overNumMap[ $date ] = 1;
				if ($pass && (isset($map[ $date ]) && count($map[ $date ]) >= $calendarMax)) {
					return;
				}
				$item = $item->toArray();

				$item['date']        = $date;
				$item['hour_format'] = date('H:i', $item['deadline']);
				$map[ $date ][]      = $item;
				$item['leader'] > 0 && $leaders[] = $item['leader'];
			});

			$compare = static function ($x, $y) {
				return ($x > $y) ? 'LAST' : ($x === $y ? 'NOW' : 'NEXT');
			};

			$leadersInfo = UserInfoModel::query()->select('user_id', 'real_name', 'avatar')->whereIn('user_id', array_unique($leaders))->get()->toArray();

			$leadersInfo && $leadersInfo = array_column($leadersInfo, null, 'user_id');
			$leaders && $map && $map = collect($map)->map(static function ($val) use ($leadersInfo) {
				return collect($val)->map(function ($item) use ($leadersInfo) {
					isset($leadersInfo[ $item['leader'] ]) && $item['leader_info'] = $leadersInfo[ $item['leader'] ];

					return $item;
				});
			});

			return collect($periodDate())->map(function ($date) use ($map, $filterParams, $compare, $overNumMap, $calendarMax) {
				$year     = (int)$filterParams['year'];
				$month    = (int)$filterParams['month'];
				$nowYear  = (int)date('Y', strtotime($date));
				$nowMonth = (int)date('m', strtotime($date));
				if (($dateType = $compare($year, $nowYear)) === 'NOW') {
					$dateType = $compare($month, $nowMonth);
				}
				$overTimes = 0;

				if (isset($overNumMap[ $date ])) {
					$overTimes = ($n = $overNumMap[ $date ] - $calendarMax) < 0 ? 0 : $n;
				}

				return [
					'module_date' => $date,
					'date_type'   => $dateType,
					'over_num'    => $overTimes,
					'data_map'    => $map[ $date ] ?? [],
				];
			})->toArray();
		});
	}

	/**
	 * @param Builder      $query
	 * @param array        $filterParams
	 * @param Closure|null $func
	 *
	 * @return array
	 * @throws Exception
	 * 筛选条件处理
	 */
	private static function dealQueryFilter(Builder $query, array $filterParams, Closure $func = null): array
	{
		$updatedTime = $filterParams['updated'] ?? 0;
		if ($filterParams['search_data'] ?? false) {
			$query->where('title', 'like', '%' . $filterParams['search_data'] . '%');
		}
		$filterField = ['leader', 'priority', 'status'];
		foreach ($filterField as $field) {
			if ($filterParams[ $field ] ?? false) {
				if ($field === 'leader' && $filterParams[ $field ] <= 0) {
					$filterParams[ $field ] = 0;
				}
				$query->where($field, $filterParams[ $field ]);
			}
		}
		$filterDay = $filterParams['day'] ?? 0;
		[$startTime, $endTime] = self::setDateFormat($filterParams['year'], $filterParams['month'], $filterDay);

		self::$calendarStartTime = $startTime;
		self::$calendarEndTime   = $endTime;
		$query->where('deadline', '>=', $startTime);
		$query->where('deadline', '<=', $endTime);
		$query->when($updatedTime, function ($query, $updateTime) {
			--$updateTime;
			$updatedAt = date('Y-m-d H:i:s', $updateTime);
			$query->where('updated_at', '>=', $updatedAt);
		});

		$pageInfo = [];
		if (isset($filterParams['page_no'], $filterParams['page_size']) && $filterDay > 0) {
			$page     = (int)($filterParams['page_no'] < 1 ? 1 : $filterParams['page_no']);
			$pageSize = (int)($filterParams['page_size'] < 1 ? 1 : $filterParams['page_size']);
			$pageInfo = ['limit' => $pageSize, 'page' => $page];
		}

		return self::pageRowsReturn($query, $pageInfo, $func);
	}

	/**
	 * @var string 查询月起始时间
	 */
	private static $calendarStartTime;
	private static $calendarEndTime;

	/**
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 *
	 * @return array
	 * 日历视图 时间组装
	 */
	private static function setDateFormat(int $year, int $month, int $day = 0): array
	{

		$formatDate = static function ($year, $month, $day, $isStart) {
			$hourFormat = $isStart ? '00:00:00' : '23:59:59';

			return strtotime(sprintf('%d-%d-%d %s', $year, $month, $day, $hourFormat));
		};
		if ($day > 0) {
			return [
				$formatDate($year, $month, $day, true),
				$formatDate($year, $month, $day, false),
			];
		}
		$monthFirstDay = strtotime(sprintf('%d-%d-%d 00:00:00', $year, $month, 1));

		$monthLastDay = strtotime(date("Y-m-d 23:59:59", strtotime(sprintf('%s +1 month -1 day', date('Y-m-d', $monthFirstDay)))));
		$nowWeek      = date('w', $monthFirstDay);
		$dayNum       = ceil(($monthLastDay - $monthFirstDay) / 86400);

		$calendarFirst = 7 - (7 - $nowWeek);

		$calendarLast = 42 - ($dayNum + $calendarFirst);

		return [
			$monthFirstDay - ($calendarFirst * 86400),
			$monthLastDay + ($calendarLast * 86400),
		];
	}

	/**
	 * @param     $panelsCardData
	 * @param     $projectId
	 * @param int $userId
	 *
	 * @return array
	 * 获取任务卡片详情
	 */
	public static function getPanelsCardList($panelsCardData, $projectId, int $userId = 0): array
	{

		$panelsCardIds = [];
		array_map(static function ($val) use (&$panelsCardIds) {
			$panelsCardIds = array_merge($panelsCardIds, array_column((is_object($val) ? $val->toArray() : $val), 'id'));
		}, $panelsCardData);

		$fileMap    = false;
		$docIds     = [];
		$cardDocMap = [];

		if ($panelsCardIds) {
			//TODO 待优化 易发生数据过载
			$docIdsMap = PanelsCardDocModel::query()->select([
				'card_id',
				'doc_id',
			])->whereIn('card_id', array_unique($panelsCardIds))->orderBy('id', 'desc')->get();
			foreach ($docIdsMap as $row) {
				if (!isset($docIds[ $row->card_id ])) {
					$docIds[ $row->card_id ] = $row->doc_id;
				}
				$cardDocMap[ $row->card_id ][] = $row->doc_id;
			}

			$docIds && $fileMap = ProjectFileService::queryFileList($projectId, $userId, [
				'searchByIds' => $docIds,
				'isAll'       => true,
				'isSort'      => false,
			], false);
			$fileMap && $fileMap = array_column($fileMap->toArray(), null, 'id');
		}

		return collect($panelsCardData)->map(function (&$cards) use ($fileMap, $docIds, $cardDocMap) {
			return collect($cards)->map(function (&$item) use ($fileMap, $docIds, $cardDocMap) {
				$item['bind'] = [];
				if ($docIds && $fileMap && ($docIds[ $item['id'] ] ?? false) && ($fileMap[ $docIds[ $item['id'] ] ] ?? false)) {
					$item['bind'][] = $fileMap[ $docIds[ $item['id'] ] ];
				}
				$item['bind_docs'] = $cardDocMap[ $item['id'] ] ?? [];

				return $item;
			});
		})->toArray();
	}

}
