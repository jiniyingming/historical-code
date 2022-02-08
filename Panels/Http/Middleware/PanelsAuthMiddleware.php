<?php

namespace Modules\Panels\Http\Middleware;

use App\Http\Controllers\AuthController;
use App\Models\ProjectModel;
use App\Models\ProjectUserModel;
use Closure;
use Illuminate\Http\Request;
use Language\Status;

/**
 * 任务看板权限验证
 * @verify 验证配置 panels.paramsRuleMap
 */
class PanelsAuthMiddleware
{

	/**
	 * @param Request $request
	 * @param Closure $next
	 *
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next)
	{

		$companyId = $request->input('companyInfo')['companyId'] ?? 0;
		if ($companyId < 1) {
			sendErrorResponse('权限不足,无法请求', Status::SUCCESS);
		}

		$identity = (int)($request->input('companyInfo')['identity'] ?? 0);
		if (config('panels.taskExternalSwitch') && !in_array($identity, config('panels.taskExternalMap'), true)) {
			sendErrorResponse('权限不足,无法请求', Status::SUCCESS);
		}
		$paramsRuleMap = config('panels.paramsRuleMap');
		//--verify 验证
		$actionName = $request->route()->getActionName();

		if (isset($paramsRuleMap[ $actionName ])) {
			$verify      = $request->input('verify');
			$localVerify = '';
			array_map(static function ($field) use ($request, &$localVerify) {
				$val         = $request->input($field);
				$localVerify .= is_array($val) ? json_encode($val) : $val;
			}, $paramsRuleMap[ $actionName ]);

			$verifyResult = verifyParam($verify, $localVerify);
			if (!$verifyResult['result']) {
				sendErrorResponse(null, Status::VERIFY_ERROR, $verifyResult['verify']);
			}
		}
		$request->request->set('projectInfo', (object)[]);
		$request->request->set('projectUserInfo', (object)[]);
		//--项目认证
		$projectNo = $request->input('project_id');
		if ($projectNo) {
			$project = ProjectModel::getInfo(['project_number' => $projectNo]);

			if (!$project) {
				sendErrorResponse(null, Status::PROJECT_NOT_EXIST);
			}
			$userInfo = $request->input('userInfo');
			$userId   = $userInfo['u_id'];

			$leader      = $request->input('leader', 0);
			$projectUser = ProjectUserModel::getInfo(['project_id' => $project->id, 'user_id' => $userId]);

			if (!$projectUser) {
				sendErrorResponse(null, Status::NOT_PROJECT_MEMBER);
			}
			$request->request->set('projectInfo', (object)$project->toArray());
			$request->request->set('projectUserInfo', (object)$projectUser->toArray());
			if ($leader > 0) {
				$projectUser = ProjectUserModel::getInfo(['project_id' => $project->id, 'user_id' => $leader]);
				if (!$projectUser) {
					sendErrorResponse(null, Status::PANEL_ITEM_NOT_LEADER);
				}
			}
			//--写入权限统一处理
			$paramsRuleMap = config('panels.panels_write_auth');
			if (in_array($actionName, $paramsRuleMap, true)) {
				$permission = AuthController::projectAuthority($project->panels_op_permission, $projectUser->type);
				if (!$permission) {
					sendErrorResponse(null, Status::NO_PERMISSION);
				}
			}
		}

		return $next($request);
	}
}

