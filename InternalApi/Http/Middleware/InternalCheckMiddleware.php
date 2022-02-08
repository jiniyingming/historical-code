<?php

namespace Modules\InternalApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Language\Status;

class InternalCheckMiddleware
{
	/**
	 * @var int accessToken 过期时间
	 */
	private $TOKEN_EXPIRED = 15;

	/**
	 * @param Request $request
	 * @param Closure $next
	 *
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next)
	{
		$timestamp   = $request->header('timestamp');
		$accessToken = $request->header('accessToken');
		if (!$timestamp || !$accessToken) {
			sendResponse(Status::PARAMS_ERROR, $response, 'not found timestamp or accessToken')->send();
			die;
		}

		$authMap = config('internalapi.application');
		//--verify 验证
		$actionName = $request->route()->getActionName();

		$verify = false;
		$time   = time();
		foreach ($authMap as $accessSign => $routeMap) {
			if (in_array($actionName, $routeMap, true) && $this->getAccessToken($accessSign, $timestamp) === $accessToken) {
				if ($timestamp > $time || ($time - $timestamp) > $this->TOKEN_EXPIRED) {
					sendResponse(Status::ERROR, $response, 'accessToken expired')->send();
					die;
				}
				$verify = true;
			}
		}
		if (!$verify) {
			sendResponse(Status::ERROR, $response, 'accessToken verification failed')->send();
			die;
		}

		return $next($request);
	}

	/**
	 * @param $accessSign
	 * @param $timestamp
	 *
	 * @return string
	 */
	private function getAccessToken($accessSign, $timestamp): string
	{
		return base64_encode(md5($accessSign . $timestamp));
	}
}
