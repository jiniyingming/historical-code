<?php

namespace Modules\Panels\Services\OperatorLog;

use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use App\Services\OperateLogService;
use Modules\Panels\Services\Execute\OperatorCardsService;

/**
 * 任务看板-操作日志
 */
class PanelsOperatorLog extends OperatorCardsService implements OperatorLogInterface
{

	public $parameterData;
	public $userId;
	public $systemInfo;
	public $userInfo;

	public function __construct(int $type, array $parameterData, int $userId, object $systemInfo)
	{
		$this->parameterData = $parameterData;
		$this->userId        = $userId;
		$this->systemInfo    = $systemInfo;

		$this->userInfo = json_encode([
			'real_name' => $systemInfo->userInfo['nickname'],
			'avatar'    => $systemInfo->userInfo['avatar'],
		]);

		parent::__construct(new PanelsItemModel(), new PanelsCardModel());
	}

	public function createOperatorLog(): void
	{
		$contentInfo = ['panel' => $this->parameterData['panel']->name];
		$this->sendOperatorLogFunc($contentInfo, 'p_45', $this->parameterData['panel']->name, $this->parameterData['panel']->id);
	}

	public function updateOperatorLog(): void
	{
		$contentInfo = [
			'panel'     => $this->parameterData['old_panel'],
			'new_panel' => $this->parameterData['new_panel'],
		];
		$this->sendOperatorLogFunc($contentInfo, 'p_46', $this->parameterData['panel']->name, $this->parameterData['panel']->id);
	}

	public function deleteOperatorLog(): void
	{
		$contentInfo = [
			'panel' => $this->parameterData['panel']->name,
			'num'   => $this->parameterData['num'],
		];
		$this->sendOperatorLogFunc($contentInfo, 'p_47', $this->parameterData['panel']->name, $this->parameterData['panel']->id);
	}

	public function sortOperatorLog(): void
	{

	}

	private function sendOperatorLogFunc($contentInfo, $contentId, $resource, $child): void
	{
		$avatar = $userInfo['avatar'] ?? config('user.operate_logo_user_logo');
		OperateLogService::operateLog($this->systemInfo->projectInfo->project_number, (int)$this->systemInfo->userInfo['u_id'], $this->systemInfo->userInfo['nickname'], $avatar, $contentId, 'panels', '1', $resource, $contentInfo, 'project', $child, 0);
	}
}
