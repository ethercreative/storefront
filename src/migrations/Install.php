<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\migrations;

use craft\db\Migration;

/**
 * Class Install
 *
 * @author  Ether Creative
 * @package ether\storefront\migrations
 */
class Install extends Migration
{

	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		$this->createTable('storefront_webhooks', [
			'id' => $this->string()->notNull(),
			'hook' => $this->string()->notNull(),
		]);

		$this->addPrimaryKey(
			'storefront_webhooks_pk',
			'{{%storefront_webhooks}}',
			'id'
		);

		$this->createTable('storefront_relations', [
			'id' => $this->primaryKey(),
			'shopifyId' => $this->string(),
			'type' => $this->string(),
			'createdAt' => $this->dateTime(),
			'updatedAt' => $this->dateTime(),
		]);

		$this->addForeignKey(
			null,
			'{{%storefront_relations}}',
			'id',
			'{{%elements}}',
			'id',
			'CASCADE'
		);

		$this->createIndex(
			null,
			'{{%storefront_relations}}',
			'type',
			false
		);
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropTable('storefront_webhooks');
		$this->dropTable('storefront_relations');
	}

}