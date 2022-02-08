<?php

namespace Modules\Panels\Services\Notice;

use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use Illuminate\Support\Facades\URL;
use Modules\Panels\Services\Execute\OperatorCardsService;
use Modules\Panels\Services\Factory\PushFactory;
use Modules\Panels\Services\PanelsBaseService;
use Modules\Panels\Services\PanelsService;
use Notice\Notice;

/**
 * 任务看板-任务通知
 */
class PanelsCardNotice extends OperatorCardsService implements NoticeInterface
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
		in_array($type, [
			PushFactory::DEAL_BY_CREATE_CARD,
			PushFactory::DEAL_BY_UPDATE_CARD,
		], true) && $this->contentMap = [
			'project' => $systemInfo->projectInfo->name,
			'card'    => $parameterData['cardInfo']->title,
			'item'    => $parameterData['itemInfo']->name,
			'url'     => URL::previous(),
			'card_id' => $parameterData['cardInfo']->id,
			'item_id' => $parameterData['cardInfo']->item_id,
		];

		parent::__construct(new PanelsItemModel(), new PanelsCardModel());

	}

	public function createNotice(): void
	{

		//--任务指派通知
		if ($this->parameterData['cardInfo']->leader > 0) {
			$this->userId !== $this->parameterData['cardInfo']->leader && Notice::createNotice($this->userId, $this->userInfo, $this->parameterData['cardInfo']->leader, $this->contentMap, $this->systemInfo->projectInfo->project_number, Notice::RECEIVER_TYPE_USER, Notice::TYPE_PROJECT_TASK_ASSIGN, $this->systemInfo->projectInfo->id);
		}
		$this->sendNoticeFunc(PanelsBaseService::PANELS_CARD_NOTICE, $this->systemInfo->projectInfo->project_number, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'item_id'        => $this->parameterData['cardInfo']->item_id,
			'project_id'     => $this->systemInfo->projectInfo->id,
		]);
	}

	public function updateNotice(): void
	{
		if ($this->parameterData['isSendNotice']) {
			if ($this->parameterData['isSendNoticeType'] === 1) {
				Notice::createNotice($this->userId, $this->userInfo, $this->parameterData['cardInfo']->leader, $this->contentMap, $this->systemInfo->projectInfo->project_number, Notice::RECEIVER_TYPE_USER, Notice::TYPE_PROJECT_TASK_ASSIGN, $this->systemInfo->projectInfo->project_number);
			} elseif ($this->parameterData['isSendNoticeType'] === 2) {
				//调整任务状态给项目组发站内信
				$_s                         = (int)$this->parameterData['cardInfo']->status;
				$this->contentMap['status'] = PanelsCardModel::$status[ $_s ];
				Notice::createNotice($this->userId, $this->userInfo, $this->systemInfo->projectInfo->id, $this->contentMap, $this->systemInfo->projectInfo->project_number, Notice::RECEIVER_TYPE_PROJECT, Notice::TYPE_PROJECT_TASK_STATUS, $this->systemInfo->projectInfo->project_number);
			}
		}

		$this->sendNoticeFunc(PanelsBaseService::PANELS_CARD_NOTICE, $this->systemInfo->projectInfo->project_number, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'item_id'        => $this->parameterData['cardInfo']->item_id,
			'project_id'     => $this->systemInfo->projectInfo->id,
		]);
		$this->sendNoticeFunc(PanelsBaseService::PANELS_CARD_INFO_NOTICE, $this->parameterData['cardInfo']->item_id, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'item_id'        => $this->parameterData['cardInfo']->item_id,
			'project_id'     => $this->systemInfo->projectInfo->id,
			'panels_card_id' => $this->parameterData['cardInfo']->id,
		]);
	}

	public function deleteNotice(): void
	{

		$this->sendNoticeFunc(PanelsBaseService::PANELS_CARD_NOTICE, $this->systemInfo->projectInfo->project_number, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'item_id'        => $this->parameterData['cardInfo']->item_id,
			'project_id'     => $this->systemInfo->projectInfo->id,
		]);
		$this->sendNoticeFunc(PanelsBaseService::PANELS_CARD_DELETED_NOTICE, $this->parameterData['cardInfo']->item_id, [
			'project_number' => $this->systemInfo->projectInfo->project_number,
			'item_id'        => $this->parameterData['cardInfo']->item_id,
			'project_id'     => $this->systemInfo->projectInfo->id,
			'panels_card_id' => $this->parameterData['cardInfo']->id,
		]);
	}

	public function sortNotice(): void
	{
	}

	private function sendNoticeFunc($type, $triggerId, $info): void
	{
		PanelsService::sendPanelsNotice($triggerId, $info, $type);
	}
}
