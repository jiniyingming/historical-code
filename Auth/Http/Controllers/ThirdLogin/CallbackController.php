<?php

namespace Modules\Auth\Http\Controllers\ThirdLogin;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Language\Status;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Http\Request;

class CallbackController extends AuthController
{

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 * 第三方登录 回调处理
	 */
	public function thirdBack(Request $request): JsonResponse
	{

		$params    = $request->all();
		$validator = $this->validatorThirdBack($params);
		if ($validator->fails()) {

			return sendResponse(Status::ERROR);
		}

		$data = $this->authService->loginByThird($params);

		return sendResponse(Status::SUCCESS, $data);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function ticketCallByFly(Request $request): JsonResponse
	{
		$params = $request->all();

		try {
			$this->callTicketService->setFlyBookTicket($params);
		} catch (Exception $e) {
			Log::channel('thirdCall')->error('ticketCallByFly', [
				'error'   => $e->getMessage(),
				'$params' => $params,
			]);
		}

		return response()->json([
			'challenge' => $request->input('challenge')
		]);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return array|\Illuminate\Http\JsonResponse
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function thirdJump(Request $request)
	{
		$params    = $request->all();
		$validator = $this->validatorJump($params);
		if ($validator->fails()) {
			return sendResponse(Status::ERROR);
		}

		try {
			return $this->authService->thirdJumpUrl($params['channel'], $params['state'], $params['login_code']);
		} catch (AuthException | Exception $e) {
			return sendResponse($e->getCode(), $res, $e->getMessage());
		}
	}

}