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
use craft\errors\MissingComponentException;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\Storefront;
use yii\db\Exception;
use yii\db\Query;

/**
 * Class CheckoutService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class CheckoutService extends Component
{

	// Consts
	// =========================================================================

	const CHECKOUT_KEY = 'storefrontCheckoutId';

	// Properties
	// =========================================================================

	private $_checkoutId;

	// Public
	// =========================================================================

	/**
	 * Gets the current checkout ID (or creates one if none exist)
	 *
	 * @return string
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	public function getCheckoutId ()
	{
		if ($this->_checkoutId)
			return $this->_checkoutId;

		if ($id = Craft::$app->getUser()->getId())
			$this->_checkoutId = (new Query())
				->select('p.shopifyId')
				->from('{{%storefront_relations_to_elements}} p')
				->where(['p.elementId' => $id, 'c.dateCompleted' => null])
				->leftJoin('{{%storefront_checkouts}} c', '[[c.shopifyId]] = [[p.shopifyId]]')
				->scalar();

		// TODO: merge with previously stored, incomplete carts if user is logged in?

		if (!$this->_checkoutId)
		{
			$this->_checkoutId = Craft::$app->getSession()->get(self::CHECKOUT_KEY);

			if ($this->_checkoutId)
			{
				$completed = (new Query())
					->select('id')
					->from('{{%storefront_checkouts}}')
					->where([
						'shopifyId' => base64_decode($this->_checkoutId),
						['!=', 'dateCompleted', null],
					])
					->exists();

				if ($completed)
					$this->_checkoutId = null;
			}
		}

		if ($this->_checkoutId)
			return $this->_checkoutId;

		return $this->_checkoutId = $this->createCheckout();
	}

	/**
	 * Called whenever a checkout is updated
	 *
	 * @param array $data
	 */
	public function onUpdate ($data)
	{
		CacheHelper::clearCheckoutCaches($data['id']);
	}

	/**
	 * Will delete a checkout
	 *
	 * @param array $data
	 *
	 * @throws Exception
	 */
	public function delete ($data)
	{
		CacheHelper::clearCheckoutCaches($data['id']);
		Craft::$app->getDb()->createCommand()
			->delete('{{%storefront_checkouts}}', [
				'shopifyId' => base64_decode($data['id']),
			])->execute();
	}

	// Line Items
	// -------------------------------------------------------------------------

	/**
	 * Will add a single line item to the cart. Returns null on success, or an
	 * array of errors on failure.
	 *
	 * @param string $variantId
	 * @param int $quantity
	 *
	 * @return array|null
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function addLineItem ($variantId, $quantity = 1)
	{
		$query = <<<GQL
mutation AddLineItem (
	\$checkoutId: ID!
	\$lineItems: [CheckoutLineItemInput!]!
) {
	add: checkoutLineItemsAdd (
		checkoutId: \$checkoutId
		lineItems: \$lineItems
	) {
		userErrors {
			message
			field
		}
	}
}
GQL;

		$id = $this->getCheckoutId();
		$res = Storefront::getInstance()->graph->storefront($query, [
			'checkoutId' => $id,
			'lineItems' => [[
				'variantId' => $variantId,
				'quantity' => (int) $quantity,
			]],
		]);

		if (array_key_exists('errors', $res))
			return $res['errors'];

		if (!empty($res['add']['userErrors']))
			return $res['add']['userErrors'];

		$this->onUpdate(['id' => $id]);
		return null;
	}

	/**
	 * Update the qty of the given line item
	 *
	 * @param string $lineItemId
	 * @param int $quantity
	 *
	 * @return array|null
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function updateLineItem ($lineItemId, $quantity)
	{
		$query = <<<GQL
mutation UpdateLineItem (
	\$checkoutId: ID!
	\$lineItems: [CheckoutLineItemUpdateInput!]!
) {
	update: checkoutLineItemsUpdate (
		checkoutId: \$checkoutId
		lineItems: \$lineItems
	) {
		userErrors {
			message
			field
		}
	}
}
GQL;

		$id = $this->getCheckoutId();
		$res = Storefront::getInstance()->graph->storefront($query, [
			'checkoutId' => $id,
			'lineItems' => [[
				'id' => $lineItemId,
				'quantity' => (int) $quantity,
			]],
		]);

		if (array_key_exists('errors', $res))
			return $res['errors'];

		if (!empty($res['update']['userErrors']))
			return $res['update']['userErrors'];

		$this->onUpdate(['id' => $id]);
		return null;
	}

	/**
	 * Removes the given line item from the checkout
	 *
	 * @param string $lineItemId
	 *
	 * @return array|null
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function removeLineItem ($lineItemId)
	{
		$query = <<<GQL
mutation RemoveLineItem (
	\$checkoutId: ID!
	\$lineItemIds: [ID!]!
) {
	remove: checkoutLineItemsRemove (
		checkoutId: \$checkoutId
		lineItemIds: \$lineItemIds
	) {
		userErrors {
			message
			field
		}
	}
}
GQL;

		$id = $this->getCheckoutId();
		$res = Storefront::getInstance()->graph->storefront($query, [
			'checkoutId' => $id,
			'lineItemIds' => [$lineItemId],
		]);

		if (array_key_exists('errors', $res))
			return $res['errors'];

		if (!empty($res['remove']['userErrors']))
			return $res['remove']['userErrors'];

		$this->onUpdate(['id' => $id]);
		return null;
	}

	// Discount Codes
	// -------------------------------------------------------------------------

	/**
	 * Applies the given discount code to the checkout
	 *
	 * @param string $code
	 *
	 * @return null
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function applyDiscountCode ($code)
	{
		$query = <<<GQL
mutation ApplyDiscountCode (
	\$checkoutId: ID!
	\$code: String!
) {
	apply: checkoutDiscountCodeApplyV2 (
		checkoutId: \$checkoutId
		discountCode: \$code
	) {
		checkoutUserErrors {
			message
			field
			code
		}
	}
}
GQL;

		$id = $this->getCheckoutId();
		$res = Storefront::getInstance()->graph->storefront($query, [
			'checkoutId' => $id,
			'code' => $code,
		]);

		if (array_key_exists('errors', $res))
			return $res['errors'];

		if (!empty($res['apply']['checkoutUserErrors']))
			return $res['apply']['checkoutUserErrors'];

		$this->onUpdate(['id' => $id]);
		return null;
	}

	/**
	 * Removes the discount code from the checkout
	 *
	 * @return null
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function removeDiscountCode ()
	{
		$query = <<<GQL
mutation RemoveDiscountCode (
	\$checkoutId: ID!
) {
	remove: checkoutDiscountCodeRemove (
		checkoutId: \$checkoutId
	) {
		checkoutUserErrors {
			message
			field
			code
		}
	}
}
GQL;

		$id = $this->getCheckoutId();
		$res = Storefront::getInstance()->graph->storefront($query, [
			'checkoutId' => $id,
		]);

		if (array_key_exists('errors', $res))
			return $res['errors'];

		if (!empty($res['remove']['checkoutUserErrors']))
			return $res['remove']['checkoutUserErrors'];

		$this->onUpdate(['id' => $id]);
		return null;
	}

	// Private
	// =========================================================================

	/**
	 * Creates a new checkout
	 *
	 * @return string|null
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	private function createCheckout ()
	{
		$mutation = <<<GQL
mutation (\$input: CheckoutCreateInput!) {
	checkoutCreate (input: \$input) {
		checkout {
			id
		}
		checkoutUserErrors {
			message
		}
	}
}
GQL;

		$email = null;
		$user = null;

		if ($user = Craft::$app->getUser()->identity)
			$email = $user->email;

		$res = Storefront::getInstance()->graph->storefront($mutation, [
			'input' => compact('email'),
		]);

		if (array_key_exists('errors', $res))
		{
			Craft::error($res['errors'], 'storefront');
			return null;
		}

		if (!empty($res['data']['checkoutCreate']['checkoutUserErrors']))
		{
			Craft::error($res['data']['checkoutCreate']['checkoutUserErrors'], 'storefront');
			return null;
		}

		$this->_checkoutId = $res['data']['checkoutCreate']['checkout']['id'];
		Craft::$app->getSession()->set(self::CHECKOUT_KEY, $this->_checkoutId);

		$shopifyId = base64_decode($this->_checkoutId);
		$this->store($shopifyId, $user ? $user->id : null);

		return $this->_checkoutId;
	}

	/**
	 * @param string $shopifyId
	 * @param null $elementId
	 *
	 * @throws Exception
	 */
	private function store ($shopifyId, $elementId = null)
	{
		Storefront::getInstance()->relations->store(
			$shopifyId,
			RelationsService::TYPE_CHECKOUT,
			$elementId
		);

		Craft::$app->getDb()->createCommand()
			->insert('{{%storefront_checkouts}}', [
				'shopifyId' => $shopifyId,
			], false)->execute();
	}

}