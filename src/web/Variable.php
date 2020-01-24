<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\web;

use ether\storefront\Storefront;

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

	public function getOptionLabelsFromVariant ($variant)
	{
		$labels = [];

		foreach ($variant['options'] as $option)
			$labels[] = $option['name'];

		return $labels;
	}

}