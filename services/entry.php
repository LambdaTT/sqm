<?php

namespace ServiceQueue\Services;

use Exception;
use SplitPHP\Exceptions\BadRequest;
use SplitPHP\Exceptions\NotFound;
use SplitPHP\Exceptions\FailedValidation;
use SplitPHP\Service;

/**
 * Class Entry
 * @package ServiceQueue\Services
 *
 * This class handles the creation of service queue entries.
 */
class Entry extends Service
{
  private const NEW_CALLS = 2;
  private const NEW_DATA = 1;
  private const NOTHING_NEW = 0;

  /**
   * Creates a new service queue entry.
   *
   * @param array $data The data for the new entry.
   * @return object The result of the insert operation.
   * @throws Exception If required fields are missing or if there is an error during insertion.
   */
  public function create(?string $clientName = null): object
  {
    $data = [];
    $data['nr_number'] = null;
    if (empty($clientName)) {
      $lastToday = $this->getDao('SQM_ENTRY')
        ->first(
          "SELECT 
              nr_number 
            FROM `sqm_entry` 
            WHERE DATE(dt_created) = CURDATE() 
            ORDER BY id_sqm_entry DESC LIMIT 1"
        );

      if (empty($lastToday)) $data['nr_number'] = 1; // Start from 1 if no entries today
      else $data['nr_number'] = $lastToday->nr_number + 1; // Increment the last number
    } else {
      $data['ds_clientname'] = $clientName;
    }

    // Generate a unique key for the entry
    $data['ds_key'] = 'sqm-' . uniqid();
    $data['ds_location'] = null;

    $this->getService('multitenancy/stash')->set('queueFlag', self::NEW_DATA);

    // Insert the entry into the database
    return $this->getDao('SQM_ENTRY')->insert($data);
  }

  /**
   * Changes the status of a service queue entry.
   *
   * @param array $params The parameters to identify the entry.
   * @param string $status The new status to set.
   * @return int The number of affected rows.
   * @throws Exception If the status is invalid or if the entry is not found.
   */
  public function changeStatus($params, $status, $location = null): int
  {
    $statusFields = [
      'S' => 'dt_summoned',
      'D' => 'dt_served',
      'C' => 'dt_canceled'
    ];

    if (!array_key_exists($status, $statusFields))
      throw new BadRequest('Status inválido.');

    $entry = $this->get($params);
    if (empty($entry))
      throw new NotFound('Entrada não encontrada.');

    if ($entry->do_status == 'D' || $entry->do_status == 'C')
      throw new FailedValidation('A entrada já foi finalizada ou cancelada.');

    $data = [];
    if ($status == 'S') {
      if (empty($location))
        throw new BadRequest('Insira o nome do profissional ou o local.');

      $data['ds_location'] = $location;
      $this->getService('multitenancy/stash')->set('queueFlag', self::NEW_CALLS);
    } else {
      $this->getService('multitenancy/stash')->set('queueFlag', self::NEW_DATA);
    }

    $data[$statusFields[$status]] = date('Y-m-d H:i:s');
    $data['do_status'] = $status;

    return $this->getDao('SQM_ENTRY')
      ->bindParams($params)
      ->update($data);
  }

  /**
   * Retrieves a service queue entry based on the provided parameters.
   *
   * @param array $params The parameters to identify the entry.
   * @return object|null The entry object if found, null otherwise.
   */
  public function get($params): ?object
  {
    return $this->getDao('SQM_ENTRY')
      ->bindParams($params)
      ->first();
  }

  /**
   * Lists all service queue entries for today.
   *
   * @param array $params Optional parameters to filter the entries.
   * @return array An array of service queue entries.
   */
  public function list($params = []): array
  {
    if (!isset($params['$sort_by'])) {
      $params['$sort_by'] = 12; // Default sort by dtLastCall
      $params['$sort_direction'] = 'DESC';
    }

    return $this->getDao('SQM_ENTRY')
      ->bindParams($params)
      ->find(
        "SELECT *,
          CASE do_status
            WHEN 'W' THEN 'Aguardando'
            WHEN 'S' THEN 'Convocado'
            WHEN 'D' THEN 'Atendido'
            WHEN 'C' THEN 'Cancelado'
          END AS statusText,
          CASE do_status
            WHEN 'W' THEN NULL
            WHEN 'S' THEN dt_summoned
            WHEN 'D' THEN dt_served
            WHEN 'C' THEN dt_canceled
          END AS dtLastCall,
          CASE do_status
            WHEN 'W' THEN NULL
            WHEN 'S' THEN DATE_FORMAT(dt_summoned, '%d/%m/%Y %H:%i')
            WHEN 'D' THEN DATE_FORMAT(dt_served, '%d/%m/%Y %H:%i')
            WHEN 'C' THEN DATE_FORMAT(dt_canceled, '%d/%m/%Y %H:%i')
          END AS dtLastCallText
          FROM `SQM_ENTRY`
          WHERE DATE(dt_created) = CURDATE()
          ORDER BY dtLastCall DESC"
      );
  }

  /**
   * Retrieves all service queue entries.
   *
   * @return array An array of all service queue entries.
   */
  public function getQueue($params = []): object
  {
    // Wait for new data to be available:
    $this->getService('utils/misc')->waitFor(fn() => (int) $this->getService('multitenancy/stash')->get('queueFlag', 0) > 0, 300);

    // Retrieve the queue entries and flag status:
    $result = (object)[
      'flag' => (int) $this->getService('multitenancy/stash')->get('queueFlag', 0),
      'entries' => $this->list($params),
    ];

    // Reset the queue flag to indicate no new data:
    $this->getService('multitenancy/stash')->set('queueFlag', self::NOTHING_NEW);
    return $result;
  }
}
