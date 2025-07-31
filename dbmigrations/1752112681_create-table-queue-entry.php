<?php

namespace Sqm\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableQueueEntry extends Migration
{
  public function apply()
  {
    /**
     * Creates the SQM_ENTRY table to store service queue entries.
     * This table will hold information about each entry in the service queue,
     * including the client name, professional name, room number, and status.
     */
    $this->Table('SQM_ENTRY')
      ->id('id_sqm_entry')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->string('ds_clientname')->nullable()->setDefaultValue(null)
      ->int('nr_number')->nullable()->setDefaultValue(null)
      ->string('ds_location', 50)->nullable()->setDefaultValue(null)
      ->string('do_status', 1)->setDefaultValue('W')
      ->datetime('dt_summoned')->nullable()->setDefaultValue(null)
      ->datetime('dt_served')->nullable()->setDefaultValue(null)
      ->datetime('dt_canceled')->nullable()->setDefaultValue(null);
  }
}
