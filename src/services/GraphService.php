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
use ether\storefront\helpers\ArrayHelper;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\models\Settings;
use ether\storefront\Storefront;
use Exception;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
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

		if ($api !== 'admin')
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
			Craft::parseEnv($this->_settings()->shopifyAdminApiPassword),
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
			Craft::parseEnv($this->_settings()->shopifyStorefrontAccessToken),
			'api/{version}/graphql'
		);

		return $this->_query($client, $query, $variables, $cache);
	}

	// Helpers
	// =========================================================================

	/**
	 * @param string $query
	 * @param array  $variables
	 * @param array  $result
	 *
	 * @return array
	 * @throws SyntaxError
	 * @throws Exception
	 */
	public static function getIdsFromQuery ($query, $variables, $result)
	{
		$ast = Parser::parse($query);
		$ids = [];

		$paths = [];

		Visitor::visit($ast, [
			'leave' => [
				NodeKind::VARIABLE_DEFINITION => function (VariableDefinitionNode $node, $key, $parent, $path) use (&$ids, $variables) {
					$isArray = false;
					$originalNode = $node;

					if ($node->type instanceof NonNullTypeNode)
						$node = $node->type;

					if ($node->type instanceof ListTypeNode)
					{
						$isArray = true;
						$node = $node->type;
					}

					if ($node->type instanceof NamedTypeNode)
						$node = $node->type;

					if ($node->name->value !== 'ID')
						return null;

					$name = $originalNode->variable->name->value;

					if ($isArray) $ids = array_merge($ids, $variables[$name]);
					else $ids[] = $variables[$name];

					return null;
				},
				NodeKind::FIELD => function (FieldNode $node, $key, $parent, $path, $ancestors) use (&$ids, $result, &$paths) {
					if (!array_key_exists('data', $result))
						return null;

					if ($node->name->value !== 'id')
						return null;

					$path = [];

					foreach ($ancestors as $node) {
						if (!($node instanceof FieldNode))
							continue;

						$path[] = $node->alias ? $node->alias->value : $node->name->value;
					}

					if ($node instanceof SelectionSetNode)
						$node = $node->selections[0];

					$path[] = $node->alias ? $node->alias->value : $node->name->value;

					$data = self::_traversePath($path, $result['data']);
					$paths[] = compact('path', 'data', 'result');

					if ($data !== null)
						$ids[] = $data;

					return null;
				},
			],
		]);

		$ids = ArrayHelper::flatten($ids);
		$ids = array_filter($ids);

		foreach ($ids as $i => $id)
			if (strpos($id, 'gid://') === false)
				$ids[$i] = base64_decode($id);

		return array_unique($ids, SORT_STRING);
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

		$shop = $this->_settings()->getShopHandle();
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
		$cache = !preg_match('/^\s*mutation\s*?\w*?\s*?\(/mi', $query) && $cache;
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

		$value = Json::decode($res, true);

		if ($cache)
			CacheHelper::set($key, $value, $query, $variables);

		return $value;
	}

	private static function _traversePath ($path, $data)
	{
		foreach ($path as $i => $step)
		{
			if (!is_array($data) || !array_key_exists($step, $data))
				continue;

			if ($step === 'edges')
			{
				$items = [];
				$pth = array_slice($path, $i + 1);

				foreach ($data[$step] as $item)
					$items[] = self::_traversePath($pth, $item);

				$data = $items;
			}
			else $data = $data[$step];
		}

		return $data;
	}

}