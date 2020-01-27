<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\controllers;

use Craft;
use craft\web\Controller;
use ether\storefront\Storefront;

/**
 * Class CheckoutController
 *
 * @author  Ether Creative
 * @package ether\storefront\controllers
 */
class CheckoutController extends Controller
{

	protected $allowAnonymous = true;

	public function actionAddLineItem ()
	{
		$request = Craft::$app->getRequest();

		$variantId = $request->getRequiredBodyParam('variantId');
		$quantity = $request->getBodyParam('quantity', 1);

		// TODO: return any errors
		$errors = Storefront::getInstance()->checkout->addLineItem($variantId, $quantity);

		if (!empty($errors))
			Craft::dd($errors);

		return $this->redirectToPostedUrl();
	}

	public function actionAddLineItems ()
	{
		// TODO: add multiple line items at once
	}

	public function actionUpdateLineItem ()
	{
		// TODO: this
	}

	public function actionRemoveLineItem ()
	{
		// TODO: this
	}

}