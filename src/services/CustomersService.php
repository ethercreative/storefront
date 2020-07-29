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
use craft\web\User;
use DateTime;
use ether\storefront\enums\ShopifyType;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\Storefront;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\db\Exception;
use yii\web\Cookie;

/**
 * Class CustomersService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class CustomersService extends Component
{

	// Consts
	// =========================================================================

	const AUTH_KEY = 'storefrontAuthToken';

	// Properties
	// =========================================================================

	private $_authToken;

	// Public
	// =========================================================================

	/**
	 * Tie a Craft user to a Shopify customer on customer create or update
	 *
	 * @param array $data
	 *
	 * @throws Exception
	 */
	public function upsert (array $data)
	{
		$storefront = Storefront::getInstance();
		$relations = $storefront->relations;
		$users = Craft::$app->getUsers();

		$id = $relations->getShopifyIdFromArray($data, ShopifyType::Customer);
		$userId = $relations->getElementIdByShopifyId($id);

		if ($userId)
			return;

		$user = $users->getUserByUsernameOrEmail($data['email']);

		if (!$user)
			return;

		$relations->store(
			$id,
			ShopifyType::Customer,
			$user->id
		);
	}

	/**
	 * Delete the Craft User -> Shopify Customer relation
	 *
	 * @param array $data
	 *
	 * @throws Exception
	 */
	public function delete (array $data)
	{
		$relations = Storefront::getInstance()->relations;

		$id = $relations->getShopifyIdFromArray($data, ShopifyType::Customer);
		$relations->remove($id);

		CacheHelper::clearCachesByShopifyId($id, ShopifyType::Customer);
	}

	/**
	 * Get the customer ID for the currently logged in user
	 *
	 * @return string|null
	 */
	public function getCurrentUserCustomerId ()
	{
		$user = Craft::$app->getUser()->getIdentity();

		if (!$user)
			return null;

		return Storefront::getInstance()->relations->getShopifyIdByElementId(
			$user->id,
			ShopifyType::Customer
		);
	}

	// Edit
	// =========================================================================

	/**
	 * @param array $context
	 *
	 * @return null
	 */
	public function addShopifyTab (array &$context)
	{
		$id = $this->_validateAndGetId(@$context['user']);

		if (!$id)
			return null;

		$context['tabs']['storefront-shopify'] = [
			'label' => 'Shopify',
			'url'   => '#storefront-tab-shopify',
			'class' => null,
		];

		return null;
	}

	/**
	 * @param array $context
	 *
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws \yii\base\Exception
	 */
	public function addShopifyDetails (array &$context)
	{
		$id = $this->_validateAndGetId(@$context['user']);

		if (!$id)
			return null;

		return Craft::$app->getView()->renderTemplate(
			'storefront/_customer',
			[
				'id' => $id,
				'visible' => false,
			]
		);
	}

	// Auth
	// =========================================================================

	/**
	 * Log the user in to their Shopify account
	 *
	 * @param string $email
	 * @param string $password
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	public function login ($email, $password)
	{
		$query = <<<GQL
mutation Login (
	\$email: String!
	\$password: String!
) {
	login: customerAccessTokenCreate (input: {
		email: \$email
		password \$password
	}) {
		customerAccessToken {
			accessToken
			expiresAt
		}
		customerUserErrors {
			message
		}
	}
}
GQL;

		$res = Storefront::getInstance()->graph->storefront($query, [
			'email' => $email,
			'password' => $password,
		]);

		if (array_key_exists('errors', $res))
			return $res['errors'];

		if (!empty($res['data']['login']['customerUserErrors']))
			return $res['data']['login']['customerUserErrors'];

		$this->_authToken = $res['data']['login']['customerAccessToken']['accessToken'];
		$expiresAt = $res['data']['login']['customerAccessToken']['expiresAt'];

		$cookie = new Cookie([
			'name' => self::AUTH_KEY,
			'value' => $this->_authToken,
			'expires' => (new DateTime($expiresAt))->getTimestamp(),
		]);
		Craft::$app->getRequest()->getCookies()->add($cookie);

		return null;
	}

	/**
	 * Log the user out from their shopify account
	 */
	public function logout ()
	{
		$token = Craft::$app->getRequest()->getCookies()->getValue(self::AUTH_KEY);

		if ($token)
		{
			$query = <<< GQL
mutation customerAccessTokenDelete(
	\$customerAccessToken: String!
) {
	customerAccessTokenDelete(
		customerAccessToken: \$customerAccessToken
	) {
		deletedAccessToken
	}
}
GQL;

			Storefront::getInstance()->graph->storefront($query, compact('token'));
		}

		Craft::$app->getRequest()->getCookies()->remove(self::AUTH_KEY);
	}

	/**
	 * Checks if the current user is logged in
	 *
	 * @param bool $returnId - Return the ID of the customer
	 *
	 * @return bool|string
	 */
	public function isLoggedIn ($returnId = false)
	{
		$token = Craft::$app->getRequest()->getCookies()->getValue(self::AUTH_KEY);

		if (!$token)
			return false;

		$query = <<<GQL
query GetCustomer (\$token: String!) {
	customer (customerAccessToken: \$token) {
		id
	}
}
GQL;

		$res = Storefront::getInstance()->graph->storefront($query, [
			'token' => $token,
		]);

		if (array_key_exists('errors', $res))
			return false;

		if (empty(@$res['data']['customer']['id']))
			return false;

		return $returnId ? @$res['data']['customer']['id'] : true;
	}

	/**
	 * Returns the customer ID or null
	 *
	 * @return null|string
	 */
	public function getCustomerId ()
	{
		return $this->isLoggedIn(true) ?: null;
	}

	// Helpers
	// =========================================================================

	/**
	 * Validate that the given entry exists and is a Shopify generated entry
	 *
	 * @param User|null $user
	 *
	 * @return string|null
	 */
	private function _validateAndGetId ($user)
	{
		if (!$user)
			return null;

		return Storefront::getInstance()->relations->getShopifyIdByElementId(
			$user->id,
			ShopifyType::Customer
		);
	}

}