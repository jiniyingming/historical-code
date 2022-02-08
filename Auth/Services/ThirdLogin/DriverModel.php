<?php

namespace Modules\Auth\Services\ThirdLogin;

/**
 * 第三方登录返回结果结构统一
 */
class DriverModel
{
	public $mobile;
	public $userName;
	public $avatar;
	public $unionId;
	public $openId;
	public $other;

	private $_name = [
		'mobile',
		'userName',
		'avatar',
		'openId',
		'unionId',
		'other',
	];
	private $_Map;

	public function __construct(array $mapping = [])
	{
		if ($mapping) {
			foreach ($this->_name as $key) {
				$this->{$key}       = $mapping[ $key ] ?? null;
				$this->_Map[ $key ] = $this->{$key};
			}
		}
	}

	public function toMap()
	{
		return $this->_Map;
	}
}