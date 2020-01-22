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

	public function admin ($query, $variables = [])
	{
		$client = $this->_client(
			'X-Shopify-Access-Token',
			$this->_settings()->shopifyAdminApiPassword,
			'admin/api/{version}/graphql.json'
		);

		return $this->_query($client, $query, $variables);
	}

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

		return $client[$tokenKey] = Craft::createGuzzleClient([
			'base_uri' => "https://{$shop}.myshopify.com/{$endPoint}",
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				$tokenKey => $token,
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