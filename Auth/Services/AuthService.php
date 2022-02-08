<?php

namespace Modules\Auth\Services;

use App\Models\Company\CompanyUserModel;
use App\Services\UserService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Language\Status;
use Modules\Auth\Components\HttpHelper;
use Modules\Auth\Entities\ThirdLoginInfoModel;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\ThirdDriver;
use Psr\SimpleCache\InvalidArgumentException;

class AuthService extends LoginService
{
	protected $thirdLoginInfoModel;

	public function __construct(ThirdLoginInfoModel $thirdLoginInfoModel)
	{
		$this->thirdLoginInfoModel = $thirdLoginInfoModel;
	}

	/**
	 * @param $params
	 *
	 * @return array
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	public function loginByThird($params): array
	{
		[
			$companySign,
			$platform,
			$isBind,
			$type,
		] = $this->decryptCode($params['channel'], $params['state']);

		try {

			$object = ThirdDriver::channel($params['channel'], $params)->output();
		} catch (Exception $e) {

			Log::channel('thirdCall')->error('thirdBack loginByThird-Error', [
				'input' => $params,
				'error' => $e->getMessage(),
			]);

			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => self::CALLBACK_AUTH_ERROR_CODE,
				'state'         => $params['state'],
				'is_register'   => false,
				'success'       => false,
				'error'         => true,
				'data'          => [],
				'msg'           => '认证失败',
			]);
		}
		//--绑定账号
		if ($isBind) {
			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'third_map' => $object->toMap(),
			]);
		}

		if ($companySign === false) {
			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => self::CALLBACK_ILLEGAL_REQUEST_CODE,
				'state'         => $params['state'],
				'is_register'   => false,
				'success'       => false,
				'error'         => true,
				'data'          => [],
				'msg'           => '非法请求',
			]);
		}

		$registered = $this->thirdLoginInfoModel->getThirdRegistered($params['channel'], $object->unionId);
		if (!$registered) {//---未绑定 前往绑定
			//--手机号存在
			if (!empty($object->mobile)) {
				try {
					$params['platform']   = $platform;
					$params['unique']     = $object->unionId;
					$params['account']    = $object->mobile;
					$params['is_company'] = $companySign;
					$params['nickname']   = $object->userName;
					$params['third_info'] = $object->toMap();

					return $this->rememberCallStatus($params['channel'], $params['state'], [
						'callback_code' => self::CALLBACK_AUTH_BIND_SUCCESS,
						'state'         => $params['state'],
						'is_register'   => false,
						'success'       => true,
						'error'         => false,
						'data'          => $this->bindAccountByThird($params, false),
						'msg'           => '登录并成功绑定账户',
					]);
				} catch (Exception | AuthException $e) {
					//--已有 联系方式 绑定是啊比
					return $this->rememberCallStatus($params['channel'], $params['state'], [
						'callback_code' => self::CALLBACK_AUTH_BIND_ERROR,
						'state'         => $params['state'],
						'is_register'   => false,
						'success'       => false,
						'error'         => true,
						'data'          => [],
						'msg'           => $e->getMessage(),
					]);
				}
			}

			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => self::CALLBACK_UNBIND_CODE,
				'state'         => $params['state'],
				'is_register'   => false,
				'success'       => true,
				'error'         => false,
				'data'          => [
					'third_info' => $object->toMap(),
					'nickname'   => $object->userName,
					'unique'     => $object->unionId,
				],
				'msg'           => sprintf('当前%s账号未绑定手机号/邮箱,请前往绑定', self::CHANNEL_NAME_MAP[ $params['channel'] ]),
			]);
		}
		[$_, $errorCode] = UserService::checkUseAccountStatusByFilter($registered->user_id);
		if ($errorCode !== 0) {
			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => $errorCode,
				'state'         => $params['state'],
				'is_register'   => true,
				'success'       => false,
				'error'         => false,
				'data'          => [],
				'msg'           => Status::getMessage($errorCode),
			]);
		}
		$params['type'] = $type;
		if ($companySign) {
			return $this->loginThirdByCompany($registered, $params, $companySign, $platform);
		}

		return $this->loginThirdByPersonal($registered->user_id, $params, $platform);

	}

	/**
	 * @param $channel
	 * @param $state
	 * @param $loginTmpCode
	 *
	 * @return array
	 * @throws GuzzleException
	 * 通过中间拦截 获取回调
	 */
	public function thirdJumpUrl($channel, $state, $loginTmpCode): array
	{

		if (!array_key_exists($channel, AuthConstMap::CALLBACK_INFO_BY_JUMP)) {
			throw new AuthException('Not Found Channel');
		}
		$data = $this->getCallStatus($channel, $state, self::PREFIX_LOGIN_JUMP_REDIS);
		if ($data['qr_url'] ?? false) {
			$url = $data['qr_url'];
			$this->clearCallStatus($channel, $state, self::PREFIX_LOGIN_JUMP_REDIS);
			$this->clearCallStatus($channel, $state, self::PREFIX_LOGIN_REDIS);
			$withQuery[ AuthConstMap::CALLBACK_INFO_BY_JUMP[ $channel ] ] = $loginTmpCode;

			return HttpHelper::client()->get($url, $withQuery);
		}

		return [];
	}

	/**
	 * @param $params
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function getRedirectUrl($params): array
	{
		$companySign = $params['company_id'] ?? 0;
		$query       = [
			'query' => [
				'channel' => $params['channel'],
			],
			'state' => $this->encryptCode($params['channel'], $companySign, $params['platform'], $params['is_bind'] > 0, $params['type']),
		];

		$redirectData = ThirdDriver::channel($params['channel'], $query)->redirect();
		$this->callbackByJump($params['channel'], $query['state'], $redirectData);

		return [
			'redirect_data' => $redirectData,
			'spot_sign'     => $query['state'],
		];
	}

	/**
	 * @param $channel
	 * @param $state
	 * @param $redirectData
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	private function callbackByJump($channel, $state, $redirectData): void
	{
		if (array_key_exists($channel, AuthConstMap::CALLBACK_INFO_BY_JUMP)) {
			$this->rememberCallStatus($channel, $state, $redirectData, self::PREFIX_LOGIN_JUMP_REDIS);
		}
	}

	/**
	 * @param $channel
	 * @param $state
	 *
	 * @return array
	 */
	public function getPolling($channel, $state): array
	{
		$polling = $this->getCallStatus($channel, $state);

		$data = [
			'spot_qr' => !empty($polling),
			'result'  => $polling,
		];
		$this->clearCallStatus($channel, $state);

		return $data;
	}

	/**
	 * @param string $channel
	 * @param int    $userId
	 * @param array  $params
	 *
	 * @return array
	 */
	public function bindThirdAccount(string $channel, int $userId, array $params): array
	{
		$this->checkBindThirdStatus($channel, $userId, $params['unique']);
		$bindStatus = $this->thirdLoginInfoModel->bindThirdAccount($channel, $userId, $params['user_name'], $params['unique'], $params['info']);

		return [
			'bind_status' => !empty($bindStatus),
			'bind_info'   => $bindStatus,
		];
	}

	protected function checkBindThirdStatus(string $channel, int $userId, string $unionId): void
	{
		//--第三方已绑定
		$blinded = $this->thirdLoginInfoModel->getThirdRegistered($channel, $unionId);
		if ($blinded && (int)$blinded->user_id !== $userId) {
			throw new AuthException(sprintf('该%s账号已绑定其他账号', self::CHANNEL_NAME_MAP[ $channel ]));
		}
		//--用户已绑定其他
		$blinded = $this->thirdLoginInfoModel->getThirdByFilter([
			'channel' => $channel,
			'user_id' => $userId,
		]);
		if ($blinded && (string)$blinded->unique_id !== $unionId) {
			throw new AuthException(sprintf('该手机号或邮箱已绑定其他%s账号', self::CHANNEL_NAME_MAP[ $channel ]));
		}
	}

	/**
	 * @param array $params
	 * @param bool  $isCheckCode
	 *
	 * @return array[]
	 * @throws Exception 根据第三方渠道 绑定手机号/邮箱
	 */
	public function bindAccountByThird(array $params, bool $isCheckCode = true): array
	{
		$successRes = [
			'bind_data'  => [],
			'login_data' => [],
			'behavior'   => 1,
		];
		if ($isCheckCode) {
			$checkCode = $this->checkCaptchaStatus($params['account'], $params['code']);
			if (!$checkCode) {
				throw new AuthException('验证码输入错误');
			}
		}
		$account = $this->checkAccountType($params['account'], 'email', 'phone');

		$params = array_merge($params, $account);

		[$userInfo, $errorCode] = UserService::checkUseAccountStatusByFilter($account);

		[
			$companySign,
			$platform,
			$_,
			$type,
		] = $this->decryptCode($params['channel'], $params['state']);

		if (($params['is_company'] > 0) && (int)$companySign !== (int)$params['company_id']) {
			throw new AuthException('非法访问');
		}
		if (!isset($params['email'])) {
			$params['email'] = '';
			$params['type']  = 2;
		}
		if (!isset($params['phone'])) {
			$params['phone'] = '';
			$params['type']  = 1;
		}
		//---账号已存在
		if ($userInfo) {

			$this->checkBindThirdStatus($params['channel'], $userInfo->id, $params['unique']);

			DB::connection('mysql')->beginTransaction();
			try {
				//--企业版->第三方账户绑定账号
				if ($params['is_company']) {
					$this->bindByAccountExistedWithCompany($params, $userInfo, $type, $successRes);
				} else {
					//--个人版->第三方账户绑定账号
					$this->bindByAccountExistedWithPersonal($params, $userInfo, $successRes);
				}
				DB::connection('mysql')->commit();

				return $successRes;
			} catch (Exception $e) {
				DB::connection('mysql')->rollBack();

				throw new AuthException($e->getMessage());
			}
		}
		if (!in_array($errorCode, [Status::USER_NOT_EXIST, 0], true)) {
			throw new AuthException(Status::getMessage($errorCode), $errorCode);
		}

		//---账号不存在
		if ($params['is_company']) {
			if ($type === self::TYPE_INVITE) {
				return (new RegisterService())->inviteRegisterByThirdWithCompany($companySign, $platform, $params, $successRes);
			}
			if ($type === self::TYPE_SHARE) {
				return (new RegisterService())->shareRegisterByThirdWithCompany($companySign, $platform, $params, $successRes);
			}
			throw new AuthException('您不是该企业成员，请联系管理员');
		}

		//---个人版注册
		$avatar           = $params['third_info']['avatar'] ?? '';
		$params['openid'] = $params['union_id'] = $params['ios_id'] = '';

		$result = UserService::register($params, ($params['third_info']['userName'] ?? ''), $avatar);
		if (!$result || !is_array($result)) {
			throw new AuthException('用户绑定注册失败');
		}

		$successRes['behavior']   = 2;
		$successRes['bind_data']  = $this->bindThirdAccount($params['channel'], $result['uId'], [
			'unique'    => $params['unique'],
			'user_name' => $params['nickname'],
			'info'      => $params['third_info'],
		]);
		$successRes['login_data'] = UserService::login($result['uId'], $platform);

		return $successRes;

	}

	/**
	 * @param $params
	 * @param $userInfo
	 * @param $type
	 * @param $successRes
	 *
	 * @return array
	 * 企业版->第三方账户绑定账号
	 */
	protected function bindByAccountExistedWithCompany($params, $userInfo, $type, &$successRes): array
	{
		$params['userId'] = $userInfo->id;
		$company          = CompanyUserModel::getCompanyUser($userInfo->id, $params['company_id']);

		if (!$company) {
			if ($type === self::TYPE_INVITE) {
				return (new RegisterService())->inviteRegisterByThirdWithCompany($params['company_id'], $params['platform'], $params, $successRes);
			}
			if ($type === self::TYPE_SHARE) {
				return (new RegisterService())->shareRegisterByThirdWithCompany($params['company_id'], $params['platform'], $params, $successRes);
			}
			throw new AuthException('您不是该企业成员，请联系管理员');
		}

		$thirdAccountExists = $this->thirdLoginInfoModel->getThirdByFilter([
			'channel'   => $params['channel'],
			'unique_id' => $params['unique'],
		]);
		//--当前第三方账户 已绑定过手机/邮箱
		if ($thirdAccountExists) {
			if ($thirdAccountExists->user_id !== $userInfo->id) {
				throw new AuthException(sprintf('该%s已绑定其他账号', self::CHANNEL_NAME_MAP[ $params['channel'] ]));
			}

			$successRes['login_data'] = $this->loginCompanyWithSpecial($userInfo->id, $params, $company, $type);

			return $successRes;
		}
		$thirdAccountExists = $this->thirdLoginInfoModel->getThirdByFilter([
			'channel' => $params['channel'],
			'user_id' => $userInfo->id,
		]);

		//--当前手机/邮箱 已绑定其他第三方账户
		if ($thirdAccountExists) {
			if ($thirdAccountExists->unique_id !== $params['unique']) {
				$successRes['login_data'] = $this->loginCompanyWithSpecial($userInfo->id, $params, $company, $type);

				return $successRes;
			}
			throw new AuthException(sprintf('该手机号或邮箱已绑定其他%s账号', self::CHANNEL_NAME_MAP[ $params['channel'] ]));
		}

		$successRes['bind_data'] = $this->bindThirdAccount($params['channel'], $userInfo->id, [
			'unique'    => $params['unique'],
			'user_name' => $params['nickname'],
			'info'      => $params['third_info'],
		]);
		if (!$successRes['bind_data']['bind_status']) {
			throw new AuthException(self::CHANNEL_NAME_MAP[ $params['channel'] ] . '账号绑定失败');
		}

		$successRes['login_data'] = $this->loginCompanyWithSpecial($userInfo->id, $params, $company, $type);

		return $successRes;
	}

	/**
	 *
	 * @param $params
	 * @param $userInfo
	 * @param $successRes
	 *
	 * @return array
	 * 个人版->第三方账户绑定账号
	 */
	protected function bindByAccountExistedWithPersonal($params, $userInfo, &$successRes): array
	{
		$thirdAccountExists = $this->thirdLoginInfoModel->getThirdByFilter([
			'channel'   => $params['channel'],
			'unique_id' => $params['unique'],
		]);
		//--当前第三方账户 已绑定过手机/邮箱
		if ($thirdAccountExists) {
			if ($thirdAccountExists->user_id !== $userInfo->id) {
				throw new AuthException(sprintf('该%s已绑定其他账号', self::CHANNEL_NAME_MAP[ $params['channel'] ]));
			}

			$successRes['login_data'] = UserService::login($thirdAccountExists->user_id, $params['platform']);

			return $successRes;
		}
		$thirdAccountExists = $this->thirdLoginInfoModel->getThirdByFilter([
			'channel' => $params['channel'],
			'user_id' => $userInfo->id,
		]);
		//--当前手机/邮箱 已绑定其他第三方账户
		if ($thirdAccountExists) {
			if ($thirdAccountExists->unique_id !== $params['unique']) {
				$successRes['login_data'] = UserService::login($thirdAccountExists->user_id, $params['platform']);

				return $successRes;
			}
			throw new AuthException(sprintf('该手机号或邮箱已绑定其他%s账号', self::CHANNEL_NAME_MAP[ $params['channel'] ]));
		}
		$successRes['bind_data'] = $this->bindThirdAccount($params['channel'], $userInfo->id, [
			'unique'    => $params['unique'],
			'user_name' => $params['nickname'],
			'info'      => $params['third_info'],
		]);
		if (!$successRes['bind_data']['bind_status']) {
			throw new AuthException('账号绑定失败,系统错误');
		}
		$successRes['login_data'] = UserService::login($userInfo->id, $params['platform']);

		return $successRes;
	}

	public function removeBindThirdAccount(string $channel, int $personId): array
	{
		$thirdAccountExists = $this->thirdLoginInfoModel->getThirdByFilter([
			'user_id' => $personId,
			'channel' => $channel,
		]);
		if (!$thirdAccountExists) {
			throw new AuthException('该账号未绑定或已解除绑定');
		}

		$status = $this->thirdLoginInfoModel->unBindThirdAccount($channel, $personId);

		return ['remove_status' => !empty($status)];
	}

}
