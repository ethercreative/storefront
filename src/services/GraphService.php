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
	 *
	 * @return array
	 * @throws Exception
	 */
	public function template ($api, $query, $variables = [])
	{
		if (preg_match('/^\s*mutation\s*?\w*?\s*?\(/mi', $query))
			throw new Exception('Mutations are not allowed in template queries!');

		if ($api !== 'admin' && $api !== 'storefront')
			$api = 'storefront';

		return $this->$api($query, $variables);
	}

	/**
	 * Query the Admin API
	 *
	 * @param string $query
	 * @param array  $variables
	 *
	 * @return array
	 */
	public function admin ($query, $variables = [])
	{
		$client = $this->_client(
			'X-Shopify-Access-Token',
			$this->_settings()->shopifyAdminApiPassword,
			'admin/api/{version}/graphql.json'
		);

		return $this->_query($client, $query, $variables);
	}

	/**
	 * Query the Storefront API
	 *
	 * @param string $query
	 * @param array  $variables
	 *
	 * @return mixed
	 */
	public function storefront ($query, $variables = [])
	{
		$client = $this->_client(
			'X-Shopify-Storefront-Access-Token',
			$this->_settings()->shopifyStorefrontAccessToken,
			'api/{version}/graphql'
		);

		return $this->_query($client, $query, $variables);
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
				'Forwarded-For' => $request->getUserIP(),
				'X-Forwarded-For' => $request->getUserIP(),
			],
		]);
	}

	private function _query (Client $client, $query, $variables = [])
	{
		$body = [ 'query' => $query ];

		if (!empty($variables))
			$body['variables'] = $variables;

		$res = $client->post('', [
			'body' => Json::encode($body),
		])->getBody()->getContents();

		return Json::decode($res, true);
	}

}