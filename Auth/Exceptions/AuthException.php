<?php

namespace Modules\Auth\Exceptions;

use Language\Status;
use Log;
use RuntimeException;
use Throwable;

class AuthException extends RuntimeException
{

	public function __construct($message = "", int $code = Status::WARING_NOTICE, ?Throwable $previous = null)
	{
		$code < 1 && $code = Status::WARING_NOTICE;
		Log::channel('thirdCall')->warning('RuntimeException', [
			'msg'   => $message,
			'trace' => $previous ? $previous->getTraceAsString() : '',
		]);
		parent::__construct($message, $code, $previous);
	}

}