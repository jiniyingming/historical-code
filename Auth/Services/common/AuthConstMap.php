<?php

namespace Modules\Auth\Services\common;

use App\Models\Company\CompanyUserModel;
use Modules\Auth\Services\ThirdLogin\Baidu\BaiduDriver;
use Modules\Auth\Services\ThirdLogin\Dinging\DingingDriver;
use Modules\Auth\Services\ThirdLogin\FlyBook\FlyBookDriver;
use Modules\Auth\Services\ThirdLogin\ThirdDriver;

abstract class AuthConstMap
{
	public const LOGIN_DRIVER_BY_DING     = 'dinging';
	public const LOGIN_DRIVER_BY_BAIDU    = 'baidu';
	public const LOGIN_DRIVER_BY_FLY_BOOK = 'fly_book';

	public const PUSH_TICKER_BY_TOKEN_WITH_FLY_BOOK = 'app_ticket';
	/**
	 * 回调信息 存储
	 * 兼容 轮序采集数据使用
	 */
	public const PREFIX_LOGIN_REDIS      = 'login:%s:%s';
	public const PREFIX_LOGIN_JUMP_REDIS = 'loginJump:%s:%s';

	/**
	 * 登录类型
	 */
	public const    TYPE_NORMAL = 'normal';
	public const    TYPE_SHARE  = 'share';
	public const    TYPE_INVITE = 'invite';
	/**
	 * 使用 回传至后端发起回调动作 类型渠道
	 */
	public const CALLBACK_INFO_BY_JUMP = [
		self::LOGIN_DRIVER_BY_DING     => 'loginTmpCode',
		self::LOGIN_DRIVER_BY_FLY_BOOK => 'tmp_code',
	];

	/**
	 * 邀请分享身份集合
	 * 映射表
	 */
	public const TYPE_MAP = [
		self::TYPE_INVITE => CompanyUserModel::IDENTITY_OUT,
		self::TYPE_SHARE  => CompanyUserModel::IDENTITY_VISITOR,
	];

	/**
	 * 第三方应用
	 * 服务映射对象
	 */
	public const OPEN_THIRD_SERVICE_MAP = [
		self::LOGIN_DRIVER_BY_DING     => DingingDriver::class,
		self::LOGIN_DRIVER_BY_BAIDU    => BaiduDriver::class,
		self::LOGIN_DRIVER_BY_FLY_BOOK => FlyBookDriver::class,
	];

	/**
	 * 请求网盘用户详情数据
	 */
	public const REQUEST_BAIDU_PAN_USER_INFO = true;
	/**
	 * 第三方渠道需二次跳转
	 * 映射MAP
	 */
	public const CHANNEL_NAME_MAP = [
		self::LOGIN_DRIVER_BY_DING     => '钉钉',
		self::LOGIN_DRIVER_BY_BAIDU    => '百度网盘',
		self::LOGIN_DRIVER_BY_FLY_BOOK => '飞书',
	];

	/**
	 * 回调处理状态码
	 */
	public const CALLBACK_NORMAL_LOGIN_CODE             = 10000;
	public const CALLBACK_AUTH_ERROR_CODE               = 10001;
	public const CALLBACK_ILLEGAL_REQUEST_CODE          = 10002;
	public const CALLBACK_UNBIND_CODE                   = 10003;
	public const CALLBACK_ACCOUNT_NO_FIND_CODE          = 10004;
	public const CALLBACK_NOT_COMPANY_USER_CODE         = 10005;
	public const CALLBACK_COMPANY_USER_LOGIN_ERROR_CODE = 10006;
	public const CALLBACK_AUTH_BIND_ERROR               = 10007;
	public const CALLBACK_AUTH_BIND_SUCCESS             = 10008;

}