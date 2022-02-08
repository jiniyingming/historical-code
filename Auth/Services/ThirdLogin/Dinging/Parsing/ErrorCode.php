<?php

namespace Modules\Auth\Services\ThirdLogin\Dinging\Parsing;

class ErrorCode
{
	public static $OK = 0;

	public static $IllegalAesKey          = 900004;
	public static $ValidateSignatureError = 900005;
	public static $ComputeSignatureError  = 900006;
	public static $EncryptAESError        = 900007;
	public static $DecryptAESError        = 900008;
	public static $ValidateSuiteKeyError  = 900010;
}