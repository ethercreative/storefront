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
use ether\storefront\enums\ShopifyType;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\Storefront;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\db\Exception;

/**
 * Class CustomersService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class CustomersService extends Component
{

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