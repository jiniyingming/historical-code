<?php

namespace Modules\Auth\Console;

use Illuminate\Console\Command;
use Modules\Auth\Services\common\AuthConstMap;
use Modules\Auth\Services\ThirdLogin\FlyBook\FlyBookDriver;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FlyTicketPushAgain extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'pushTicket:flyBook';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description.';

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
	 * @return mixed
	 */
	public function handle()
	{

		$data = FlyBookDriver::client(config('auth.driver.' . AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK))->noticePushAgain();
		print_r($data);
	}

}
