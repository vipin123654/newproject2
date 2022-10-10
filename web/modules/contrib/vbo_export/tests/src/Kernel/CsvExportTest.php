<?php

namespace Drupal\Tests\vbo_export\Kernel;

use Drupal\Tests\views_bulk_operations\Kernel\ViewsBulkOperationsKernelTestBase;
use Drupal\Core\Site\Settings;

/**
 * @coversDefaultClass \Drupal\vbo_export\Plugin\Action\VboExportBase
 * @group vbo_export
 */
class CsvExportTest extends ViewsBulkOperationsKernelTestBase {

  private const SEPARATOR = ',';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'vbo_export',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');

    $this->createTestNodes([
      'page' => [
        'count' => 5,
      ],
    ]);
  }

  /**
   * Tests the bulk edit action.
   *
   * @covers ::getViewBundles
   * @covers ::execute
   */
  public function testCsvExport() {
    $vbo_data = [
      'view_id' => 'views_bulk_operations_test',
      'action_id' => 'vbo_export_generate_csv_action',
      'configuration' => [
        'separator' => self::SEPARATOR,
        'field_override' => FALSE,
        'strip_tags' => TRUE,
      ],
    ];

    $selection = [0, 1, 2, 3];
    $vbo_data['list'] = $this->getResultsList($vbo_data, $selection);

    // Execute the action.
    $this->executeAction($vbo_data);
    $messenger = $this->container->get('messenger');
    $messages = $messenger->messagesByType($messenger::TYPE_STATUS);
    preg_match('#views_bulk_operations_test_.*\.csv#', html_entity_decode($messages[0]), $matches);
    $file_path = Settings::get('file_public_path') . '/' . $matches[0];
    $contents = file_get_contents($file_path);
    $rows = explode(PHP_EOL, $contents);

    foreach ($this->testNodesData as $nid => $item) {
      if (in_array(($nid - 1), $selection)) {
        $this->assertEquals($item['en'], $rows[$nid], "Exported node title doesn't match");
      }
      else {
        $this->assertTrue(empty($rows[$nid]));
      }
    }
  }

}
