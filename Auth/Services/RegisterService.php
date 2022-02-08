<?php

namespace Modules\Auth\Services;

use App\Models\Company\CompanyUserModel;
use App\Services\CompanyService;
use App\Services\UserService;

use Language\Status;
use Modules\Auth\Entities\ThirdLoginInfoModel;
use Modules\Auth\Exceptions\AuthException;

class RegisterService extends AuthService
{

	public function __construct()
	{
		parent::__construct(new ThirdLoginInfoModel());
	}

	protected function runInviteOrShareRegisterByThirdWithCompany($companySign, $platform, $params, $identity, &$successRes)
	{
		$successRes['behavior'] = 2;
		$params['openid']       = $params['union_id'] = $params['ios_id'] = '';
		$userId                 = $params['userId'] ?? false;
		if (!$userId) {
			$result = UserService::register($params, $params['nickname'], $params['third_info']['avatar'] ?? '');
			if (!$result || !is_array($result)) {
				throw new AuthException('用户绑定注册失败');
			}
			$userId = $result['uId'];
		}

		$successRes['login_data'] = CompanyService::CompanyLoginOrReg($userId, $companySign, $platform, $error, 1, $identity);
		if ($error > 0) {
			throw new AuthException(null, $error);
		}

		$successRes['bind_data'] = $this->bindThirdAccount($params['channel'], $userId, [
			'unique'    => $params['unique'],
			'user_name' => $params['nickname'],
			'info'      => $params['third_info'],
		]);
		if (!$successRes['bind_data']['bind_status']) {
			throw new AuthException('用户绑定账号失败');
		}

		return $successRes;
	}

	/**
	 * @param $companySign
	 * @param $platform
	 * @param $params
	 * @param $successRes
	 *
	 * @return array
	 * 第三方渠道 邀请注册 外部联系人
	 */
	public function inviteRegisterByThirdWithCompany($companySign, $platform, $params, &$successRes): array
	{
		return $this->runInviteOrShareRegisterByThirdWithCompany($companySign, $platform, $params, CompanyUserModel::IDENTITY_OUT, $successRes);
	}

	public function shareRegisterByThirdWithCompany($companySign, $platform, $params, &$successRes): array
	{
		return $this->runInviteOrShareRegisterByThirdWithCompany($companySign, $platform, $params, CompanyUserModel::IDENTITY_VISITOR, $successRes);
	}
}
