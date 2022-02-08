<?php

namespace Modules\Auth\Services\ThirdLogin\Baidu;

use Modules\Auth\Components\HttpHelper;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\DriverModel;
use Modules\Auth\Services\ThirdLogin\ThirdLoginInterface;

class BaiduDriver implements ThirdLoginInterface
{

	/**
	 * @var BaiduDriver
	 */
	private static $_instance;
	/**
	 * @var array
	 */
	private $config;

	private function __construct()
	{

	}

	private const AUTH_CODE_URL = 'https://openapi.baidu.com/oauth/2.0/authorize?response_type=code&client_id=%s&redirect_uri=%s&state=%s&scope=basic,netdisk,super_msg&display=tv&qrcode=1&force_login=1';

	private const AUTH_TOKEN_URL     = 'https://openapi.baidu.com/oauth/2.0/token';
	private const AUTH_USER_INFO_URL = 'https://openapi.baidu.com/rest/2.0/passport/users/getInfo';

	private const AUTH_AVATAR_URL    = 'https://himg.bdimg.com/sys/portrait/item/%s';
	private const AUTH_PAN_USER_INFO = 'https://pan.baidu.com/rest/2.0/xpan/nas';
	private $accessToken;
	private $refreshToken;

	/**
	 * @param $config
	 *
	 * @return BaiduDriver
	 */
	public static function client($config): BaiduDriver
	{
		if (!self::$_instance instanceof self) {
			self::$_instance = new self();
		}

		self::$_instance->config = $config;

		return self::$_instance;
	}

	public function redirect($params): array
	{
		$redirect_url  = self::$_instance->config['redirect'] . '?' . http_build_query($params['query']);
		$url['qr_url'] = sprintf(self::AUTH_CODE_URL, self::$_instance->config['app_key'], urlencode($redirect_url), $params['state']);

		return $url;
	}

	/**
	 * @param $params
	 *
	 * @return \Modules\Auth\Services\ThirdLogin\DriverModel
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function userInfo($params): DriverModel
	{
		if (!isset($params['code'])) {
			throw new AuthException('code not found');
		}
		$tokenInfo             = $this->getToken($params['code']);
		$userInfo              = $this->getUserInfo();
		$userInfo['tokenInfo'] = $tokenInfo;

		if (isset($userInfo['panInfo']['netdisk_name']) && !empty($userInfo['panInfo']['netdisk_name'])) {
			$userName = $userInfo['panInfo']['netdisk_name'];
		} elseif (isset($userInfo['panInfo']['baidu_name']) && !empty($userInfo['panInfo']['baidu_name'])) {
			$userName = $userInfo['panInfo']['baidu_name'];
		} else {
			$userName = $userInfo['username'];
		}

		$data = [
			'userName' => $userName,
			'avatar'   => $userInfo['panInfo']['avatar_url'] ?? (isset($userInfo['portrait']) ? sprintf(self::AUTH_AVATAR_URL, $userInfo['portrait']) : ''),
			'unionId'  => $userInfo['unionid'],
			'openId'   => $userInfo['openid'],
			'mobile'   => $userInfo['securemobile'] ?? '',
			'other'    => $userInfo,
		];

		return new DriverModel($data);
	}

	/**
	 * @param $code
	 *
	 * @return array|mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function getToken($code)
	{
		$query     = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => self::$_instance->config['app_key'],
			'client_secret' => self::$_instance->config['secret_key'],
			'redirect_uri'  => self::$_instance->config['redirect'] . '?channel=' . AuthConstMap::LOGIN_DRIVER_BY_BAIDU,
		];
		$tokenInfo = HttpHelper::client()->get(self::AUTH_TOKEN_URL, $query);
		if (!isset($tokenInfo['access_token'])) {
			throw new AuthException('access_token not found');
		}
		$this->accessToken = $tokenInfo['access_token'];

		return $tokenInfo;
	}

	/**
	 * @return array|mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function getUserInfo()
	{
		$query = [
			'access_token' => $this->accessToken,
			'get_unionid'  => 1,
		];

		$user = HttpHelper::client()->get(self::AUTH_USER_INFO_URL, $query);
		if (isset($user['error'])) {
			throw new AuthException($user['error_description']);
		}
		$query           = [
			'method'       => 'uinfo',
			'access_token' => $this->accessToken,
		];
		$user['panInfo'] = [];
		if (!isset($panInfo['error_code']) && AuthConstMap::REQUEST_BAIDU_PAN_USER_INFO) {
			$panInfo         = HttpHelper::client()->get(self::AUTH_PAN_USER_INFO, $query);
			$user['panInfo'] = $panInfo;
		}

		return $user;
	}
}