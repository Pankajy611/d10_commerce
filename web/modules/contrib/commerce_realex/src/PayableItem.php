<?php

namespace Drupal\commerce_realex;

use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * Represents a Payable Item.
 */
class PayableItem implements PayableItemInterface {

  /**
   * The shared payment temp store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $sharedTempStoreFactory;

  /**
   * Array of key:value.
   *
   * @var array
   *
   * @todo Use separate class members instead of an array?
   *
   * @todo ROAD MAP
   *  - abstract this to a PayableItemInterface?
   */
  protected $values;

  /**
   * Constructs a new PayableItem.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The shared temporary storage factory.
   */
  public function __construct(SharedTempStoreFactory $temp_store_factory) {
    $this->sharedTempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTempStore($uuid = NULL) {
    $store = $this->sharedTempStoreFactory->get('commerce_realex');

    $uuid_service = \Drupal::service('uuid');
    if (!$uuid) {
      $uuid = $uuid_service->generate();
    }

    // Build temporary storage object from essential data.
    $storage_data = [
      'class' => __CLASS__,
      'values' => $this->values,
    ];

    // Save it to temp store under the UUID "payment object" key.
    $store->set($uuid, $storage_data);

    return $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromPaymentTempStore(string $uuid) {
    $payable = new static(\Drupal::service('tempstore.shared'));

    $temp_item = $payable->sharedTempStoreFactory
      ->get('commerce_realex')->get($uuid);

    // @todo validate that the $temp_item['class'] matches __CLASS__
    foreach ($temp_item['values'] as $key => $value) {
      $payable->setValue($key, $value);
    }

    return $payable;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromPaymentTempStore(string $uuid) {
    $store = $this->sharedTempStoreFactory->get('commerce_realex');
    $store->delete($uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($key, $value) {
    // @todo validation?
    $this->values[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($key) {
    return $this->values[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function getPayableAmount() {
    // @todo Could this be more robust by looking up the permit type (GUID)?
    if (isset($this->values['payable_amount'])) {
      return round((float) $this->values['payable_amount'], 2);
    }

    throw new \InvalidArgumentException('Amount not set.');
  }

  /**
   * {@inheritdoc}
   */
  public function getPayableCurrency() {
    if (isset($this->values['payable_currency'])) {
      return $this->values['payable_currency'];
    }

    throw new \InvalidArgumentException('Currency not set.');
  }

}
