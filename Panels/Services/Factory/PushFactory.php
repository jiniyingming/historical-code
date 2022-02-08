<?php

namespace Modules\Panels\Services\Factory;

use Modules\Panels\Services\Notice\PanelsCardNotice;
use Modules\Panels\Services\Notice\PanelsNotice;
use Modules\Panels\Services\OperatorLog\PanelsCardOperatorLog;
use Modules\Panels\Services\OperatorLog\PanelsOperatorLog;

class PushFactory implements OperatorInformationFactory
{

	public const DEAL_BY_CREATE_CARD = 1;
	public const DEAL_BY_UPDATE_CARD = 2;
	public const DEAL_BY_DELETE_CARD = 3;
	public const DEAL_BY_SORT_CARD   = 4;

	public const DEAL_BY_CREATE_PANELS = 4;
	public const DEAL_BY_UPDATE_PANELS = 5;
	public const DEAL_BY_DELETE_PANELS = 6;
	public const DEAL_BY_SORT_PANELS   = 7;

	private $funcArgs;
	private $channel;
	private $binding;

	final public static function setChannel($channel, $params): PushFactory
	{
		if (!self::$_instance instanceof self) {
			self::$_instance = new self();
		}

		$objClass    = new \ReflectionClass(__CLASS__);
		$channelData = $objClass->getConstants();
		if (!in_array($channel, $channelData, true)) {
			throw new \RuntimeException('channel not found');
		}
		self::$_instance->channel  = $channel;
		self::$_instance->funcArgs = $params;

		return self::$_instance;
	}

	/**
	 * @return $this
	 * 推送通知
	 */
	final public function pushNotifyInfo(): PushFactory
	{
		$union_id = $this->getUnionId(__METHOD__);
		if ($union_id instanceof self) {
			return $union_id;
		}
		switch ($this->channel) {
			case self::DEAL_BY_CREATE_PANELS:
				$execObj = function () {
					(new PanelsNotice(...$this->funcArgs))->createNotice();
				};
				break;
			case self::DEAL_BY_UPDATE_PANELS:
				$execObj = function () {
					(new PanelsNotice(...$this->funcArgs))->updateNotice();
				};
				break;
			case self::DEAL_BY_DELETE_PANELS:
				$execObj = function () {
					(new PanelsNotice(...$this->funcArgs))->deleteNotice();
				};
				break;
			case self::DEAL_BY_SORT_PANELS:
				$execObj = function () {
					(new PanelsNotice(...$this->funcArgs))->sortNotice();
				};
				break;
			case self::DEAL_BY_CREATE_CARD:
				$execObj = function () {
					(new PanelsCardNotice(...$this->funcArgs))->createNotice();
				};
				break;
			case self::DEAL_BY_UPDATE_CARD:
				$execObj = function () {
					(new PanelsCardNotice(...$this->funcArgs))->updateNotice();
				};
				break;
			case self::DEAL_BY_DELETE_CARD:
				$execObj = function () {
					(new PanelsCardNotice(...$this->funcArgs))->deleteNotice();
				};
				break;
			default:
				return $this;
		}
		$this->binding[ $union_id ] = $execObj;

		return $this;
	}

	/**
	 * @return $this
	 * 推送操作日志
	 */
	final public function pushLogInfo(): PushFactory
	{
		$union_id = $this->getUnionId(__METHOD__);
		if ($union_id instanceof self) {
			return $union_id;
		}

		switch ($this->channel) {
			case self::DEAL_BY_CREATE_PANELS:
				$execObj = function () {
					(new PanelsOperatorLog(...$this->funcArgs))->createOperatorLog();
				};
				break;
			case self::DEAL_BY_UPDATE_PANELS:
				$execObj = function () {
					(new PanelsOperatorLog(...$this->funcArgs))->updateOperatorLog();
				};
				break;
			case self::DEAL_BY_DELETE_PANELS:
				$execObj = function () {
					(new PanelsOperatorLog(...$this->funcArgs))->deleteOperatorLog();
				};
				break;
			case self::DEAL_BY_CREATE_CARD:
				$execObj = function () {
					(new PanelsCardOperatorLog(...$this->funcArgs))->createOperatorLog();
				};
				break;
			case self::DEAL_BY_UPDATE_CARD:
				$execObj = function () {
					(new PanelsCardOperatorLog(...$this->funcArgs))->updateOperatorLog();
				};
				break;
			case self::DEAL_BY_DELETE_CARD:
				$execObj = function () {
					(new PanelsCardOperatorLog(...$this->funcArgs))->deleteOperatorLog();
				};
				break;
			default:
				return $this;
		}

		$this->binding[ $union_id ] = $execObj;

		return $this;
	}

	private function __construct()
	{
	}

	private static $_instance;

	private function __clone()
	{
	}

	final public function exec()
	{
		if ($this->binding) {
			return array_map(static function ($exec) {
				return $exec();
			}, $this->binding);
		}

		return false;
	}

	/**
	 * @param string $method
	 *
	 * @return $this|string
	 */
	private function getUnionId(string $method)
	{
		$union_id = $method . '|' . $this->channel;
		if (isset($this->binding[ $union_id ])) {
			return $this;
		}

		return $union_id;
	}
}