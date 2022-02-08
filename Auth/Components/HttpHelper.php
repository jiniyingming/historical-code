<?php

namespace Modules\Auth\Components;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HttpHelper
{
	private static $_instance;
	/**
	 * @var \GuzzleHttp\Client
	 */
	private $client;

	private function __construct()
	{
	}

	private function __clone()
	{

	}

	/**
	 * @return \Modules\Auth\Components\HttpHelper
	 */
	public static function client(): HttpHelper
	{
		if (!self::$_instance instanceof self) {
			self::$_instance = new self();
		}
		self::$_instance->client = new Client();

		return self::$_instance;
	}

	/**
	 * @param       $url
	 * @param array $params
	 * @param array $options
	 *
	 * @return array|mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function get($url, array $params = [], array $options = [])
	{
		$url      = $this->buildUrl($url, $params);
		$response = $this->client->request('GET', $url, $options);

		return $this->handleResponse($response);
	}

	/**
	 * @param       $url
	 * @param array $params
	 * @param array $options
	 *
	 * @return array|mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function post($url, array $params = [], array $options = [])
	{
		$response = $this->client->request('POST', $url, array_merge([
			'json'    => $params,
			'headers' => [
				'Content-type' => 'application/json; charset=utf-8',
			],
		], $options));

		return $this->handleResponse($response);

	}

	private function handleResponse($response)
	{
		try {
			return json_decode($response->getBody()->getContents(), true);
		} catch (\Exception $e) {
			Log::error('requestError', [
				'error' => $e->getMessage(),
			]);
		}

		return [];
	}

	public function buildUrl($url, array $params = []): string
	{
		$query = http_build_query($params);
		(strpos($url, '?') !== false) ? $url .= '&' . $query : $url .= '?' . $query;

		return $url;
	}
}