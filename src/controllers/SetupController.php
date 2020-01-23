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
use ether\storefront\jobs\ImportProductsJob;
use ether\storefront\Storefront;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class SetupController
 *
 * @author  Ether Creative
 * @package ether\storefront\controllers
 */
class SetupController extends Controller
{

	/**
	 * @return Response
	 * @throws BadRequestHttpException
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	public function actionWebhooks ()
	{
		Storefront::getInstance()->webhook->install();
		return $this->redirectToPostedUrl();
	}

	/**
	 * @return Response
	 * @throws BadRequestHttpException
	 * @throws MissingComponentException
	 */
	public function actionImport ()
	{
		Craft::$app->getQueue()->push(new ImportProductsJob());
		Craft::$app->getSession()->setNotice('Queued import task');
		return $this->redirectToPostedUrl();
	}

}