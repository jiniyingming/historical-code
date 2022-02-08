<?php

namespace Modules\Panels\Services\OperatorLog;

use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use App\Services\OperateLogService;
use Modules\Panels\Services\Execute\OperatorCardsService;

/**
 * 任务看板-操作日志
 */
class PanelsCardOperatorLog extends OperatorCardsService implements OperatorLogInterface
{

	public $contentMap;
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
		$this->sendOperatorLogFunc($this->parameterData['cardInfo']->title, 'p_104', '');
	}

	public function updateOperatorLog(): void
	{
		$this->sendOperatorLogFunc($this->parameterData['contentInfo'], $this->parameterData['contentId'], $this->parameterData['cardInfo']->title, 0, $this->parameterData['cardInfo']->id);

	}

	public function deleteOperatorLog(): void
	{
		$this->sendOperatorLogFunc($this->parameterData['contentInfo'], 'p_50', $this->parameterData['cardInfo']->title, 0, $this->parameterData['cardInfo']->id);
		$this->sendOperatorLogFunc($this->parameterData['contentInfo'], 'p_103', $this->parameterData['cardInfo']->title, 0, 0, '');

	}

	public function sortOperatorLog(): void
	{
	}

	private function sendOperatorLogFunc($contentInfo, $contentId, $resource, int $child = 0, int $levelId = 0, string $type = 'panel_card'): void
	{
		$avatar = $userInfo['avatar'] ?? config('user.operate_logo_user_logo');
		OperateLogService::operateLog($this->systemInfo->projectInfo->project_number, (int)$this->systemInfo->userInfo['u_id'], $this->systemInfo->userInfo['nickname'], $avatar, $contentId, $type, '1', $resource, $contentInfo, 'project', $child, $levelId);
	}
}
