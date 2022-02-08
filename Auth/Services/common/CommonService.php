<?php

namespace Modules\Auth\Services\common;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Modules\Auth\Exceptions\AuthException;

abstract class CommonService extends AuthConstMap
{

	/**
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	protected function rememberCallStatus($channel, $state, array $data, $key = self::PREFIX_LOGIN_REDIS): array
	{
		Redis::setex(sprintf($key, $channel, $state), 3600, json_encode($data));

		return $data;
	}

	/**
	 * @param        $channel
	 * @param        $state
	 * @param string $key
	 *
	 * @return array
	 */
	protected function getCallStatus($channel, $state, string $key = self::PREFIX_LOGIN_REDIS): array
	{
		$data = Redis::get(sprintf($key, $channel, $state));
		if (!$data) {
			return [];
		}

		return json_decode($data, true);
	}

	protected function clearCallStatus($channel, $state, $key = self::PREFIX_LOGIN_REDIS): void
	{
		Redis::expire(sprintf($key, $channel, $state), 0);
	}

	/**
	 * @param        $channel
	 * @param        $companySign
	 * @param        $platform
	 * @param false  $isBind
	 * @param string $type
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function encryptCode($channel, $companySign, $platform, bool $isBind = false, string $type = 'normal'): string
	{
		$token = static function () {
			$str = sha1(md5(uniqid(md5(microtime(true)), true))); //生成一个不会重复的字符串

			return substr($str, 15, 5) . random_int(10000, 99999);
		};

		$data = openssl_encrypt(json_encode([
			$companySign,
			$platform,
			$isBind,
			$type,
			$token(),
		]), 'AES-128-ECB', SALT . $channel, OPENSSL_RAW_DATA);

		return $this->replaceSpecialSign(base64_encode($data), true);
	}

	private function replaceSpecialSign(string $key, bool $isEncrypted = false): string
	{
		foreach (self::SPECIAL_SIGN as $i => $sign) {
			$str = $i . 'RsS';
			$key = $isEncrypted ? str_replace($sign, $str, $key) : str_replace($str, $sign, $key);
		}

		return $key;
	}

	private const SPECIAL_SIGN = [
		'+',
		'-',
		'*',
		'/',
		'?',
		'#',
		'=',
		'.',
		' ',
		'&',
	];

	/**
	 * @param $channel
	 * @param $code
	 *
	 * @return false|string
	 */
	protected function decryptCode($channel, $code)
	{
		$code      = $this->replaceSpecialSign($code, false);
		$decrypted = openssl_decrypt(base64_decode($code), 'AES-128-ECB', SALT . $channel, OPENSSL_RAW_DATA);
		if (!$decrypted) {
			return false;
		}
		$res = json_decode($decrypted, true);
		if (count($res) !== 5) {
			throw new AuthException('非法参数');
		}
		unset($res[4]);

		return $res;
	}

	/**
	 * @return false|string
	 *   毫秒级时间戳
	 */
	public static function getMillisecond()
	{
		[$msecs, $sec] = explode(' ', microtime());
		$meantime = (float)sprintf('%.0f', ((float)$msecs + (float)$sec) * 1000);

		return substr($meantime, 0, 13);
	}

	/**
	 * @param string $account
	 * @param string $emailField
	 * @param string $phoneField
	 *
	 * @return array
	 */
	protected function checkAccountType(string $account, string $emailField = 'email', string $phoneField = 'phone'): array
	{
		if (strpos($account, '@') !== false) {
			return [$emailField => $account];
		}

		return [$phoneField => $account];
	}

	protected function checkCaptchaStatus($phoneOrEmail, $code): bool
	{
		return DB::table('xy_captcha_log')->where([
			'mobile' => $phoneOrEmail,
			'code'   => getCodeString($code),
			'status' => 0,
		])->where('over_time', '>', time())->exists();
	}

}
