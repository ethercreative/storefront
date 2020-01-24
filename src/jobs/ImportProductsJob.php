<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\jobs;

use Craft;
use craft\queue\BaseJob;
use ether\storefront\services\ProductsService;
use ether\storefront\Storefront;
use Exception;
use Throwable;

/**
 * Class ImportProductsJob
 *
 * @author  Ether Creative
 * @package ether\storefront\jobs
 */
class ImportProductsJob extends BaseJob
{

	protected function defaultDescription ()
	{
		return 'Importing Shopify products';
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 * @throws Throwable
	 */
	public function execute ($queue)
	{
		$graph = Storefront::getInstance()->graph;
		$productsService = Storefront::getInstance()->products;

		$products = [];
		$cursor = null;
		$productFragment = ProductsService::FRAGMENT();

		do {
			$query = <<<GQL
query GetProducts (
	\$cursor: String
	\$collectionLimit: Int
) {
	products (first: 50, after: \$cursor) {
		edges {
			cursor
			node {
				...Product
			}
		}
		pageInfo {
			hasNextPage
		}
	}
}
$productFragment
GQL;

			$res = $graph->admin($query, [
				'cursor' => $cursor,
				'collectionLimit' => 10,
			]);

			if (array_key_exists('errors', $res)) {
				Craft::error($res['errors'], 'storefront');
				throw new Exception('Failed to list products');
			}

			$edges = $res['data']['products']['edges'];

			for ($i = 0, $l = count($edges); $i < $l; $i++)
			{
				$cursor = $edges[$i]['cursor'];
				$products[] = $edges[$i]['node'];
			}

			if (!$res['data']['products']['pageInfo']['hasNextPage'])
				$cursor = null;
		} while ($cursor);

		if (empty($products))
			return;

		$i = 0;
		$total = count($products);

		foreach ($products as $product)
		{
			$productsService->upsert($product);
			$queue->setProgress($i++ / $total * 100);
		}
	}

}