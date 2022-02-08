<?php

namespace Modules\Panels\Services\Notice;

use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use Modules\Panels\Services\Execute\OperatorCardsService;
use Modules\Panels\Services\PanelsBaseService;
use Modules\Panels\Services\PanelsService;

/**
 * 任务看板-清单通知处理
 */
class PanelsNotice extends OperatorCardsService implements NoticeInterface
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

	public function createNotice(): void
	{
		$this->sendNoticeFunc(PanelsBaseService::PANELS_ITEM_NOTICE, $this->systemInfo->projectInfo->project_number, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'project_id'     => $this->systemInfo->projectInfo,
		]);
	}

	public function updateNotice(): void
	{
		$this->sendNoticeFunc(PanelsBaseService::PANELS_ITEM_NOTICE, $this->systemInfo->projectInfo->project_number, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'project_id'     => $this->systemInfo->projectInfo,
		]);
	}

	public function deleteNotice(): void
	{
		$this->sendNoticeFunc(PanelsBaseService::PANELS_ITEM_DELETE_NOTICE, $this->parameterData['panel']->id, [
			'panels_item_id' => $this->parameterData['panel']->id,
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'project_id'     => $this->systemInfo->projectInfo->id,
		]);
	}

	public function sortNotice(): void
	{
		$this->sendNoticeFunc(PanelsBaseService::PANELS_ITEM_NOTICE, $this->systemInfo->projectInfo->project_number, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'project_id'     => $this->systemInfo->projectInfo,
		]);
	}

	private function sendNoticeFunc($type, $triggerId, $info): void
	{
		PanelsService::sendPanelsNotice($triggerId, $info, $type);
	}
}
