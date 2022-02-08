<?php

namespace Modules\Panels\Services\Execute;

use App\Models\PanelsCardModel;
use App\Models\PanelsItemModel;

/**
 *
 * @startuml
 * OperatorAbstract -> __construct: PanelsItemModel $panelsItemModel, PanelsCardModel $panelsCardModel!
 * OperatorAbstract -> create: array $params, int $userId, object $systemInfo!
 * OperatorAbstract -> update: array $params, int $userId, object $systemInfo!
 * OperatorAbstract -> delete: array $params, int $userId, object $systemInfo!
 * OperatorAbstract -> sort: array $params, int $userId, object $systemInfo!
 * OperatorAbstract -> dealOther: int $type, array $parameterData, int $userId, object $systemInfo!
 *
 * @enduml
 *
 */
abstract class OperatorAbstract
{
	protected $panelsItemModel;
	protected $panelsCardModel;

	/**
	 * @param PanelsItemModel $panelsItemModel
	 * @param PanelsCardModel $panelsCardModel
	 * 注入主要数据对象
	 */
	public function __construct(PanelsItemModel $panelsItemModel, PanelsCardModel $panelsCardModel)
	{
		$this->panelsItemModel = $panelsItemModel;
		$this->panelsCardModel = $panelsCardModel;
	}

	/**
	 * @param array  $params     入参数据
	 * @param int    $userId     操作人
	 * @param object $systemInfo 系统信息： 用户信息 项目信息 项目关系信息
	 *
	 * @return mixed
	 * 创建操作
	 */
	abstract public function create(array $params, int $userId, object $systemInfo);

	/**
	 * @param array  $params     入参数据
	 * @param int    $userId     操作人
	 * @param object $systemInfo 公共对象
	 *
	 * @return mixed
	 * 更新操作
	 */
	abstract public function update(array $params, int $userId, object $systemInfo);

	/**
	 * @param array  $params     入参数据
	 * @param int    $userId     操作人
	 * @param object $systemInfo 公共对象
	 *
	 * @return mixed
	 * 删除操作
	 */
	abstract public function delete(array $params, int $userId, object $systemInfo);

	/**
	 * @param array  $params     入参数据
	 * @param int    $userId     操作人
	 * @param object $systemInfo 公共对象
	 *
	 * @return mixed
	 * 排序操作
	 */
	abstract public function sort(array $params, int $userId, object $systemInfo);

	/**
	 * @param int    $type          动作类型
	 * @param array  $parameterData 应用对象
	 * @param int    $userId        操作人
	 * @param object $systemInfo    公共对象
	 *
	 * @return mixed
	 * 其他关联任务 统一处理
	 */
	abstract protected function dealOther(int $type, array $parameterData, int $userId, object $systemInfo);
}
