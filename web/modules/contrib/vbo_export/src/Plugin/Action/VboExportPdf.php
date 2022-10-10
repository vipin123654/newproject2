<?php

namespace Drupal\vbo_export\Plugin\Action;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Generates pdf.
 *
 * @Action(
 *   id = "vbo_export_generate_pdf_action",
 *   label = @Translation("Generate pdf from selected view results"),
 *   type = ""
 * )
 */
class VboExportPdf extends VboExportBase {

  const EXTENSION = 'pdf';

  /**
   * The renderer.
   */
  protected $renderer;

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
    FileUrlGeneratorInterface $file_url_generator,
    RendererInterface $renderer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $stream_wrapper_manager, $temp_store_factory, $time, $file_repository, $file_url_generator);

    $this->renderer = $renderer;
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
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Add csv separator setting to preliminary config.
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $paper_sizes = array_keys(CPDF::$PAPER_SIZES);
    $form['paper_size'] = [
      '#title' => $this->t('Paper size'),
      '#type' => 'select',
      '#options' => array_combine($paper_sizes, $paper_sizes),
      '#default_value' => isset($values['paper_size']) ? $values['paper_size'] : 'letter',
    ];
    $form['orientation'] = [
      '#title' => $this->t('Orientation'),
      '#type' => 'radios',
      '#options' => [
        'portrait' => $this->t('Portrait'),
        'landscape' => $this->t('Landscape'),
      ],
      '#default_value' => isset($values['orientation']) ? $values['orientation'] : 'portrait',
    ];

    $form = parent::buildPreConfigurationForm($form, $values, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateOutput() {
    if (!_vbo_export_library_exists(Dompdf::class)) {
      $this->messenger()->addError('Dompdf library not installed.');
      return '';
    }

    $header = $this->getHeader();
    $rows = $this->getCurrentRows();

    $renderable = [
      '#theme' => 'vbo_export_pdf',
      '#items' => [],
      '#title' => $this->view->getTitle() . ' - ' . $this->view->getDisplay()->display['display_title'],
      '#empty_text' => $this->t('There are no items.'),
      '#view_id' => $this->view->id(),
      '#display_id' => $this->view->current_display,
    ];

    foreach ($rows as $row) {
      $item = [
        'fields' => [],
      ];
      foreach ($header as $field_id => $label) {
        $item['fields'][] = [
          'label' => $label,
          'value' => $this->configuration['strip_tags'] ? strip_tags($row[$field_id]) : Markup::create($row[$field_id]),
        ];
      }
      $renderable['#items'][] = $item;
    }

    $html = $this->renderer->renderPlain($renderable);

    $dompdf = new Dompdf();
    $dompdf->loadHtml((string) $html);
    $dompdf->setPaper($this->configuration['paper_size'], $this->configuration['orientation']);
    $dompdf->render();

    return $dompdf->output();
  }

}
