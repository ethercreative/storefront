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

		if ($errors = Storefront::getInstance()->checkout->addLineItem($variantId, $quantity))
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

	public function actionAddLineItems ()
	{
		// TODO: add multiple line items at once
	}

	public function actionUpdateLineItem ()
	{
		$request = Craft::$app->getRequest();

		$id = $request->getRequiredBodyParam('id');
		$quantity = $request->getRequiredBodyParam('quantity');

		if ($errors = Storefront::getInstance()->checkout->updateLineItem($id, $quantity))
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

	public function actionRemoveLineItem ()
	{
		$request = Craft::$app->getRequest();

		$id = $request->getRequiredBodyParam('id');

		if ($errors = Storefront::getInstance()->checkout->removeLineItem($id))
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

}