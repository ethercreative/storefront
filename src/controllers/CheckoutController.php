<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use ether\storefront\Storefront;
use yii\db\Exception;
use yii\web\BadRequestHttpException;

/**
 * Class CheckoutController
 *
 * @author  Ether Creative
 * @package ether\storefront\controllers
 */
class CheckoutController extends Controller
{

	protected $allowAnonymous = true;

	// Line Items
	// =========================================================================

	/**
	 * Adds a line item to the current cart
	 *
	 * @return null
	 * @throws MissingComponentException
	 * @throws Exception
	 * @throws BadRequestHttpException
	 */
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

	/**
	 * Adds multiple line items to the current cart
	 */
	public function actionAddLineItems ()
	{
		// TODO: add multiple line items at once
	}

	/**
	 * Updates the line item on the current cart
	 *
	 * @return null
	 * @throws BadRequestHttpException
	 * @throws Exception
	 * @throws MissingComponentException
	 */
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

	/**
	 * Removes the line item from the current cart
	 *
	 * @return null
	 * @throws BadRequestHttpException
	 * @throws Exception
	 * @throws MissingComponentException
	 */
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

	// Discount Codes
	// =========================================================================

	/**
	 * Will apply the given discount code to the checkout
	 *
	 * @return null
	 * @throws BadRequestHttpException
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function actionApplyDiscountCode ()
	{
		$code = Craft::$app->getRequest()->getRequiredBodyParam('code');

		if ($errors = Storefront::getInstance()->checkout->applyDiscountCode($code))
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

	/**
	 * Removes the discount code from the checkout
	 *
	 * @return null
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function actionRemoveDiscountCode ()
	{
		if ($errors = Storefront::getInstance()->checkout->removeDiscountCode())
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

	// Attributes
	// =========================================================================

	/**
	 * Set the note on the checkout
	 *
	 * @return null
	 * @throws BadRequestHttpException
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function actionSetNote ()
	{
		$note = Craft::$app->getRequest()->getRequiredBodyParam('note');

		if ($errors = Storefront::getInstance()->checkout->setNote($note))
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

	/**
	 * Set the custom attributes on the checkout
	 *
	 * @return null
	 * @throws BadRequestHttpException
	 * @throws Exception
	 * @throws MissingComponentException
	 */
	public function actionSetCustomAttributes ()
	{
		$attributes = Craft::$app->getRequest()->getRequiredBodyParam('attributes');

		if ($errors = Storefront::getInstance()->checkout->setCustomAttributes($attributes))
			Craft::$app->getUrlManager()->setRouteParams([
				'variables' => ['errors' => $errors],
			]);

		return null;
	}

}