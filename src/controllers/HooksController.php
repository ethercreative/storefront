<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\controllers;

use craft\web\Controller;
use ether\storefront\Storefront;
use yii\web\BadRequestHttpException;

/**
 * Class HooksController
 *
 * @author  Ether Creative
 * @package ether\storefront\controllers
 */
class HooksController extends Controller
{

	protected $allowAnonymous = true;
	public $enableCsrfValidation = false;

	/**
	 * @return string
	 * @throws BadRequestHttpException
	 */
	public function actionListen ()
	{
		Storefront::getInstance()->webhook->listen();

		return '';
	}

}