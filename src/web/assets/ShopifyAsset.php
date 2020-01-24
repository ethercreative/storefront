<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\web\assets;

use craft\web\AssetBundle;

/**
 * Class ShopifyAsset
 *
 * @author  Ether Creative
 * @package ether\storefront\web\assets
 */
class ShopifyAsset extends AssetBundle
{

	public function init ()
	{
		$this->sourcePath = __DIR__;

		$this->css = [
			'shopify.css',
		];

		parent::init();
	}

}