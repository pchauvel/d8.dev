<?php

namespace Drupal\search_api_solr\Plugin\DataType;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\TypedData;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api_solr\TypedData\SolrDocumentDefinition;
use Solarium\QueryType\Select\Result\AbstractDocument;

/**
 * Defines the "Solr document" data type.
 *
 * Instances of this class wrap Search API Item objects and allow to deal with
 * items based upon the Typed Data API.
 *
 * @DataType(
 *   id = "solr_document",
 *   label = @Translation("Solr document"),
 *   description = @Translation("Records from a Solr index."),
 *   definition_class = "\Drupal\search_api_solr\TypedData\SolrDocumentDefinition"
 * )
 */
class SolrDocument extends TypedData implements \IteratorAggregate, ComplexDataInterface {

  /**
   * The wrapped Search API Item.
   *
   * @var \Drupal\search_api\Item\ItemInterface|null
   */
  protected $item;

  /**
   * Creates an instance wrapping the given Item.
   *
   * @param \Drupal\search_api\Item\ItemInterface|null $item
   *   The Item object to wrap.
   *
   * @return static
   */
  public static function createFromItem(ItemInterface $item) {
    $server_id = $item->getIndex()->getServerInstance()->id();
    $definition = SolrDocumentDefinition::create($server_id);
    $instance = new static($definition);
    $instance->setValue($item);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->item;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($item, $notify = TRUE) {
    $this->item = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    if (!isset($this->item)) {
      throw new MissingDataException("Unable to get Solr field $property_name as no item has been provided.");
    }

    // First, verify that this field actually exists in the Solr server. If we
    // can't get a definition for it, it doesn't exist.
    /** @var \Drupal\search_api_solr\Plugin\DataType\SolrField $plugin */
    $plugin = \Drupal::typedDataManager()->getDefinition('solr_field')['class'];
    $field_manager = \Drupal::getContainer()->get('solr_field.manager');
    $server_id = $this->item->getIndex()->getServerInstance()->id();
    $fields = $field_manager->getFieldDefinitions($server_id);
    if (empty($fields[$property_name])) {
      throw new \InvalidArgumentException("The Solr field $property_name could not be found on the $server_id server.");
    }
    // Create a new typed data object from the item's field data.
    $property = $plugin::createInstance($fields[$property_name], $property_name, $this);

    // Now that we have the property, try to find its values. We first look at
    // the field values contained in the result item.
    $found = FALSE;
    foreach ($this->item->getFields(FALSE) as $field) {
      if ($field->getDatasourceId() === 'solr_document'
          && $field->getPropertyPath() === $property_name) {
        $property->setValue($field->getValues());
        $found = TRUE;
        break;
      }
    }

    if (!$found) {
      // If that didn't work, maybe we can get the field from the Solr document?
      $document = $this->item->getExtraData('search_api_solr_document');
      if ($document instanceof AbstractDocument
          && isset($document[$property_name])) {
        $property->setValue($document[$property_name]);
      }
    }

    return $property;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value, $notify = TRUE) {
    // Do nothing because we treat Solr documents as read-only.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    // @todo Implement this.
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    // @todo Implement this.
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return !isset($this->item);
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($name) {
    // Do nothing.  Unlike content entities, Items don't need to be notified of
    // changes.
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return isset($this->item) ? $this->item->getIterator() : new \ArrayIterator([]);
  }

}
