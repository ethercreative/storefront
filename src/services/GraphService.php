<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\models\Settings;
use ether\storefront\Storefront;
use Exception;
use GuzzleHttp\Client;

/**
 * Class GraphService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class GraphService extends Component
{

	private static $API_VERSION = '2020-01';

	// Public
	// =========================================================================

	/**
	 * Query function used in twig
	 *
	 * @param string $api
	 * @param string $query
	 * @param array  $variables
	 * @param bool   $cache
	 *
	 * @return array
	 * @throws Exception
	 */
	public function template ($api, $query, $variables = [], $cache = false)
	{
		if (preg_match('/^\s*mutation\s*?\w*?\s*?\(/mi', $query))
			throw new Exception('Mutations are not allowed in template queries!');

		if ($api !== 'admin' && $api !== 'storefront')
			$api = 'storefront';

		return $this->$api($query, $variables, $cache);
	}

	/**
	 * Query the Admin API
	 *
	 * @param string $query
	 * @param array  $variables
	 * @param bool   $cache
	 *
	 * @return array
	 */
	public function admin ($query, $variables = [], $cache = false)
	{
		$client = $this->_client(
			'X-Shopify-Access-Token',
			$this->_settings()->shopifyAdminApiPassword,
			'admin/api/{version}/graphql.json'
		);

		return $this->_query($client, $query, $variables, $cache);
	}

	/**
	 * Query the Storefront API
	 *
	 * @param string $query
	 * @param array  $variables
	 * @param bool   $cache
	 *
	 * @return mixed
	 */
	public function storefront ($query, $variables = [], $cache = false)
	{
		$client = $this->_client(
			'X-Shopify-Storefront-Access-Token',
			$this->_settings()->shopifyStorefrontAccessToken,
			'api/{version}/graphql'
		);

		return $this->_query($client, $query, $variables, $cache);
	}

	// Private
	// =========================================================================

	/**
	 * @return Settings
	 */
	private function _settings ()
	{
		return Storefront::getInstance()->getSettings();
	}

	private function _client ($tokenKey, $token, $endPoint)
	{
		static $client = [];

		if (@$client[$tokenKey])
			return $client[$tokenKey];

		$shop = $this->_settings()->shopHandle;
		$endPoint = str_replace('{version}', self::$API_VERSION, $endPoint);

		$request = Craft::$app->getRequest();

		return $client[$tokenKey] = Craft::createGuzzleClient([
			'base_uri' => "https://{$shop}.myshopify.com/{$endPoint}",
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				$tokenKey => $token,
				'User-Agent' => $request->getUserAgent(),
				'Forwarded' => 'for=' . $request->getUserIP(),
				'X-Forwarded-For' => $request->getUserIP(),
			],
		]);
	}

	private function _query (Client $client, $query, $variables = [], $cache = false)
	{
		$key = null;

		if ($cache)
		{
			$key = CacheHelper::keyFromQuery($query, $variables);
			$cached = CacheHelper::get($key);

			if ($cached)
				return $cached;
		}

		$body = [ 'query' => $query ];

		if (!empty($variables))
			$body['variables'] = $variables;

		$res = $client->post('', [
			'body' => Json::encode($body),
		])->getBody()->getContents();

		// TODO: if cache is true:
		//  - [x] Cache variables & result by the query if not cached
		//  - [x] Return cached result if cached
		//  - [x] If variables have changed, break the cache
		//  - [x] Hook into our CacheHelper functions to break the cache if the
		//        ID appears in the variables
		//  - [ ] Add option to Clear Caches utility to clear all graph caches

		$value = Json::decode($res, true);

		if ($cache)
			CacheHelper::set($key, $value, $variables);

		return $value;
	}

}