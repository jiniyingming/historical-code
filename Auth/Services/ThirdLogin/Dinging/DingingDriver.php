<?php

namespace Modules\Auth\Services\ThirdLogin\Dinging;

use Modules\Auth\Components\HttpHelper;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Services\common\CommonService;
use Modules\Auth\Services\ThirdLogin\Dinging\Parsing\DingCallbackCrypto;
use Modules\Auth\Services\ThirdLogin\DriverModel;
use Modules\Auth\Services\ThirdLogin\ThirdLoginInterface;
use AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Dingtalk;

class DingingDriver implements ThirdLoginInterface
{

	private const USER_INFO_URL   = 'https://oapi.dingtalk.com/sns/getuserinfo_bycode';
	private const REDIRECT_URL    = 'https://oapi.dingtalk.com/connect/qrconnect?appid=%s&response_type=code&scope=snsapi_login&state=%s&redirect_uri=%s';
	private const REDIRECT_URL_QR = 'https://oapi.dingtalk.com/connect/oauth2/sns_authorize?appid=%s&response_type=code&scope=snsapi_login&state=%s&redirect_uri=%s';
	/**
	 * @var DingingDriver
	 */
	private static $_instance;
	/**
	 * @var Dingtalk
	 */
	private $config;

	private function __construct()
	{

	}

	/**
	 * @param $config
	 *
	 * @return DingingDriver
	 */
	public static function client($config): DingingDriver
	{
		if (!self::$_instance instanceof self) {
			self::$_instance = new self();
		}

		self::$_instance->config = $config;

		return self::$_instance;
	}

	/**
	 * @throws \Exception
	 */
	public function redirect($params = []): array
	{
		$redirect_url = self::$_instance->config['redirect'] . '?' . http_build_query($params['query']);

		app()->isLocal() && $url['api_url'] = sprintf(self::REDIRECT_URL, self::$_instance->config['client_id'], $params['state'], urlencode($redirect_url));
		app()->isLocal() && $url['redirect_url'] = urlencode($redirect_url);
		$url['qr_url'] = sprintf(self::REDIRECT_URL_QR, self::$_instance->config['client_id'], $params['state'], urlencode($redirect_url));

		return $url;
	}

	public function userInfo($params): DriverModel
	{
		if (!isset($params['code'])) {
			throw new AuthException('code not found');
		}
		$query = [
			'accessKey' => $this->config['client_id'],
			'timestamp' => $timestamp = CommonService::getMillisecond(),
			'signature' => $this->getSignature($timestamp),
		];

		$url  = self::USER_INFO_URL . '?' . http_build_query($query);
		$body = [
			'tmp_auth_code' => $params['code'],
		];

		$response = HttpHelper::client()->post($url, $body);

		if ((int)$response['errcode'] !== 0) {
			throw new AuthException('Authentication failed');
		}

		$data = [
			'userName' => $response['user_info']['nick'],
			'unionId'  => $response['user_info']['unionid'],
			'openId'   => $response['user_info']['openid'],
			'other'    => $response['user_info'],
		];

		return new DriverModel($data);

	}

	/**
	 * @param $timestamp
	 *
	 * @return string
	 * 签名组装
	 */
	private function getSignature($timestamp): string
	{
		$s = hash_hmac('sha256', $timestamp, self::$_instance->config['client_secret'], true);

		return base64_encode($s);
	}

	/**
	 * @param object $data
	 *
	 * @return array|false
	 */
	public function consoleTicket(object $data)
	{
		try {
			$crypt        = new DingCallbackCrypto($this->config['token'], $this->config['secret'], $this->config['client_id']);
			$decryptedMsg = $crypt->getDecryptMsg($data->msg_signature, $data->timestamp, $data->nonce, $data->encrypt);
			$returnMap    = $crypt->getEncryptedMap("success");

			return [
				'decryptedMsg' => json_decode($decryptedMsg, true),
				'returnMap'    => json_decode($returnMap, true),
			];
		} catch (\Exception $e) {
			\Log::channel('thirdCall')->error('consoleTicket', [
				'params' => $data,
				'error'  => $e->getMessage(),
			]);
		}

		return false;
	}
}
