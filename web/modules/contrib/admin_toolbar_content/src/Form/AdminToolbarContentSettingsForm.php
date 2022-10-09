<?php

namespace Drupal\admin_toolbar_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminToolbarContentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_toolbar_content';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'admin_toolbar_content.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('admin_toolbar_content.settings');

    $form['#title'] = $this->t('Admin Toolbar Content');
    $form['#tree'] = true;

    $form['recent_items'] = [
      '#type' => 'textfield',
      '#title' => 'Show recent content',
      '#description' => 'Show recent content items. Leave empty or 0 to show none.',
      '#default_value' => $config->get('recent_items') ?? 10
    ];

    $form['hide_non_content_items'] = [
      '#type' => 'checkbox',
      '#title' => 'Hide non content items',
      '#description' => 'Hides items under "content" not directly related to content types.',
      '#default_value' => $config->get('hide_non_content_items') ?? 0
    ];

    $form['show_account_link'] = [
      '#type' => 'radios',
      '#title' => 'User account link',
      '#description' => 'Links to user account pages.',
      '#options' => [
        '' => t('Show no link'),
        'user' => t('Link to user page'),
        'edit' => t('Link to account edit form'),
        'both' => t('Link to both'),
      ],
      '#default_value' => $config->get('show_account_link') ?? ''
    ];

    $form['enhance_content_item'] = [
      '#type' => 'checkbox',
      '#title' => 'Enhance content menu',
      '#description' => 'Enhances menu items for content types and collections.',
      '#default_value' => $config->get('enhance_content_item') ?? 0
    ];

    $form['show_categories_item'] = [
      '#type' => 'checkbox',
      '#title' => 'Show categories menu',
      '#description' => 'Shows a separate main menu item for categories (vocabularies).',
      '#default_value' => $config->get('show_categories_item') ?? 0
    ];

    $form['show_media_item'] = [
      '#type' => 'checkbox',
      '#title' => 'Show media menu',
      '#description' => 'Shows a separate main menu item for media.',
      '#default_value' => $config->get('show_media_item') ?? 0
    ];

    $form['show_webforms_item'] = [
      '#type' => 'checkbox',
      '#title' => 'Show webform menu',
      '#description' => 'Shows a separate main menu item for forms.',
      '#default_value' => $config->get('show_webforms_item') ?? 0
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('admin_toolbar_content.settings');
    $config->set('recent_items',  $form_state->getValue('recent_items', ''));
    $config->set('hide_non_content_items',  $form_state->getValue('hide_non_content_items', 0));
    $config->set('show_account_link',  $form_state->getValue('show_account_link', ''));
    $config->save();

    // Clear cache so admin menu can rebuild.
    \Drupal::service('plugin.manager.menu.link')->rebuild();

    parent::submitForm($form, $form_state);
  }

}
