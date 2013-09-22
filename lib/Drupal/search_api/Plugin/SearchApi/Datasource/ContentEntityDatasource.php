<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

/*
 * Include required classes and interfaces.
 */
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Annotation\Datasource;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Datasource\Entity\EntityItem;
use Drupal\search_api\Datasource\Tracker\DefaultTracker;
use Drupal\search_api\Index\IndexInterface;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @Datasource(
 *   id = "search_api_content_entity_datasource",
 *   name = @Translation("Content entity datasource"),
 *   desciption = @Translation("Exposes the content entities as datasource.")
 *   derivative = "Drupal\search_api\Datasource\Entity\ContentEntityDatasourceDerivative"
 * )
 */
class ContentEntityDatasource extends DatasourcePluginBase implements \Drupal\Core\Plugin\ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  private $entityManager;

  /**
   * The entity storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  private $storageController;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $databaseConnection;

  /**
   * Cache which contains already loaded TrackerInterface objects.
   *
   * @var array
   */
  private $trackers;

  /**
   * Create a ContentEntityDatasource object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection to the database.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EntityManager $entity_manager, Connection $connection, array $configuration, $plugin_id, array $plugin_definition) {
    // Initialize the parent chain of objects.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->storageController = $entity_manager->getStorageController($plugin_definition['entity_type']);
    $this->databaseConnection = $connection;
    $this->trackers = array();
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * Get the entity storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageControllerInterface
   *   An instance of EntityStorageControllerInterface.
   */
  protected function getStorageController() {
    return $this->storageController;
  }

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   An instance of Connection.
   */
  protected function getDatabaseConnection() {
    return $this->databaseConnection;
  }

  /**
   * Determine whether the index is valid for this datasource.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the index is valid, otherwise FALSE.
   */
  protected function isValidIndex(IndexInterface $index) {
    // Determine whether the index is compatible with the datasource.
    return $index->getDatasource()->getPluginId() == $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $container->get('entity.manager'),
      $container->get('database'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyInfo() { return array(); /* @todo */ }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    // Load the entity from the storage controller.
    $entity = $this->getStorageController()->load($id);
    // Wrap the entity into a datasource item.
    return $entity ? new EntityItem($entity) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    // Initialize the items variable to an empty array.
    $items = array();
    // Iterate through the loaded entities.
    foreach ($this->getStorageController()->loadMultiple($ids) as $entity_id => $entity) {
      // Wrap the entity into a datasource item and add it to the list.
      $items[$entity_id] = new EntityItem($entity);
    }
    return $items;
  }

  /**
   * Add a datasource tracker.
   *
   * @param \Drupal\search_api\Datasource\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the tracker was added, otherwise FALSE.
   */
  public function addTracker(IndexInterface $index) {
    // The default tracker implementation supports dynamic creation.
    return $this->isValidIndex($index);
  }

  /**
   * Determine whether a datasource tracker for the given index exists.
   *
   * @param \Drupal\search_api\Datasource\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the tracker exists, otherwise FALSE.
   */
  public function hasTracker(IndexInterface $index) {
    // The default tracker implementation supports dynamic creation.
    return $this->isValidIndex($index);
  }

  /**
   * Get a datasource tracker.
   *
   * @param \Drupal\search_api\Datasource\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return \Drupal\search_api\Datasource\Tracker\TrackerInterface|NULL
   *   An instance of TrackerInterface if present, otherwise NULL.
   */
  public function getTracker(IndexInterface $index) {
    // Check if the index is valid.
    if ($this->isValidIndex($index)) {
      // Get the index ID.
      $index_id = $index->id();
      // Check if the tracker is not present in cache.
      if (!isset($this->trackers[$index_id])) {
        // Create a new tracker.
        $this->trackers[$index_id] = new DefaultTracker($index, $this->getDatabaseConnection());
      }
      return $this->trackers[$index_id];
    }
    return NULL;
  }

  /**
   * Delete a datasource tracker.
   *
   * @param \Drupal\search_api\Datasource\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if removed, otherwise FALSE.
   */
  public function deleteTracker(IndexInterface $index) {
    // Check if the index is valid.
    if (($tracker = $this->getTracker($index))) {
      // Clear the tracked items.
      return $tracker->clear();
    }
    return FALSE;
  }

}
