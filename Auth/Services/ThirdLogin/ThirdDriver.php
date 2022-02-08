<?php

namespace Modules\Auth\Services\ThirdLogin;

use GuzzleHttp\Exception\GuzzleException;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\Baidu\BaiduDriver;
use Modules\Auth\Services\ThirdLogin\Dinging\DingingDriver;
use Modules\Auth\Services\ThirdLogin\FlyBook\FlyBookDriver;

/**
 * 第三方应用 唯一出入口
 */
class ThirdDriver
{

	private $config;
	private $params;
	/**
	 * @var DingingDriver|BaiduDriver|FlyBookDriver
	 */
	private $thirdDriver;

	/**
	 * @param string $channel
	 * @param array  $params
	 *
	 * @return ThirdDriver
	 */
	public static function channel(string $channel, array $params = []): ThirdDriver
	{
		if (!self::$_instance instanceof self) {
			self::$_instance = new self();
		}

		self::$_instance->params = $params;
		$driverObj               = AuthConstMap::OPEN_THIRD_SERVICE_MAP[ $channel ] ?? null;
		if (empty($driverObj)) {
			throw new AuthException('channel not found');
		}
		self::$_instance->thirdDriver = $driverObj;
		self::$_instance->config      = config('auth.driver.' . $channel);

		return self::$_instance;
	}

	final public function getConfig()
	{
		return $this->config;
	}

	private static $_instance;

	private function __construct()
	{

	}

	private function __clone()
	{
	}

	/**
	 * @return DriverModel
	 * @throws GuzzleException
	 */
	final public function output(): DriverModel
	{

		return $this->thirdDriver::client($this->config)->userInfo($this->params);
	}

	/**
	 * @throws \Exception
	 */
	final public function redirect(): array
	{
		return $this->thirdDriver::client($this->config)->redirect($this->params);
	}

	final public function consoleTicket()
	{
		return $this->thirdDriver::client($this->config)->consoleTicket($this->params);

	}

}
