<?php

namespace ServiceQueue\Routes;

use SplitPHP\Request;
use SplitPHP\WebService;

/**
 * Class ServiceQueue
 * @package ServiceQueue\Routes
 *
 * This class defines the API endpoints for managing the service queue manager system.
 */
class ServiceQueue extends WebService
{
  /**
   * Initializes the service queue.
   */
  public function init(): void
  {
    $this->setAntiXsrfValidation(false);

    $this->addEndpoint('GET', '/v1/current-queue', function (Request $request) {
      $params = $request->getBody();
      $data = $this->getService('servicequeue/entry')->getQueue($params);

      return $this->response
        ->withStatus(200)
        ->withData($data);
    });

    $this->addEndpoint('GET', '/v1/entry', function (Request $request) {
      $params = $request->getBody();
      $data = $this->getService('servicequeue/entry')->list($params);

      return $this->response
        ->withStatus(200)
        ->withData($data);
    });

    $this->addEndpoint('POST', '/v1/entry', function (Request $request) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'SQM_ENTRY' => 'C'
      ]);

      $clientName = $request->getBody()['clientName'] ?? null;

      $result = $this->getService('servicequeue/entry')->create($clientName);

      return $this->response
        ->withStatus(201)
        ->withData($result);
    });

    $this->addEndpoint('PUT', '/v1/change-status/?entryKey?/?status?', function (Request $request) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'SQM_ENTRY' => 'U'
      ]);

      $params = $request->getRoute()->params;
      $body = $request->getBody();

      $rows = $this->getService('servicequeue/entry')->changeStatus(
        params: ['ds_key' => $params['entryKey']],
        status: $params['status'],
        location: $body['location'] ?? null
      );

      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });
  }
}
