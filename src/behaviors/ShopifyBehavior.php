<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\behaviors;

use yii\base\Behavior;

/**
 * Class ShopifyBehavior
 *
 * @author  Ether Creative
 * @package ether\storefront\behaviors
 */
class ShopifyBehavior extends Behavior
{

	// Static
	// =========================================================================

	public static $fieldHandles = [
		'shopifyId' => true,
	];

	// Properties
	// =========================================================================
	// Note: These fields are populated by Storefront::onBeforeElementQueryPrepare()

	/** @var string The products Shopify ID */
	public $shopifyId;

}