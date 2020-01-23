<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\web\twig\nodes;

use ether\storefront\Storefront;
use Twig\Compiler;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Node;
use Twig\Node\NodeCaptureInterface;

/**
 * Class ShopifyNode
 *
 * @author  Ether Creative
 * @package ether\storefront\web\twig\nodes
 */
class ShopifyNode extends Node implements NodeCaptureInterface
{

	public function compile (Compiler $compiler)
	{
		/** @var ArrayExpression $data */
		$handle = $this->getAttribute('handle');
		$variables = $this->getAttribute('variables');
		$api = $this->getAttribute('api');
		$query = $this->getNode('query');

		$compiler
			->addDebugInfo($this)
			->write('ob_start();' . PHP_EOL)
			->subcompile($query)
			->write('$context[\'' . $handle . '\'] = ')
			->raw(Storefront::class . '::getInstance()->graph->template(\'' . $api . '\', ')
			->raw('ob_get_clean(), ')
			->raw($variables->compile($compiler) . ');' . PHP_EOL);
	}

}