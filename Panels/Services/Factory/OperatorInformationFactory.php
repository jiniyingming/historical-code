<?php

namespace Modules\Panels\Services\Factory;

interface OperatorInformationFactory
{

	/**
	 * @return mixed
	 * 消息通知推送
	 */
	public function pushNotifyInfo();

	/**
	 * @return mixed
	 * 操作日志推送
	 */
	public function pushLogInfo();
}