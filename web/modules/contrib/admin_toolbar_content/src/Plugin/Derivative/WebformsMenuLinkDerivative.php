<?php
namespace Drupal\admin_toolbar_content\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WebformsMenuLinkDerivative extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /** @var \Drupal\Core\Language\LanguageManagerInterface $languageManager */
  private $languageManager;

  /**
   * Create an AdminToolbarToolsHelper object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, LanguageManagerInterface $languageManager) {
    $this->moduleHandler = $moduleHandler;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    /** @var ModuleHandlerInterface $moduleHandler */
    $moduleHandler = $container->get('module_handler');
    return new static(
      $moduleHandler,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    $config = \Drupal::config('admin_toolbar_content.settings');
    $show_webform_item = $config->get('show_webforms_item') ?? 0;

    if ($show_webform_item && $this->moduleHandler->moduleExists('webform')) {

      $links['webform'] = [
          'title' => $this->t('Forms'),
          'route_name' => 'entity.webform_submission.collection',
          'route_parameters' => [],
          'menu_name' => 'admin',
          'parent' => 'system.admin',
          'weight' => -7,
        ] + $base_plugin_definition;

      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
      $entityTypeManager = \Drupal::service('entity_type.manager');

      $webforms = $entityTypeManager->getStorage('webform')->loadMultiple(NULL);
      foreach ($webforms as $webform) {
        $links['webform.entity.' . $webform->id()] = [
            'title' => $webform->label(),
            'route_name' => "entity.webform.results_submissions",
            'route_parameters' => [
              'webform' => $webform->id()
            ],
            'menu_name' => 'admin',
            'weight' => 0,
            'parent' => $base_plugin_definition['id'] . ':webform'
          ] + $base_plugin_definition;
      }
    }

    return $links;
  }

}
