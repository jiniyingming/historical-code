<?php

namespace Modules\Panels\Http\Controllers;

use App\Http\Validator\PanelsCardValidator;
use Modules\Panels\Services\PanelsCardCommentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Language\Status;

/**
 * 任务评论处理
 */
class PanelsCommentController extends BaseController
{
	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 * 创建&更新评论
	 */
	public function createComment(Request $request): JsonResponse
	{
		$params = $request->all();

		//参数校验
		if ($validator = PanelsCardValidator::comment($params)) {
			return sendResponse($validator);
		}

		$result = PanelsCardCommentService::operateComment($params, getUserUid());
		if ($result) {
			return sendResponse(Status::SUCCESS, $result);
		}

		return sendResponse(Status::ERROR, $result);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * 删除评论
	 */
	public function deleteComment(Request $request): JsonResponse
	{
		$comment_id = $request->get('comment_id');
		//参数校验
		if ($validator = PanelsCardValidator::delComment($request->all())) {
			return sendResponse($validator);
		}
		$result = PanelsCardCommentService::delComment($comment_id, getUserUid());
		if ($result) {
			return sendResponse(Status::SUCCESS, $result);
		}

		return sendResponse(Status::ERROR, $result);
	}

	/**
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 * 评论列表&增量拉取
	 */
	public function commentList(Request $request): JsonResponse
	{
		$params = $request->all();
		//参数校验
		if ($validator = PanelsCardValidator::commentList($params)) {
			return sendResponse($validator);
		}
		$updatedAt = $request->get('updated', false);
		$where     = [
			'card_id'    => $params['card_id'],
			'project_id' => $request->input('projectInfo')->id,
		];
		$list      = PanelsCardCommentService::commentList($where, [
			'page'  => $params['page'],
			'limit' => $params['page_size'],
		], $updatedAt);

		return sendResponse(Status::SUCCESS, $list);

	}
}
