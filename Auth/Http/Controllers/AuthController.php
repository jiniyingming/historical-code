<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Http\Requests\ValidatorRequestTrait;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Services\CallTicketService;

class AuthController extends Controller
{
	use ValidatorRequestTrait;

	/**
	 * @var \Modules\Auth\Services\AuthService
	 */
	protected $callTicketService;
	protected $authService;

	public function __construct(AuthService $authService, CallTicketService $callTicketService)
	{
		$this->authService       = $authService;
		$this->callTicketService = $callTicketService;
	}
}
