<?php

namespace Modules\Panels\Services\Execute;

use App\Models\PanelsItemModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Panels\Services\Factory\PushFactory;

/**
 * 任务看板 清单相关数据处理
 */
class OperatorPanelsService extends OperatorAbstract
{

	/**
	 * @throws Exception
	 */
	public function create(array $params, int $userId, object $systemInfo): array
	{
		DB::connection('mysql_project')->beginTransaction();
		try {
			$panelData = [
				'project_id' => $systemInfo->projectInfo->id,
				'name'       => $params['name'],
			];
			if ($params['pre_sort'] ?? false) {
				$panelData['sort'] = $this->dealPanelsSort($params['pre_sort'], $systemInfo->projectInfo->id);
			} else {
				$max               = $this->panelsItemModel::query()->where('project_id', $systemInfo->projectInfo->id)->orderByDesc('sort')->first()->sort ?? 0;
				$panelData['sort'] = $max + 1;
			}
			$panel             = $this->panelsItemModel::create($panelData);
			$panel->cards      = [];
			$panel->cards_num  = 0;
			$panel->finish_num = 0;
			DB::connection('mysql_project')->commit();
		} catch (Exception $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, []];
		}
		//--连带业务统一处理
		$this->dealOther(PushFactory::DEAL_BY_CREATE_PANELS, ['panel' => $panel], $userId, $systemInfo);

		return [true, $panel];
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
		DB::connection('mysql_project')->beginTransaction();
		try {
			$panel = $this->panelsItemModel::query()->where('id', $params['id'])->first();
			if (!$panel) {
				return [false, []];
			}
			$oldName     = $panel->name;
			$panel->name = $params['name'];
			$panel->save();
			DB::connection('mysql_project')->commit();
		} catch (\Throwable $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, []];
		}
		$this->dealOther(PushFactory::DEAL_BY_UPDATE_PANELS, [
			'panel'     => $panel,
			'old_panel' => $oldName,
			'new_panel' => $panel->name,
		], $userId, $systemInfo);

		return [true, $panel];

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
		DB::connection('mysql_project')->beginTransaction();
		try {
			$panel = $this->panelsItemModel::query()->where([
				'id'         => $params['id'],
				'project_id' => $systemInfo->projectInfo->id,
			])->first();
			if (empty($panel)) {
				return [false, []];
			}
			$num = $this->panelsCardModel::query()->where('item_id', $params['id'])->count();
			$this->panelsItemModel->deleteItem($params, $panel, $systemInfo->projectInfo->id);
			DB::connection('mysql_project')->commit();
		} catch (\Throwable $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, []];
		}
		$this->dealOther(PushFactory::DEAL_BY_DELETE_PANELS, [
			'panel' => $panel,
			'num'   => $num,
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
	 */
	public function sort(array $params, int $userId, object $systemInfo): array
	{
		DB::connection('mysql_project')->beginTransaction();
		try {
			$panel = $this->panelsItemModel::query()->where([
				'id'         => $params['item_id'],
				'project_id' => $systemInfo->projectInfo->id,
			])->first();
			if (empty($panel)) {
				return [false, []];
			}
			$panel->sort = $this->dealPanelsSort($params['pre_sort'], $systemInfo->projectInfo->id);
			$panel->save();
			DB::connection('mysql_project')->commit();
		} catch (\Throwable $e) {
			DB::connection('mysql_project')->rollBack();
			Log::info("File: " . $e->getFile() . " Line: " . $e->getLine() . " message: " . $e->getMessage());

			return [false, []];
		}
		$this->dealOther(PushFactory::DEAL_BY_SORT_PANELS, ['panel' => $panel], $userId, $systemInfo);

		return [true, []];
	}

	/**
	 * @param $preSort
	 * @param $projectId
	 *
	 * @return int
	 */
	private function dealPanelsSort($preSort, $projectId): int
	{
		$sort = $preSort + 1;
		PanelsItemModel::query()->where(['project_id' => $projectId])->where('sort', '>=', $sort)->increment('sort');

		return $sort;
	}

	/**
	 * @param int    $type
	 * @param array  $parameterData
	 * @param int    $userId
	 * @param object $systemInfo
	 *
	 */
	protected function dealOther(int $type, array $parameterData, int $userId, object $systemInfo)
	{
		PushFactory::setChannel($type, func_get_args())->pushNotifyInfo()->pushLogInfo()->exec();
	}
}
