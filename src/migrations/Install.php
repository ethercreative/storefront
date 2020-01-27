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
		// Webhooks
		// ---------------------------------------------------------------------

		$this->createTable('storefront_webhooks', [
			'id' => $this->string()->notNull(),
			'hook' => $this->string()->notNull(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_webhooks}}',
			'id'
		);

		// Relations
		// ---------------------------------------------------------------------

		$this->createTable('storefront_relations', [
			'id' => $this->primaryKey(),
			'shopifyId' => $this->string(),
			'type' => $this->string(),
			'dateCreated' => $this->dateTime(),
			'dateUpdated' => $this->dateTime(),
			'uid' => $this->uid(),
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

		// Checkouts
		// ---------------------------------------------------------------------

		$this->createTable('storefront_checkouts', [
			'id' => $this->string()->notNull(),
			'userId' => $this->integer()->null(),
			'dateCompleted' => $this->dateTime()->null(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_checkouts}}',
			'id'
		);

		$this->addForeignKey(
			null,
			'{{%storefront_checkouts}}',
			'userId',
			'{{%users}}',
			'id',
			'SET NULL'
		);
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropTable('storefront_webhooks');
		$this->dropTable('storefront_relations');
		$this->dropTable('storefront_checkouts');
	}

}