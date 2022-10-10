<?php

namespace Drupal\vbo_export\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Generates csv.
 *
 * @Action(
 *   id = "vbo_export_generate_csv_action",
 *   label = @Translation("Generate csv from selected view results"),
 *   type = ""
 * )
 */
class VboExportCsv extends VboExportBase {

  const EXTENSION = 'csv';

  /**
   * {@inheritdoc}
   *
   * Add csv separator setting to preliminary config.
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $form = parent::buildPreConfigurationForm($form, $values, $form_state);
    $form['separator'] = [
      '#title' => $this->t('CSV separator'),
      '#type' => 'radios',
      '#options' => [
        ';' => $this->t('semicolon ";"'),
        ',' => $this->t('comma ","'),
        '|' => $this->t('pipe "|"'),
      ],
      '#default_value' => isset($values['separator']) ? $values['separator'] : ';',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateOutput() {
    $config = $this->configuration;
    $header = $this->context['sandbox']['header'];
    $rows = $this->getCurrentRows();

    // Sanitize data.
    foreach ($header as $key => $item) {
      $header[$key] = strtr($item, [$config['separator'] => ' ']);
    }

    $content_replacements = [
      "\r\n" => ' ',
      "\n\r" => ' ',
      "\r" => ' ',
      "\n" => ' ',
      "\t" => ' ',
      $config['separator'] => ' ',
    ];

    // Generate output.
    $csv_rows = [];
    $csv_rows[] = implode($config['separator'], $header);
    foreach ($rows as $row_index => $row) {
      foreach ($row as $cell_key => $cell) {
        $row[$cell_key] = strtr($cell, $content_replacements);
      }
      $csv_rows[] = implode($config['separator'], $row);
      unset($rows[$row_index]);
    }

    $csv_string = implode(PHP_EOL, $csv_rows);
    if (!empty($config['strip_tags'])) {
      $csv_string = strip_tags($csv_string);
    }

    // BOM needs to be added to UTF-8 encoded csv file
    // to make it easier to read by Excel.
    $output = chr(0xEF) . chr(0xBB) . chr(0xBF) . (string) $csv_string;
    return $output;
  }

}
