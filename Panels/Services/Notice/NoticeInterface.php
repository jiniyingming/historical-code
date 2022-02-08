<?php

namespace Modules\Panels\Services\Notice;

interface NoticeInterface
{
	public function createNotice();

	public function updateNotice();

	public function deleteNotice();

	public function sortNotice();
}
