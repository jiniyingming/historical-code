<?php

namespace Modules\Auth\Services\ThirdLogin\FlyBook;

use Modules\Auth\Components\Aes;
use Modules\Auth\Components\HttpHelper;
use Modules\Auth\Entities\ThirdTicketInfoModel;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\DriverModel;
use Modules\Auth\Services\ThirdLogin\ThirdLoginInterface;
use function AlibabaCloud\Client\json;

class FlyBookDriver implements ThirdLoginInterface
{

	/**
	 * @var FlyBookDriver
	 */
	private static $_instance;
	/**
	 * @var array
	 */
	private $config;

	private function __construct()
	{

	}

	private const AUTH_CODE_URL = 'https://open.feishu.cn/open-apis/authen/v1/index?app_id=%s&redirect_uri=%s&response_type=code&state=%s';
	//	private const AUTH_CODE_URL = 'https://passport.feishu.cn/suite/passport/oauth/authorize?client_id=%s&redirect_uri=%s&response_type=code&state=%s';

	private const AUTH_TOKEN_URL         = 'https://passport.feishu.cn/suite/passport/oauth/token';
	private const AUTH_USER_INFO_URL     = 'https://passport.feishu.cn/suite/passport/oauth/userinfo';
	private const AUTH_APP_USER_INFO_URL = 'https://open.feishu.cn/open-apis/authen/v1/access_token';
	private const AUTH_APP_TOKEN_URL     = 'https://open.feishu.cn/open-apis/auth/v3/app_access_token';
	private const AUTH_PUSH_AGAIN_URL    = 'https://open.feishu.cn/open-apis/auth/v3/app_ticket/resend';

	private $accessToken;

	/**
	 * @param $config
	 *
	 * @return FlyBookDriver
	 */
	public static function client($config): FlyBookDriver
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
		$url['qr_url'] = sprintf(self::AUTH_CODE_URL, self::$_instance->config['app_id'], urlencode($redirect_url), $params['state']);

		return $url;
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function userInfo($params): DriverModel
	{

		if (!isset($params['code'])) {
			throw new AuthException('code not found');
		}

		$tokenInfo = $this->getToken($params['code']);
		if ($this->config['self_builder']) {
			$userInfo = $this->getUserInfo();
		} else {
			$userInfo = $this->getAPPUserInfo($params['code']);
		}
		$userInfo['tokenInfo'] = $tokenInfo;
		$data                  = [
			'userName' => $userInfo['name'] ?? '',
			'avatar'   => $userInfo['avatar_url'] ?? '',
			'unionId'  => $userInfo['union_id'],
			'openId'   => $userInfo['open_id'],
			'mobile'   => $userInfo['mobile'] ?? '',
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

		if ($this->config['self_builder']) {
			$query     = [
				'grant_type'    => 'authorization_code',
				'client_id'     => self::$_instance->config['app_id'],
				'client_secret' => self::$_instance->config['app_secret'],
				'code'          => $code,
				'code_verifier' => '',
				'redirect_uri'  => self::$_instance->config['redirect'] . '?channel=' . AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK,
			];
			$tokenInfo = HttpHelper::client()->post(self::AUTH_TOKEN_URL, $query);
			if (!isset($tokenInfo['access_token'])) {
				throw new AuthException('access_token not found');
			}
			$tokenInfo['is_ticket'] = false;

			$this->accessToken = $tokenInfo['access_token'];
		} else {
			$ticketInfo = (new ThirdTicketInfoModel())->getLatestTicket(AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK, AuthConstMap::PUSH_TICKER_BY_TOKEN_WITH_FLY_BOOK);

			if (!isset($ticketInfo['event']['app_ticket'])) {
				throw new AuthException('app_ticket not found');
			}
			$tokenInfo              = $this->getAppToken($ticketInfo['event']['app_ticket']);
			$tokenInfo['token']     = $tokenInfo['app_access_token'];
			$tokenInfo['is_ticket'] = true;
			$this->accessToken      = $tokenInfo['app_access_token'];
		}

		return $tokenInfo;
	}

	public function getAppToken($appTicket)
	{
		$query     = [
			'app_id'     => $this->config['app_id'],
			'app_secret' => $this->config['app_secret'],
			'app_ticket' => $appTicket,
		];
		$tokenInfo = HttpHelper::client()->post(self::AUTH_APP_TOKEN_URL, $query);
		if (!isset($tokenInfo['app_access_token'])) {
			throw new AuthException('app_access_token not found');
		}

		return $tokenInfo;
	}

	private function getAPPUserInfo($code)
	{
		$user = HttpHelper::client()->post(self::AUTH_APP_USER_INFO_URL, [
			'grant_type' => 'authorization_code',
			'code'       => $code
		], [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->accessToken,
			],
		]);

		if (!isset($user['data'])) {
			throw new AuthException('APP user not found');
		}

		return $user['data'];
	}

	/**
	 * @return array|mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function getUserInfo()
	{

		$user = HttpHelper::client()->get(self::AUTH_USER_INFO_URL, [], [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->accessToken,
			],
		]);
		if (isset($user['error'])) {
			throw new AuthException($user['error_description']);
		}

		return $user;
	}

	public function consoleTicket($params)
	{
		$fields = ['token', 'ts', 'event', 'uuid', 'type'];
		foreach ($fields as $key) {
			if (!isset($params[ $key ])) {
				throw new AuthException('Key Not Found');
			}
		}

		return $params;
		$encrypt        = $params['encrypt'];
		$aseExec        = new Aes($this->config['encrypt_key'], 'AES-256-CBC');
		$encrypt        = base64_decode($encrypt);
		$encryptedEvent = $aseExec->decrypt($encrypt);

		return json_decode($encryptedEvent, true);
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function noticePushAgain()
	{

		$query = [
			'app_id'     => $this->config['app_id'],
			'app_secret' => $this->config['app_secret'],
		];

		return HttpHelper::client()->post(self::AUTH_PUSH_AGAIN_URL, $query);
	}
}