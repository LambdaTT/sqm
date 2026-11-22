<?php

namespace Sqm\Seeds;

use SplitPHP\DbManager\Seed;
use SplitPHP\ModsManager;

class SqmPermissions extends Seed
{
  public function apply()
  {
    if (!ModsManager::modExists('iam')) return;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Ler Fila de Atendimento')
      ->onField('ds_tag')->setFixedValue('sqm.entry.read')
    ;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Gerenciar Fila de Atendimento')
      ->onField('ds_tag')->setFixedValue('sqm.entry.manage')
    ;
  }
}
