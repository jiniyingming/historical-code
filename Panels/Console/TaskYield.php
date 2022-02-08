<?php

namespace Modules\Panels\Console;

use App\Models\Company\CompanyModel;
use App\Models\Company\CompanyUserModel;
use App\Models\LoginToken;
use App\Models\PanelsCardDocModel;
use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;
use App\Models\ProjectDocModel;
use App\Models\ProjectModel;
use App\Models\UserInfoModel;
use App\Models\UserModel;
use App\Services\ContentReviewService;
use App\Services\UserService;
use EasyWeChat\Kernel\Messages\Raw;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Panels\Events\Panels;
use Modules\Panels\Services\PanelsCardService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class TaskYield extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'task-yield';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = '任务看板调试';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @param $newSortVal
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function handle()
	{
		$this->consoleTest();
		$this->generateHeader();
	}

	public const DEAL_BY_UPDATE_CARD = 2;
	public const DEAL_BY_DELETE_CARD = 3;
	public const DEAL_BY_SORT_CARD   = 4;

	/**
	 * @throws Exception
	 */
	private function changeUserStatus($userId, $enable): void
	{
		UserService::clearUsingTokenByUserId($userId);
		UserModel::changeUserStatus($userId, $enable);
	}

	/**
	 * 用户模拟登录信息
	 */
	private function generateHeader(): void
	{

		$userId   = 34632967;
		$platform = 1;

		$getCurrentMillis = static function () {
			[$s1, $s2] = explode(' ', microtime());

			return (float)sprintf('%.0f', ((float)$s1 + (float)$s2) * 1000);
		};
		$getAuthToken     = static function ($id, $platform) {
			$tokenInfo = LoginToken::query()->where([
				'user_id'     => $id,
				'platform'    => $platform,
				'delete_flag' => 1
			])->orderBy('id', 'DESC')->first();
			if ($tokenInfo) {
				$token        = $tokenInfo->token;
				$tokenEncrypt = $tokenInfo->user_info;
			} else {
				$token        = setUserToken(); // 生成token
				$tokenEncrypt = tokenEncrypt("{$id},{$platform}");
				$time         = time();
				LoginToken::updateOrCreate(['user_id' => $id, 'platform' => $platform], [
					'token'           => $token,
					'user_info'       => $tokenEncrypt,
					'expiration_time' => $time + (15 * 86400),
					'avatar'          => 'https://xinyue-public-new.oss-cn-hangzhou.aliyuncs.com/mobile_static_resources/yanse/yanse3.png?x-oss-process=image/resize,w_300,h_300/watermark,type_d3F5LXplbmhlaQ,size_70,text_Sg,color_FFFFFF,shadow_50,t_100,g_center,x_10,y_10',
					'real_name'       => 'jinyingming',
					'delete_flag'     => '1'
				]);
			}

			return $token . '$' . $tokenEncrypt;
		};
		$timestamp        = $getCurrentMillis();
		$appSecret        = config('app.api_param.app_serect');
		$Authorization    = $getAuthToken($userId, $platform);
		$header           = [
			sprintf('timestamp:%s', $timestamp),
			sprintf('appSerectToken:%s', md5($appSecret . $timestamp)),
			sprintf('Authorization:%s', $Authorization),
		];
		$token_sub        = explode('$', $Authorization);
		$userInfo         = explode(',', tokenDecrypt($token_sub[1]));
		$companyInfo      = [];
		if ($userInfo[2] ?? false) {
			$companyData = CompanyModel::query()->where('id', $userInfo[2])->first();
			if ($companyData) {
				$companyInfo = [
					sprintf('企业编号:%s', $companyData->id),
					sprintf('企业名称:%s', $companyData->company_name),
					sprintf('企业域名:%s', $companyData->company_domain)
				];
			}
		}
		if ($companyInfo) {
			echo '================================*****企业信息*****=======================================' . PHP_EOL;
			echo implode(PHP_EOL, $companyInfo) . PHP_EOL;
		}
		$userInfo = UserInfoModel::query()->where('user_id', $userId)->first();
		if ($userInfo) {
			echo PHP_EOL;
			echo '================================*****用户信息*****=======================================' . PHP_EOL;
			$userInfo = [
				sprintf('用户编号:%s', $userInfo->user_id),
				sprintf('用户名称:%s', $userInfo->real_name),
			];
			echo implode(PHP_EOL, $userInfo) . PHP_EOL;
		}
		echo PHP_EOL;
		echo '================================******HEADER******=======================================' . PHP_EOL;
		echo implode(PHP_EOL, $header) . PHP_EOL;
		die;
	}

	/**
	 * @throws Exception
	 * 极端情况下数据模拟
	 */
	private function runData(): void
	{
		//--任务看板极端情况模拟
		$i         = 1;
		$projectId = 463751;
		$docIds    = ProjectDocModel::query()->where(['project_id' => $projectId, 'top_id' => 0])->whereIn('status', [
			2,
			4
		])->where('version_no', 0)->whereNull('deleted_at')->pluck('id')->toArray();

		while ($i <= 40) {
			$item = PanelsItemModel::create([
				'project_id' => $projectId,
				'name'       => '清单序列-' . $i,
				'sort'       => $i,
			]);
			$i++;
			$cardPage = 1;
			while ($cardPage <= 200) {
				$title  = $cardPage . '---->' . $this->getChar(random_int(5, 20));
				$status = random_int(1, 3);
				$card   = PanelsCardModel::create([
					'item_id'    => $item->id,
					'project_id' => $projectId,
					'title'      => $title,
					'leader'     => 34632967,
					'member'     => '',
					'deadline'   => strtotime(date('Y-m-d')) + (86400 * (random_int(0, 60))),
					'start_time' => '',
					'sort'       => $status !== 3 ? (random_int(0, 100) + ((random_int(1, 99) / 1000)) + random_int(0, 100)) : random_int(200, 1000),
					'status'     => $status,
					'priority'   => random_int(1, 3),
					'remark'     => $cardPage . '---->' . $this->getChar(random_int(20, 100)),
				]);
				echo $title . PHP_EOL;
				$cardPage++;
				$insertData = [];
				$docKeyMap  = array_rand($docIds, 100);
				foreach ($docKeyMap as $key) {
					$insertData[] = [
						'project_id' => $projectId,
						'doc_id'     => $docIds[ $key ],
						'card_id'    => $card->id,
					];
				}
				$this->addAll($insertData);
			}

		}
	}

	public function addAll(array $data): bool
	{
		return DB::connection('mysql_project')->table('xy_panels_card_doc')->insert($data);
	}

	/**
	 * 去重排序值
	 */
	private function runRepeatSort(): void
	{
		$card = PanelsCardModel::query()->select(DB::raw('count(sort) as sortNum ,sort,id as card_id,project_id'))->groupBy('sort', 'project_id')->orderBy('sortNum', 'desc')->get()->toArray();
		collect($card)->map(function ($val) use (&$i) {
			if ($val['sortNum'] > 1 || $val['sort'] === 1.0000) {
				$list = PanelsCardModel::query()->where('sort', $val['sort'])->get();
				$list->map(function ($val) {
					$sort      = $this->randomFloat($val['sort'], ($val['sort'] + 1));
					$val->sort = $sort;
					$val->save();
				});
				echo $val['sort'] . '-->' . $val['project_id'] . '--' . $i . PHP_EOL;
				$i--;
			}

			return [];
		});
	}

	public function randomFloat($min = 0, $max = 1, $length = 4): float
	{
		$rand = mt_rand();
		$lmax = mt_getrandmax();

		return round($min + $rand / $lmax * ($max - $min), $length);
	}

	public function getChar($num): string
	{
		$b = '';
		for ($i = 0; $i < $num; $i++) {
			// 使用chr()函数拼接双字节汉字，前一个chr()为高位字节，后一个为低位字节
			$a = chr(mt_rand(0xB0, 0xD0)) . chr(mt_rand(0xA1, 0xF0));
			// 转码
			$b .= iconv('GB2312', 'UTF-8', $a);
		}

		return $b;
	}

	private function consoleTest()
	{


		ContentReviewService::dealContent(3, 367508, 34080648, "https://xinyue-public-new.oss-cn-hangzhou.aliyuncs.com/cover_image/96841958ee09664c0b9a24c45f99bd17-d50468e49d555ea163f95165a4f16076.png");
	}
}
