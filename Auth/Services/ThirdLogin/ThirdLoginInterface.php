<?php

namespace Modules\Auth\Services\ThirdLogin;

interface ThirdLoginInterface
{

	/**
	 * @param $config
	 *
	 * @return mixed
	 * 单例实现对接类
	 */
	public static function client($config);

	/**
	 * @param $params
	 *
	 * @return array
	 * 跳转信息设置
	 */
	public function redirect($params): array;

	/**
	 * @param $params
	 *
	 * @return DriverModel
	 * 实现回调&取回用户信息 输出结构
	 */
	public function userInfo($params): DriverModel;
}
