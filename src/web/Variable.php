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

}