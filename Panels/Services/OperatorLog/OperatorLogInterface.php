<?php

namespace Modules\Panels\Services\OperatorLog;

interface OperatorLogInterface
{
	public function createOperatorLog();

	public function updateOperatorLog();

	public function deleteOperatorLog();

	public function sortOperatorLog();
}
