<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Log;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\ThirdDriver;

class AuthThirdChannelCheck
{
	private $whiteUri = [
		'auth/thirdlogin/flybook/ticket/callback'
	];

	/**
	 * Handle an incoming request.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure                 $next
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function handle(Request $request, Closure $next)
	{
		if (in_array($request->path(), $this->whiteUri)) {
			return $next($request);
		}
		$channel = $request->input('channel');
		if (!$channel) {
			sendErrorResponse('Channel not found ！');
		}
		$channelAuth = config('auth.open_third_services');
		if (!in_array($channel, $channelAuth, true)) {
			sendErrorResponse('Channel not opened ！');
		}

		$isCompany = $request->input('is_company');

		if ($isCompany > 0 && in_array($channel, config('auth.third_services_by_personal'), true)) {
			sendErrorResponse('企业版未开启该渠道权限');
		}

		$actionName = $request->route()->getActionName();

		$response = $next($request);
		if ($actionName === 'Modules\Auth\Http\Controllers\ThirdLogin\CallbackController@thirdBack') {
			$content = json_decode($response->content(), true);
			Log::channel('thirdCall')->info('CallbackController@thirdBack', [
				'$request'  => $request->input(),
				'$response' => $content,
			]);
			if ($channel === AuthConstMap::LOGIN_DRIVER_BY_BAIDU) {
				sleep(10);

				return $this->setBaiduRedirect($channel, $request->input('state'));
			}
		}

		return $response;
	}

	/**
	 * @throws \Exception
	 * 百度回调 重构至登录页面
	 */
	private function setBaiduRedirect($channel, $state)
	{
		$query = [
			'query' => [
				'channel' => $channel,
			],
			'state' => $state,
		];

		return redirect(ThirdDriver::channel($channel, $query)->redirect()['qr_url'] ?? '');
	}
}
