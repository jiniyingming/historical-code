<?php

namespace Modules\Auth\Http\Middleware;

use App\Models\Company\CompanyModel;
use Closure;
use Illuminate\Http\Request;
use Language\Status;

class ThirdCompanyMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure                 $next
	 *
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next)
	{
		$originUrl = $request->header('Referer');
		$parseUrl  = parse_url($originUrl);

		//		$this->checkRequest($request);
		$isCompany = (bool)$request->input('is_company', 0);
		if ($isCompany) {
			$url = $parseUrl['host'] ?? $parseUrl['path'];
			if (!$url) {
				sendErrorResponse(null, Status::APPSERECT_ERROE);
			}
			$company = CompanyModel::where('company_domain', $url)->first();

			if (!isset($company->status) || (int)$company->status !== CompanyModel::STATUS_START) {
				sendErrorResponse('该企业不存在或已停止使用');
			}
			$request->request->set('company_id', $company->id);
		}

		$request->request->set('redirect_uri', rtrim($originUrl, '/'));

		return $next($request);
	}

	private function checkRequest($request)
	{
		$appSecret      = config('app.api_param.app_serect');
		$appSecretToken = $request->header('appSerectToken', '');
		$timestamp      = $request->header('timestamp', '');
		if (!$appSecretToken || !$timestamp) {
			sendErrorResponse(null, Status::MISS_PARAM);
		}
		$serverSecretToken = md5($appSecret . $timestamp);
		if ($appSecretToken !== $serverSecretToken) {
			if (!$appSecretToken || !$timestamp) {
				sendErrorResponse(null, Status::APPSERECT_ERROE);
			}
		}
	}
}
