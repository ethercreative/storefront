<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\services;

use craft\base\Component;
use ether\storefront\Storefront;

/**
 * Class OrdersService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class OrdersService extends Component
{

	public function onCreate (array $data)
	{
		$products = Storefront::getInstance()->products;

		// Clear the caches for all products in the order to ensure our stock
		// is up-to-date
		foreach ($data['line_items'] as $item)
			if ($item['product_exists'])
				$products->clearCaches($item['product_id']);
	}

}