<?php

namespace Modules\Auth\Services;

use App\Models\Company\CompanyUserModel;
use App\Services\CompanyService;
use App\Services\UserService;
use Language\Status;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\common\CommonService;

abstract class LoginService extends CommonService
{

	/**
	 * @param int    $userId  用户 个人ID
	 * @param array  $params  company_id，platform
	 * @param object $company CompanyUserModel
	 * @param string $type    类型 normal share invite
	 *
	 * @return array
	 * 企业登录 包括游客 外部联系人
	 */
	protected function loginCompanyWithSpecial(int $userId, array $params, object $company, string $type): array
	{
		if (array_key_exists($type, AuthConstMap::TYPE_MAP) && in_array((int)$company->identity, AuthConstMap::TYPE_MAP, true)) {

			$userLogin = CompanyService::CompanyLoginOrReg($userId, $params['company_id'], $params['platform'], $error, 1, $company->identity);
		} else {
			$userLogin = CompanyService::companyUserLogin($userId, $params['company_id'], $params['platform'], $error);
		}
		if ($error > 0) {
			throw new AuthException(Status::getMessage($error), $error);
		}
		if ($userLogin && isset($userLogin['companies']) && count($userLogin['companies']) === 1) {
			$userLogin = CompanyService::userLogin($userLogin['companies'][0]['user_id'], $userLogin['companies'][0]['company_id'], $params['platform'], 0, '', '', $userLogin['companies'][0]['company_name'], $company->identity, $userId);
		}

		return $userLogin;
	}

	/**
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * 第三方登录 企业版
	 */
	protected function loginThirdByCompany(object $registered, array $params, $companySign, $platform): array
	{
		$company = CompanyUserModel::getCompanyUser($registered->user_id, $companySign);
		if (!$company && in_array($params['type'], [
				self::TYPE_SHARE,
				self::TYPE_INVITE,
			], true)) {
			/**
			 * 特殊情况  邀请 分享 渠道 企业允许直接注册
			 */
			$userLogin = CompanyService::CompanyLoginOrReg($registered->user_id, $companySign, $platform, $error, 1, self::TYPE_MAP[ $params['type'] ]);
			if ($error > 0) {
				return $this->rememberCallStatus($params['channel'], $params['state'], [
					'callback_code' => self::CALLBACK_COMPANY_USER_LOGIN_ERROR_CODE,
					'state'         => $params['state'],
					'is_register'   => true,
					'success'       => false,
					'error'         => true,
					'data'          => [],
					'msg'           => Status::getMessage($error),
				]);
			}

			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => self::CALLBACK_NORMAL_LOGIN_CODE,
				'state'         => $params['state'],
				'is_register'   => true,
				'success'       => true,
				'error'         => false,
				'data'          => [
					'isCompany' => true,
					'userLogin' => $userLogin,
				],
				'msg'           => '登录成功',
			]);
		}

		/**
		 * 分享渠道 屏蔽其他登录
		 */
		if (!$company || (in_array($company->identity, self::TYPE_MAP, true) && !in_array($params['type'], [
					self::TYPE_INVITE,
					self::TYPE_SHARE,
				], true))) {

			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => self::CALLBACK_NOT_COMPANY_USER_CODE,
				'state'         => $params['state'],
				'is_register'   => true,
				'success'       => false,
				'error'         => true,
				'data'          => [],
				'msg'           => '您不是该企业成员，请联系管理员',
			]);
		}
		/**
		 * 访客进入邀请 身份转换为外部联系人
		 */
		$isChangeIdentity = false;
		if ($params['type'] === self::TYPE_INVITE && $company->identity === CompanyUserModel::IDENTITY_VISITOR) {
			$company->identity = CompanyUserModel::IDENTITY_OUT;
			$isChangeIdentity  = true;
		}

		$params['platform']   = $platform;
		$params['company_id'] = $companySign;
		try {
			$userLogin = $this->loginCompanyWithSpecial($registered->user_id, $params, $company, $params['type']);
		} catch (\Exception $e) {
			return $this->rememberCallStatus($params['channel'], $params['state'], [
				'callback_code' => self::CALLBACK_COMPANY_USER_LOGIN_ERROR_CODE,
				'state'         => $params['state'],
				'is_register'   => true,
				'success'       => false,
				'error'         => true,
				'data'          => [],
				'msg'           => $e->getMessage(),
			]);
		}
		$isChangeIdentity && $company->save();

		return $this->rememberCallStatus($params['channel'], $params['state'], [
			'callback_code' => self::CALLBACK_NORMAL_LOGIN_CODE,
			'state'         => $params['state'],
			'is_register'   => true,
			'success'       => true,
			'error'         => false,
			'data'          => [
				'isCompany' => true,
				'userLogin' => $userLogin,
			],
			'msg'           => '登录成功',
		]);
	}

	/**
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 */
	protected function loginThirdByPersonal(int $userId, array $params, $platform): array
	{

		return $this->rememberCallStatus($params['channel'], $params['state'], [
			'callback_code' => self::CALLBACK_NORMAL_LOGIN_CODE,
			'state'         => $params['state'],
			'is_register'   => true,
			'success'       => true,
			'error'         => false,
			'data'          => [
				'isCompany' => true,
				'userLogin' => UserService::login($userId, $platform),
			],
			'msg'           => '登录成功',
		]);
	}

}
