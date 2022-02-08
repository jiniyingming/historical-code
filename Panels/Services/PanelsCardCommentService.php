<?php

namespace Modules\Panels\Services;

use Modules\Panels\Entities\PanelsCardCommentInfoModel;
use Modules\Panels\Entities\PanelsCardCommentModel;
use App\Models\PanelsCardModel;
use App\Models\ProjectUserModel;
use App\Models\UserInfoModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Notice\Notice;

/**
 * 任务评论-逻辑
 */
class PanelsCardCommentService extends PanelsBaseService
{

	/**
	 * @param array $params
	 * @param int   $userId
	 *
	 * @return bool|array
	 * @throws Exception
	 * 评论操作 create update
	 */
	public static function operateComment(array $params, int $userId)
	{
		[$comment, $commentInfo, $cardInfo] = self::checkParams($params, $userId);

		$isUpdate = false;
		//---更新
		if ($params['comment_id'] ?? false) {
			$commentExec = PanelsCardCommentModel::query()->where('id', $params['comment_id'])->first();
			$commentId   = $params['comment_id'];
			if (!$commentExec) {
				sendErrorResponse('该评论不存在或已被删除');
			}
			$infoExec = PanelsCardCommentInfoModel::query()->find($commentExec->info_id);
			if (!$infoExec) {
				sendErrorResponse('该评论不存在或已被删除');
			}
			if ((time() - strtotime($infoExec->created_at)) > (self::UPDATE_LIMIT_TIME * 60)) {
				sendErrorResponse(sprintf('超过%d分钟不可修改', self::UPDATE_LIMIT_TIME));
			}
			$infoExec     = PanelsCardCommentInfoModel::query()->where('id', $commentExec->info_id)->first();
			$infoNotice   = json_decode($infoExec['notice_map'], true);
			$updateNotice = json_decode($commentInfo['notice_map'], true);
			if (!$infoExec->update($commentInfo)) {
				sendErrorResponse();
			}
			//--通知处理
			self::sendNoticeByComment($cardInfo, $commentId, array_diff($updateNotice, $infoNotice), true);
			$isUpdate = true;
		} else {
			DB::connection('mysql_project')->beginTransaction();
			try {

				$info_exec          = PanelsCardCommentInfoModel::create($commentInfo);
				$comment['info_id'] = $info_exec->id;
				$map                = PanelsCardCommentModel::create($comment);
				DB::connection('mysql_project')->commit();
				$commentId = $map->id;
				//--通知处理
				self::sendNoticeByComment($cardInfo, $commentId, json_decode($commentInfo['notice_map'], true));

			} catch (Exception $e) {
				Log::error('operateComment', [
					'params' => $params,
					'error'  => $e->getMessage(),
				]);
				DB::connection('mysql_project')->rollback();

				return false;
			}
		}

		$projectInfo = Request::input('projectInfo');
		$commentInfo = self::commentList(['id' => $commentId])['data_map'][0] ?? [];
		PanelsService::sendPanelsNotice(//--卡片详情页
			$cardInfo->item_id, [
			'project_number'     => $projectInfo->project_number,
			'item_id'            => $cardInfo->item_id,
			'panels_card_id'     => $cardInfo->id,
			'project_id'         => $projectInfo->id,
			'comment_id'         => $commentId,
			'is_updated_comment' => $isUpdate,
			'commentInfo'        => $commentInfo,
		], self::PANELS_CARD_INFO_NOTICE);

		return $commentInfo;
	}

	/**
	 * @param      $cardInfo
	 * @param      $commentId
	 * @param      $noticeUsers
	 * 通知处理
	 * @param bool $isUpdate
	 */
	private static function sendNoticeByComment($cardInfo, $commentId, $noticeUsers, bool $isUpdate = false): void
	{

		$projectInfo = Request::input('projectInfo');

		$userInfo = json_encode([
			'real_name' => Request::input('userInfo')['nickname'],
			'avatar'    => Request::input('userInfo')['avatar'],
		]);
		//--评论中没有@任何成员 通知任务执行人
		if (empty($noticeUsers) && !$isUpdate && $cardInfo->leader > 0) {
			Notice::createNotice(getUserUid(), $userInfo, $cardInfo->leader, [
				'project_number' => $projectInfo->project_number,
				'project'        => $projectInfo->name,
				'card_id'        => $cardInfo->id,
				'item_id'        => $cardInfo->item_id,
				'comment_id'     => $commentId,
			], $projectInfo->project_number, 1, self::NOTICE_LEADER_CODE);

			return;
		}
		if (empty($noticeUsers)) {
			return;
		}
		/**
		 * @param $noticeUsers
		 * @param $userInfo
		 * @param $cardInfo
		 * @param $projectInfo
		 * 评论@ 消息通知
		 * @param $commentId
		 */
		$sendNotice = static function ($noticeUsers, $userInfo, $cardInfo, $projectInfo, $commentId) {
			//--外部联系人限制
			$noticeUsers = self::checkOutsideUsers($noticeUsers);
			foreach ($noticeUsers as $user) {
				if ($user === self::NOTICE_ALL_MEMBER) {
					continue;
				}
				if ((int)$user !== (int)getUserUid()) {
					Notice::createNotice(getUserUid(), $userInfo, $user, [
						'project_number' => $projectInfo->project_number,
						'project'        => $projectInfo->name,
						'card_id'        => $cardInfo->id,
						'item_id'        => $cardInfo->item_id,
						'comment_id'     => $commentId,
					], $projectInfo->project_number, Notice::RECEIVER_TYPE_USER, self::NOTICE_USER_CODE);
				}
			}
		};

		//评论中@所有人	项目内所有成员（除评论者）
		if (in_array(self::NOTICE_ALL_MEMBER, $noticeUsers, true)) {
			$noticeUsers = ProjectUserModel::query()->where('project_id', $projectInfo->id)->pluck('user_id')->toArray();
		}
		$sendNotice($noticeUsers, $userInfo, $cardInfo, $projectInfo, $commentId);

		return;
	}

	/**
	 * @param $params
	 * @param $userId
	 *
	 * @return array[]
	 * 评论请求参数 检验
	 */
	private static function checkParams($params, $userId): array
	{
		if (isset($params['files']) && count($params['files']) > self::UPLOAD_NUM_LIMIT) {
			sendErrorResponse('最多可上传九张图片');
		}
		$cardInfo = PanelsCardModel::query()->where('id', $params['card_id'])->first();
		if (!$cardInfo) {
			sendErrorResponse('该任务不存在或已被删除');
		}
		$content = $params['content'];
		$comment = str_replace(self::NOTICE_PLACE_SIGN, '', $content);
		if (mb_strlen($comment, "utf-8") > 500) {
			sendErrorResponse('评论内容不超过500字');
		}
		if (isset($params['notice_user_list'])) {
			$userNum = count($params['notice_user_list']);
			/*
			 * TODO 验证暂去除
			 *  if ($userNum !== substr_count($params['content'], self::NOTICE_PLACE_SIGN)) {
				 sendErrorResponse('@成员 参数传入有误');
			 }
			 if (in_array(self::NOTICE_ALL_MEMBER, $params['notice_user_list'], true)) {
				 $userNum -= substr_count(implode(',', $params['notice_user_list']), self::NOTICE_ALL_MEMBER);
			 }*/
			//--额外通知到个人 检查处理
			if ($userNum > 0) {
				$checkUser = ProjectUserModel::query()->whereIn('user_id', $params['notice_user_list'])->where(['project_id' => Request::input('projectInfo')->id])->pluck('user_id')->toArray();

				$noticeUsers = collect($params['notice_user_list'])->filter(static function ($user) {
					return $user !== self::NOTICE_ALL_MEMBER;
				})->toArray();
				if ($diff = array_filter(array_unique(array_diff($noticeUsers, $checkUser)))) {
					$nameMap = UserInfoModel::query()->whereIn('user_id', $diff)->pluck('real_name')->toArray();
					$nameMap && sendErrorResponse(sprintf('所@ %s 成员已不再项目中,请选择其他成员', implode(',', $nameMap)));
				}
			}
		}
		$comment     = [
			'card_id'    => $params['card_id'],
			'project_id' => Request::input('projectInfo')->id,
			'user_id'    => $userId,
		];
		$commentInfo = [
			'comment_desc' => $params['content'],
			'notice_map'   => json_encode($params['notice_user_list']),
			'upload_map'   => json_encode($params['files']),
		];

		return [$comment, $commentInfo, $cardInfo];
	}

	/**
	 * @param int $commentId
	 * @param int $userId
	 *
	 * @return bool
	 * 评论删除
	 */
	public static function delComment(int $commentId, int $userId): bool
	{
		$comment = PanelsCardCommentModel::query()->where([
			'id'      => $commentId,
			'user_id' => $userId,
		])->first();
		if (!$comment) {
			sendErrorResponse('该评论不存在或已被删除');
		}
		if ((time() - strtotime($comment->created_at)) > (self::UPDATE_LIMIT_TIME * 60)) {
			sendErrorResponse(sprintf('超过%d分钟 不可删除', self::UPDATE_LIMIT_TIME));
		}
		$projectInfo = Request::input('projectInfo');
		PanelsService::sendPanelsNotice(//--卡片详情页
			$commentId, [
			'project_number' => $projectInfo->project_number,
			'panels_card_id' => $comment->card_id,
			'project_id'     => $projectInfo->id,
			'comment_id'     => $commentId,
		], self::DELETE_COMMENT);

		return PanelsCardCommentModel::query()->where([
			'id'      => $commentId,
			'user_id' => $userId,
		])->delete();
	}

	private const COMMENT_MAX_SUM = 1000;

	/**
	 * @param array       $where     筛选条件
	 * @param array|int[] $pageInfo  分页信息
	 * @param false|int   $updatedAt 增量时间节点
	 *
	 * @return array 分页结构
	 * @throws Exception 评论列表
	 */
	public static function commentList(array $where, array $pageInfo = [
		'page'  => 1,
		'limit' => 50,
	],                                       $updatedAt = false): array
	{
		if (($pageInfo['page'] * $pageInfo['limit']) > self::COMMENT_MAX_SUM) {
			sendErrorResponse(sprintf('评论最多可查看%d条', self::COMMENT_MAX_SUM));
		}
		$comment = PanelsCardCommentModel::query()->where($where)->when($updatedAt, function ($query) use ($updatedAt) {
			--$updatedAt;
			$updatedAt = date('Y-m-d H:i:s', $updatedAt);
			$query->where('updated_at', '>=', $updatedAt);
		})->orderBy('created_at', 'desc');

		return self::pageRowsReturn($comment, $pageInfo, static function ($result) {
			$map = $result->toArray();

			$infoMap   = PanelsCardCommentInfoModel::query()->whereIn('id', array_column($map, 'info_id'))->get()->toArray();
			$usersInfo = array_column($map, 'user_id');

			array_map(static function ($query) use (&$usersInfo) {
				$usersInfo = array_merge(json_decode($query, true), $usersInfo);

				return;
			}, array_column($infoMap, 'notice_map'));

			$usersInfo = UserInfoModel::query()->select('user_id', 'real_name', 'avatar')->whereIn('user_id', array_filter($usersInfo, static function ($val) {
				return !(!is_numeric($val) || $val < 1);
			}))->get()->toArray();

			$infoMap   = array_column($infoMap, null, 'id');
			$usersInfo = array_column($usersInfo, null, 'user_id');

			$usersInfo[ self::NOTICE_ALL_MEMBER ] = ['real_name' => '所有人'];

			return self::arraySort(collect($result)->map(static function ($query) use ($infoMap, $usersInfo) {
				$info        = $infoMap[ $query->info_id ];
				$uploadMap   = json_decode($info['upload_map'], true);
				$contentDesc = $info['comment_desc'] ?? '';
				$userList    = array_map(static function ($val) use ($usersInfo) {
					return $usersInfo[ $val ] ?? [];
				}, json_decode($info['notice_map'], true));

				$contentArr = explode(self::NOTICE_PLACE_SIGN, $contentDesc);
				//--为前端 拼接 评论内容
				$contentOriginal = [];
				foreach ($userList as $key => $val) {
					if (!$val) {
						continue;
					}
					$contentOriginal[ $key ] = $contentArr[ $key ];
					$contentArr[ $key ]      .= sprintf(self::CONTENT_HTML_SIGN, $val['real_name']);
					$contentOriginal[ $key ] .= '@' . $val['real_name'] . ' ';
				}
				$contentDetail         = implode('', $contentArr);
				$contentOriginalDetail = implode('', $contentOriginal);

				return [
					'comment_id'         => $query->id,
					'card_id'            => $query->card_id,
					'comment_user'       => $usersInfo[ $query->user_id ] ?? [],
					'content'            => $contentOriginalDetail,
					'content_detail'     => $contentDetail,
					'notice_user_info'   => $userList,
					'files'              => $uploadMap ? array_map(static function ($item) {
						return config('app.aliyunCloud.public_host') . '/' . $item;
					}, $uploadMap) : [],
					'created_at'         => strtotime($query->created_at),
					'created_format'     => self::timeTran($query->created_at),
					'comment_place_sign' => self::NOTICE_PLACE_SIGN,
				];
			})->toArray(), 'created_at', SORT_ASC);

		});
	}

}
