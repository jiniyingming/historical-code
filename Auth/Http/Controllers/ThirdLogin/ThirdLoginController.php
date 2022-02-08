<?php

namespace Modules\Auth\Http\Controllers\ThirdLogin;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Language\Status;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Controllers\AuthController;

class ThirdLoginController extends AuthController
{
	/**
	 * @throws \Exception
	 */
	public function redirectUrl(Request $request): JsonResponse
	{
		$params = $request->input();

		$validator = $this->validatorRedirect($params);
		if ($validator->fails()) {
			return sendResponse(Status::ERROR);
		}
		$params['type'] = $params['type'] ?? $this->authService::TYPE_NORMAL;
		try {
			$data = $this->authService->getRedirectUrl($params);
		} catch (AuthException | Exception $e) {
			return sendResponse($e->getCode(), $res, $e->getMessage());
		}

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * 轮询查询数据结果
	 */
	public function pollingLoginEffect(Request $request): JsonResponse
	{
		$params    = $request->input();
		$validator = $this->validatorPolling($params);
		if ($validator->fails()) {
			return sendResponse(Status::ERROR);
		}

		try {
			$data = $this->authService->getPolling($params['channel'], $params['state']);
		} catch (AuthException | Exception $e) {
			return sendResponse($e->getCode(), $res, $e->getMessage());
		}

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * 已注册 绑定第三方账号
	 */
	public function bindingThird(Request $request): JsonResponse
	{

		$params = $request->input();

		$validator = $this->validatorThirdBinding($params);
		if ($validator->fails()) {
			return sendResponse(Status::ERROR);
		}

		try {
			$data = $this->authService->bindThirdAccount($params['channel'], $params['userInfo']['person_id'], $params);
		} catch (AuthException | Exception $e) {

			return sendResponse($e->getCode(), $res, $e->getMessage());
		}

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Exception
	 * 第三方账号绑定 手机或邮箱
	 */
	public function bindAccountByThird(Request $request): JsonResponse
	{
		$params    = $request->input();
		$validator = $this->validatorBindAccountByThird($params);
		if ($validator->fails()) {
			return sendResponse(Status::ERROR);
		}

		try {
			$data = $this->authService->bindAccountByThird($params);
		} catch (AuthException | Exception $e) {
			return sendResponse($e->getCode(), $res, $e->getMessage());
		}

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * 解除第三方账号绑定
	 */
	public function removeBindThirdAccount(Request $request): JsonResponse
	{
		$params    = $request->input();
		$validator = $this->validatorRemoveBindThirdAccount($params);
		if ($validator->fails()) {
			return sendResponse(Status::ERROR);
		}

		try {
			$data = $this->authService->removeBindThirdAccount($params['channel'], $params['userInfo']['person_id']);
		} catch (AuthException | Exception $e) {
			return sendResponse($e->getCode(), $res, $e->getMessage());
		}

		return sendResponse(Status::SUCCESS, $data);
	}

}
