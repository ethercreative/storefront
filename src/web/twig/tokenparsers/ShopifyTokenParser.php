<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\web\twig\tokenparsers;

use ether\storefront\web\twig\nodes\ShopifyNode;
use Exception;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;

/**
 * Class ShopifyTokenParser
 *
 * @author  Ether Creative
 * @package ether\storefront\web\twig\tokenparsers
 */
class ShopifyTokenParser extends AbstractTokenParser
{

	/**
	 * Parses a token and returns a node.
	 *
	 * @param Token $token
	 *
	 * @return Node
	 * @throws SyntaxError
	 */
	public function parse (Token $token)
	{
		$parser = $this->parser;
		$lineNo = $token->getLine();
		$stream = $parser->getStream();
		$expressionParser = $parser->getExpressionParser();
		$nodes = [
			'value' => new Node(),
		];
		$attributes = [
			'handle' => 'shopify',
			'variables' => new ArrayExpression([], $lineNo),
			'api' => 'storefront',
			'cache' => false,
		];

		// Get the variable name
		$attributes['handle'] = $stream->getCurrent()->getValue();
		$stream->next();

		// Do we have variables?
		if ($stream->nextIf(Token::NAME_TYPE, 'with'))
			$attributes['variables'] =
				$expressionParser->parseHashExpression();

		// Are we defining the API type?
		if ($stream->nextIf(Token::NAME_TYPE, 'as'))
		{
			$attributes['api'] = $stream->getCurrent()->getValue();
			$stream->next();
		}

		// Should we cache the response?
		if ($stream->nextIf(Token::OPERATOR_TYPE, 'and'))
			if ($stream->nextIf(Token::NAME_TYPE, 'cache'))
				$attributes['cache'] = true;

		// Capture the contents
		$stream->expect(Token::BLOCK_END_TYPE);
		$nodes['query'] = $parser->subparse([$this, 'decideBlockEnd'], true);

		// Close out the tag
		$stream->expect(Token::BLOCK_END_TYPE);

		return new ShopifyNode(
			$nodes,
			$attributes,
			$lineNo,
			$this->getTag()
		);
	}

	/**
	 * Gets the tag name associated with this token parser.
	 *
	 * @return string The tag name
	 */
	public function getTag ()
	{
		return 'shopify';
	}

	/**
	 * @param Token $token
	 *
	 * @return bool
	 */
	public function decideBlockEnd (Token $token)
	{
		return $token->test('end' . $this->getTag());
	}

}