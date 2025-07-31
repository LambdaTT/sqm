<?php

namespace Sqm\Commands;

use Exception;
use SplitPHP\Cli;

class Sqm extends Cli
{
  /**
   * Initializes the service queue manager commands.
   */
  public function init()
  {
    $this->addCommand('entry:call', function ($args) {
      $key = $args[0] ?? null;
      if (!$key) throw new Exception('#1 argument (Entry key) is required.');

      $location = $args[1] ?? null;
      if (!$location) throw new Exception('#2 argument (Location) is required.');

      // Call the service to change the status
      $this->getService('sqm/entry')->changeStatus(['ds_key' => $key], 'S', $location);
    });
  }
}
