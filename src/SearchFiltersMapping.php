<?php

namespace Drupal\search_filters;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

class SearchFiltersMapping {
  protected $mapping = [];
  protected $config;

  public function __construct($mapping = NULL) {
    $this->config = \Drupal::config('search_filters.settings');

    if (is_null($mapping)) {
      $mapping = $this->config->get('bundle_mapping');
    }

    $this->setMapping($mapping);
  }

  public function setMapping($mapping = NULL) {
    $this->mapping = [];

    if (is_array($mapping)) {
      $this->mapping = $mapping;
    } elseif (is_string($mapping)) {
      $bundle_mapping = preg_split('/(\r\n|\r|\n)/', $mapping);

      foreach ($bundle_mapping as $line) {
        list($entity_type, $bundle, $values) = explode(':', $line, 3);

        $values = explode('|', $values);

        foreach ($values as $value) {
          $this->mapping[$entity_type][$bundle][] = $value;
        }
      }
    }
  }

  public function shouldSetFilters(EntityInterface $entity) {
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

  public function setFiltersOnEntity(EntityInterface $entity) {
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

  protected function clearFilterValues(FieldableEntityInterface $entity) {
    $config = \Drupal::config('search_filters.settings');

    $field_name = $config->get('field_name');

    if ($entity->hasField($field_name)) {
      $entity->get($field_name)->setValue([]);
    }
  }

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
