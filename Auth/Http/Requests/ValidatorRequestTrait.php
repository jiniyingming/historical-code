<?php

namespace Modules\Auth\Http\Requests;

use Validator;

trait ValidatorRequestTrait
{
	/**
	 * @param $params
	 *
	 * @return \Illuminate\Validation\Validator
	 */
	public function validatorThirdBack($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel' => 'required',
			'state'   => 'required',
			'code'    => 'required',
		], [
			'channel.required' => 'channel not found',
			'state.required'   => 'channel not found',
			'code.required'    => 'channel not found',
		]);
	}

	/**
	 * @param $params
	 *
	 * @return \Illuminate\Validation\Validator
	 */
	public function validatorRedirect($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel'    => 'required',
			'is_company' => 'required',
			'is_bind'    => 'required',
			'platform'   => 'required',
		], [
			'channel.required'    => 'channel not found',
			'is_company.required' => 'is_company not found',
			'platform.required'   => 'platform not found',
			'is_bind.required'    => 'is_bind not found',
		]);
	}

	public function validatorThirdBinding($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel'   => 'required',
			'unique'    => 'required',
			'user_name' => 'required',
			'info'      => 'required',
		], [
			'channel.required'   => 'channel not found',
			'unique.required'    => 'unique not found',
			'user_name.required' => 'user_name not found',
			'info.required'      => 'info not found',
		]);
	}

	public function validatorBindAccountByThird($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel'    => 'required',
			'platform'   => 'required',
			'unique'     => 'required',
			'account'    => 'required',
			'code'       => 'required',
			'state'      => 'required',
			'is_company' => 'required',
			'nickname'   => 'required',
			'third_info' => 'required',
		], [
			'channel.required'    => 'channel not found',
			'platform.required'   => 'platform not found',
			'unique.required'     => 'unique not found',
			'account.required'    => 'account not found',
			'code.required'       => 'code not found',
			'state.required'      => 'state not found',
			'is_company.required' => 'state not found',
			'nickname.required'   => 'nickname not found',
			'third_info.required' => 'third_info not found',
		]);
	}

	public function validatorRemoveBindThirdAccount($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel' => 'required',
		], [
			'channel.required' => 'channel not found',
		]);
	}

	public function validatorPolling($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel' => 'required',
			'state'   => 'required',
		], [
			'channel.required' => 'channel not found',
			'state.required'   => 'state not found',
		]);
	}

	public function validatorJump($params): \Illuminate\Validation\Validator
	{
		return Validator::make($params, [
			'channel'    => 'required',
			'state'      => 'required',
			'login_code' => 'required',
		], [
			'channel.required'    => 'channel not found',
			'state.required'      => 'channel not found',
			'login_code.required' => 'login_code not found',
		]);
	}
}