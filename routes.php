<?php

namespace Sqm\Routes;

use SplitPHP\Request;
use SplitPHP\WebService;
use SplitPHP\Exceptions\Unauthorized;

/**
 * Class Sqm
 * @package Sqm\Routes
 *
 * This class defines the API endpoints for managing the service queue manager system.
 */
class Sqm extends WebService
{
  /**
   * Initializes the service queue.
   */
  public function init()
  {
    $this->setAntiXsrfValidation(false);

    $this->addEndpoint('GET', '/v1/current-queue', function (Request $request) {
      $params = $request->getBody();
      $data = $this->getService('sqm/entry')->getQueue($params);

      return $this->response
        ->withStatus(200)
        ->withData($data);
    });

    $this->addEndpoint('GET', '/v1/entry', function (Request $request) {
      $params = $request->getBody();
      $data = $this->getService('sqm/entry')->list($params);

      return $this->response
        ->withStatus(200)
        ->withData($data);
    });

    $this->addEndpoint('POST', '/v1/entry', function (Request $request) {
      $this->auth([
        'SQM_ENTRY' => 'C'
      ]);

      $clientName = $request->getBody()['clientName'] ?? null;

      $result = $this->getService('sqm/entry')->create($clientName);

      return $this->response
        ->withStatus(201)
        ->withData($result);
    });

    $this->addEndpoint('PUT', '/v1/change-status/?entryKey?/?status?', function (Request $request) {
      $this->auth([
        'SQM_ENTRY' => 'U'
      ]);

      $params = $request->getRoute()->params;
      $body = $request->getBody();

      $rows = $this->getService('sqm/entry')->changeStatus(
        params: ['ds_key' => $params['entryKey']],
        status: $params['status'],
        location: $body['location'] ?? null
      );

      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });
  }

  private function auth(array $permissions)
  {
    if (!$this->getService('modcontrol/control')->moduleExists('iam')) return;

    // Auth user login:
    if (!$this->getService('iam/session')->authenticate())
      throw new Unauthorized("Não autorizado.");

    // Validate user permissions:
    $this->getService('iam/permission')
      ->validatePermissions($permissions);
  }
}
