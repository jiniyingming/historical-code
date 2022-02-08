<?php

namespace Modules\Auth\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Entities\ThirdTicketInfoModel;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\ThirdDriver;

class CallTicketService
{
	protected $thirdTicketInfoModel;

	public function __construct(ThirdTicketInfoModel $dingTicketInfoModel)
	{
		$this->thirdTicketInfoModel = $dingTicketInfoModel;
	}

	public function setFlyBookTicket(array $params): bool
	{
		$return    = false;

		$ticketMap = ThirdDriver::channel(AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK, $params)->consoleTicket();

		if ($ticketMap) {
			$this->thirdTicketInfoModel->addLatestTicket(AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK, AuthConstMap::PUSH_TICKER_BY_TOKEN_WITH_FLY_BOOK, $ticketMap);
			$return = true;
		}

		return $return;
	}

}