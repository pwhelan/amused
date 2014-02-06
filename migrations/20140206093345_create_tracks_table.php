<?php

use Phinx\Migration\AbstractMigration;

class CreateTracksTable extends AbstractMigration
{
	/**
	 * Change.
	 */
	public function change()
	{
		// create the table
		$this->table('tracks')
			->addColumn('artist', 'string', array('limit' => 512))
			->addColumn('title', 'string', array('limit' => 512))
			->addColumn('album', 'string', array('limit' => 512, 'default' => 'Unreleased'))
			->addColumn('filename', 'string', array('limit' => 512))
			->addColumn('date', 'integer', array('limit' => 4, 'null' => true))
			->addColumn('bpm',  'integer', array('limit' => 4, 'null' => true))
			->create();
	}
	
	/**
	 * Migrate Up.
	 */
	public function up()
	{

	}

	/**
	 * Migrate Down.
	 */
	public function down()
	{

	}
}
