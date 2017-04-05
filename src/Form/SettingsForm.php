<?php

namespace Drupal\search_filters\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_filters_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('search_filters.settings')
      ->set('bundle_mapping', $form_state->getValue('bundle_mapping'))
      ->set('vocabulary', $form_state->getValue('vocabulary'))
      ->set('field_name', $form_state->getValue('field_name'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['search_filters.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('search_filters.settings');

    $form['information'] = [
      '#markup' => '<p>After saving these settings, any affected entities will need to be re-saved.</p>',
    ];

    $form['vocabulary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filters vocabulary'),
      '#description' => $this->t('The taxonomy vocabulary ID to use for the search filters.'),
      '#default_value' => $config->get('vocabulary'),
    ];

    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The name of the term reference field to store the filter values in.'),
      '#default_value' => $config->get('field_name'),
    ];

    $form['bundle_mapping'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bundle mapping'),
      '#description' => $this->t('A list of mappings in the format "entity_type:bundle:Term Name", one per line. Bundle can be * for a wildcard. Term name can also be in the format [field_name] to use a field from the entity as the term.'),
      '#default_value' => $config->get('bundle_mapping'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
