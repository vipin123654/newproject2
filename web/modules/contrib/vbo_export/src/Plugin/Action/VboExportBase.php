<?php

namespace Drupal\vbo_export\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\file\FileRepositoryInterface;
use Drupal\views\ViewExecutable;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for export actions.
 */
abstract class VboExportBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  const EXTENSION = '';

  /**
   * Some fields should always be excluded from exports.
   */
  const EXCLUDED_FIELDS = [
    'views_bulk_operations_bulk_form',
    'entity_operations',
  ];

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The stream wrapper object.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;


  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The file repository service.
   *
   * @var \Drupal\Core\File\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    PrivateTempStoreFactory $temp_store_factory,
    TimeInterface $time,
    FileRepositoryInterface $file_repository,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->tempStore = $temp_store_factory->get('vbo_export_multiple');
    $this->time = $time;
    $this->fileRepository = $file_repository;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('stream_wrapper_manager'),
      $container->get('tempstore.private'),
      $container->get('datetime.time'),
      $container->get('file.repository'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $form['strip_tags'] = [
      '#title' => $this->t('Strip HTML tags'),
      '#type' => 'checkbox',
      '#default_value' => isset($values['strip_tags']) ? $values['strip_tags'] : FALSE,
    ];

    $form['field_override'] = [
      '#title' => $this->t('Override the fields configuration'),
      '#type' => 'checkbox',
      '#default_value' => isset($values['field_override']) ? $values['field_override'] : FALSE,
    ];

    if ($this->view instanceof ViewExecutable && !empty($this->view->field)) {
      $form['field_config'] = [
        '#type' => 'table',
        '#caption' => $this->t('Select the fields you want to include in the exportable. <strong>The following options only applies if the "Override the fields configuration" option is checked.</strong>'),
        '#header' => [
          $this->t('Field name'),
          $this->t('Active'),
          $this->t('Label'),
        ],
      ];

      $functional_fields = [
        'views_bulk_operations_bulk_form',
        'entity_operations',
      ];
      foreach ($this->view->field as $field_id => $field) {
        if (in_array($field_id, $functional_fields)) {
          continue;
        }
        $form['field_config'][$field_id] = [
          'name' => [
            '#markup' => $field_id,
          ],
          'active' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Active'),
            '#title_display' => 'invisible',
            '#default_value' => isset($values['field_config'][$field_id]['active']) ? $values['field_config'][$field_id]['active'] : FALSE,
          ],
          'label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#title_display' => 'invisible',
            '#default_value' => isset($values['field_config'][$field_id]['label']) ? $values['field_config'][$field_id]['label'] : '',
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * Generate export output string.
   *
   * @return string
   *   The generated output string.
   */
  abstract protected function generateOutput();

  /**
   * Current rows to be processed.
   *
   * @return array
   *   Array of rows.
   */
  protected function getCurrentRows() {
    $rows = [];
    for ($i = 1; $i <= $this->context['sandbox']['current_batch']; $i++) {
      $chunk = $this->tempStore->get($this->context['sandbox']['cid_prefix'] . $i);
      if ($chunk) {
        $rows = array_merge($rows, $chunk);
        $this->tempStore->delete($this->context['sandbox']['cid_prefix'] . $i);
      }
    }

    return $rows;
  }

  /**
   * Output generated string to file. Message user.
   *
   * @param string $output
   *   The string that will be saved to a file.
   */
  protected function sendToFile($output) {
    if (!empty($output)) {
      $rand = substr(hash('ripemd160', uniqid()), 0, 8);
      $filename = $this->getFilename();

      $wrappers = $this->streamWrapperManager->getWrappers();
      if (isset($wrappers['private'])) {
        $wrapper = 'private';
      }
      else {
        $wrapper = 'public';
      }

      $destination = $wrapper . '://' . $filename;
      $file = $this->fileRepository->writeData($output, $destination, FileSystemInterface::EXISTS_REPLACE);
      $file->setTemporary();
      $file->save();

      // Get relative url to prevent mixed content errors when using
      // HTTPS + HTTP.
      $relative_url = $this->fileUrlGenerator->transformRelative($this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()));
      $this->messenger()->addStatus($this->t('Export file created, <a href=":url" target="_blank">Click here</a> to download.', [':url' => $relative_url]));
    }
  }

  /**
   * Set a method that can be overriden to get filename destination.
   *
   * @return string
   *   The output file name.
   */
  protected function getFilename() {
    $rand = substr(hash('ripemd160', uniqid()), 0, 8);
    return $this->context['view_id'] . '_' . date('Y_m_d_H_i', $this->time->getRequestTime()) . '-' . $rand . '.' . static::EXTENSION;
  }

  /**
   * Execute multiple handler.
   *
   * Execute action on multiple entities to generate csv output
   * and display a download link.
   */
  public function executeMultiple(array $entities) {
    // Free up some memory.
    unset($entities);

    if (empty($this->getHeader()) || empty($this->view->result)) {
      return;
    }

    $rows = $this->getRows();
    $processed = $this->context['sandbox']['processed'] + count($rows);
    $this->saveRows($rows);

    // Generate the output file if the last row has been processed.
    if (!isset($this->context['sandbox']['total']) || $processed >= $this->context['sandbox']['total']) {
      $output = $this->generateOutput();
      $this->sendToFile($output);
    }
  }

  /**
   * Get rows from views results.
   *
   * @return array
   *   An array of rows in a single batch prepared for theming.
   */
  protected function getRows() {
    // Render rows.
    $this->view->render($this->view->current_display);
    $index = $this->context['sandbox']['processed'];
    $rows = [];
    foreach (array_keys($this->view->result) as $num) {
      foreach (array_keys($this->getHeader()) as $field_id) {
        $rows[$index][$field_id] = (string) $this->view->style_plugin->getField($num, $field_id);
      }
      $index++;
    }
    return $rows;
  }

  /**
   * Prepares sandbox data (header and cache ID).
   *
   * @return array
   *   Table header.
   */
  protected function getHeader() {
    // Build output header array.
    $header = &$this->context['sandbox']['header'];
    if (!empty($header)) {
      return $header;
    }

    return $this->setHeader();
  }

  /**
   * Sets table header from view header.
   *
   * @return array
   *   Table header.
   */
  protected function setHeader() {
    $this->context['sandbox']['header'] = [];
    $header = &$this->context['sandbox']['header'];

    if ($this->configuration['field_override']) {
      foreach ($this->configuration['field_config'] as $id => $field_settings) {
        if ($field_settings['active']) {
          if ($field_settings['label'] !== '') {
            $header[$id] = $field_settings['label'];
          }
          elseif (array_key_exists($id, $this->view->field)) {
            $header[$id] = $this->view->field[$id]->options['label'];
          }
        }
      }
    }

    else {
      foreach ($this->view->field as $id => $field) {
        if (
          in_array($field->options['plugin_id'], static::EXCLUDED_FIELDS) ||
          $field->options['exclude']
        ) {
          continue;
        }
        $header[$id] = $field->options['label'];
      }
    }

    return $header;
  }

  /**
   * Gets Cache ID for current batch.
   *
   * @return string
   *   Cache unique ID for Temporary storage.
   */
  protected function getCid() {
    if (!isset($this->context['sandbox']['cid_prefix'])) {
      $this->context['sandbox']['cid_prefix'] = $this->context['view_id'] . ':'
        . $this->context['display_id'] . ':' . $this->context['action_id'] . ':'
        . md5(serialize(array_keys($this->context['list']))) . ':';
    }

    return $this->context['sandbox']['cid_prefix'] . $this->context['sandbox']['current_batch'];
  }

  /**
   * Saves batch data into Private storage.
   *
   * @param array $rows
   *   Rows from batch.
   */
  protected function saveRows(array &$rows) {
    $this->tempStore->set($this->getCid(), $rows);
    unset($rows);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->executeMultiple([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('view', $account, $return_as_object);
  }

}
