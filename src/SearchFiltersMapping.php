<?php

namespace Drupal\search_filters;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Contains search filter mapping.
 */
class SearchFiltersMapping {

  /**
   * An array of mappings.
   *
   * @var array
   */
  protected array $mapping = [];

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new SearchFilterMapping object.
   *
   * @param array|null $mapping A mapping of config.
   */
  public function __construct(?array $mapping = NULL) {
    $this->config = \Drupal::config('search_filters.settings');

    if (is_null($mapping)) {
      $mapping = $this->config->get('bundle_mapping');
    }

    $this->setMapping($mapping);
  }

  /**
   * Sets the mapping.
   *
   * @param mixed $mapping
   *   The mapping array if set.
   */
  public function setMapping(mixed $mapping = NULL): void {
    $this->mapping = [];

    if (is_array($mapping)) {
      $this->mapping = $mapping;
    }
    elseif (is_string($mapping)) {
      $bundle_mapping = preg_split('/(\r\n|\r|\n)/', $mapping);

      foreach ($bundle_mapping as $line) {
        [$entity_type, $bundle, $values] = explode(':', $line, 3);

        $values = explode('|', $values);

        foreach ($values as $value) {
          $this->mapping[$entity_type][$bundle][] = $value;
        }
      }
    }
  }

  /**
   * Should set filters.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function shouldSetFilters(EntityInterface $entity): bool {
    if (!$entity instanceof FieldableEntityInterface) {
      return FALSE;
    }

    $config = \Drupal::config('search_filters.settings');

    if (empty($config->get('vocabulary')) || empty($config->get('field_name'))) {
      return FALSE;
    }

    $vocabulary = Vocabulary::load($config->get('vocabulary'));

    if (!$vocabulary instanceof VocabularyInterface) {
      return FALSE;
    }

    $field_name = $config->get('field_name');

    if (!$entity->hasField($field_name)) {
      return FALSE;
    }

    if (!array_key_exists($entity->getEntityTypeId(), $this->mapping)) {
      return FALSE;
    }

    return (array_key_exists($entity->bundle(), $this->mapping[$entity->getEntityTypeId()])
      || array_key_exists('*', $this->mapping[$entity->getEntityTypeId()]));
  }

  /**
   * Set filters on entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return void
   */
  public function setFiltersOnEntity(EntityInterface $entity): void {
    if (!$this->shouldSetFilters($entity)) {
      return;
    }

    /** @var FieldableEntityInterface $entity */

    $this->clearFilterValues($entity);

    foreach ([$entity->bundle(), '*'] as $bundle_key) {
      if (array_key_exists($bundle_key, $this->mapping[$entity->getEntityTypeId()])) {
        foreach ($this->mapping[$entity->getEntityTypeId()][$bundle_key] as $term_name) {
          $this->setFilterOnEntity($term_name, $entity);
        }
      }
    }
  }

  /**
   * Clears filter values.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *
   * @return void
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function clearFilterValues(FieldableEntityInterface $entity): void {
    $config = \Drupal::config('search_filters.settings');

    $field_name = $config->get('field_name');

    if ($entity->hasField($field_name)) {
      $entity->get($field_name)->setValue([]);
    }
  }

  /**
   * @param $term_name
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function setFilterOnEntity($term_name, FieldableEntityInterface $entity) {
    if (strpos($term_name, '[') === 0) {
      $name_field = str_replace(['[', ']'], '', $term_name);

      if ($entity->hasField($name_field) && !$entity->get($name_field)->isEmpty()) {
        $field = $entity->get($name_field);
        $term_name = $field->value;
        if ($field->getSetting('allowed_values')) {
          $term_name = $field->getSetting('allowed_values')[$term_name];
        }
      }
    }

    $config = \Drupal::config('search_filters.settings');

    $properties = [
      'vid' => $config->get('vocabulary'),
      'name' => $term_name,
    ];

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);

    $term = reset($terms);

    if (empty($term)) {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create($properties);
      $term->save();
    }

    $field_name = $config->get('field_name');

    $values = $entity->get($field_name)->getValue();

    $exists = FALSE;

    foreach ($values as $item) {
      if ($item['target_id'] == $term->id()) {
        $exists = TRUE;
        break;
      }
    }

    if (!$exists) {
      $values[] = ['target_id' => $term->id()];
    }

    $entity->get($field_name)->setValue($values);
  }
}
