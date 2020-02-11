<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\web;

use craft\errors\MissingComponentException;
use ether\storefront\Storefront;
use yii\db\Exception;

/**
 * Class Variable
 *
 * @author  Ether Creative
 * @package ether\storefront
 */
class Variable
{

	public function getShopUrl ()
	{
		$shop = Storefront::getInstance()->getSettings()->shopHandle;

		return 'https://' . $shop . '.myshopify.com/';
	}

	public function getProductEditUrl ($id)
	{
		$id = str_replace('gid://shopify/product/', '', strtolower($id));

		return $this->getShopUrl() . 'admin/products/' . $id;
	}

	public function getVariantEditUrl ($productId, $id)
	{
		$id = str_replace('gid://shopify/productvariant/', '', strtolower($id));

		return $this->getProductEditUrl($productId) . '/variants/' . $id;
	}

	public function getCollectionEditUrl ($id)
	{
		$id = str_replace('gid://shopify/collection/', '', strtolower($id));

		return $this->getShopUrl() . 'admin/collections/' . $id;
	}

	public function getCustomerEditUrl ($id)
	{
		$id = str_replace('gid://shopify/customer/', '', strtolower($id));

		return $this->getShopUrl() . 'admin/customers/' . $id;
	}

	public function getOrderEditUrl ($id)
	{
		$id = str_replace('gid://shopify/order/', '', strtolower($id));

		return $this->getShopUrl() . 'admin/orders/' . $id;
	}

	public function getOptionLabelsFromVariant ($variant)
	{
		$labels = [];

		foreach ($variant['options'] as $option)
			$labels[] = $option['name'];

		return $labels;
	}

	/**
	 * @return string
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	public function getCheckoutId ()
	{
		return Storefront::getInstance()->checkout->getCheckoutId();
	}

	public function getCustomerId ()
	{
		return Storefront::getInstance()->customers->getCurrentUserCustomerId();
	}

}