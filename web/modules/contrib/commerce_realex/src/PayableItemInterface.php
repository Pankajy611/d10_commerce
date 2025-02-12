<?php

namespace Drupal\commerce_realex;

/**
 * Represents a thing which can be paid for, in the context of the Website.
 */
interface PayableItemInterface {

  /**
   * Save a PayableItem in the Shared Temp Store.
   *
   * We don't need to save anything about the Payment itself permanently.
   * Items using this class can use the Global Payments Order ID as payment ID.
   *
   * @param string $uuid
   *   The key for the object to save.
   *
   * @return string
   *   The key the object was stored under in the Shared Temp Store.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *
   * @todo ROAD MAP
   *  - abstract some of this to a PermitInterface?
   */
  public function saveTempStore($uuid = NULL);

  /**
   * Retrieve a payable from Shared Temp Store.
   *
   * @param string $uuid
   *   A UUID previously used to store data in the Shared Temp Store.
   *
   * @return \Drupal\commerce_realex\PayableItem
   *   A Global Payments payable item.
   *
   * @todo ROAD-MAP
   *   - formalize this in some interface, e.g. PayableItem
   */
  public static function createFromPaymentTempStore(string $uuid);

  /**
   * Deletes a payable from Shared Temp Store.
   *
   * @param string $uuid
   *   A UUID previously used to store data in the Shared Temp Store.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function deleteFromPaymentTempStore(string $uuid);

  /**
   * Set a value.
   *
   * @param string $key
   *   A field key.
   * @param mixed $value
   *   A value to set.
   */
  public function setValue($key, $value);

  /**
   * General purpose getter.
   *
   * @param string $key
   *   A field key.
   *
   * @return mixed
   *   A value to set.
   */
  public function getValue($key);

  /**
   * Gets the payable amount.
   *
   * @return int
   *   The payable amount.
   *
   * @throws \InvalidArgumentException
   */
  public function getPayableAmount();

  /**
   * Gets the payable currency.
   *
   * @return string
   *   The 3 character payable currency.
   *
   * @throws \InvalidArgumentException
   */
  public function getPayableCurrency();

}
