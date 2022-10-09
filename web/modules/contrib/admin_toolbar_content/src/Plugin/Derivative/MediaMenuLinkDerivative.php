<?php
namespace Drupal\admin_toolbar_content\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaMenuLinkDerivative extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /** @var \Drupal\Core\Language\LanguageManagerInterface $languageManager */
  private $languageManager;

  /**
   * Create an AdminToolbarToolsHelper object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, RouteProviderInterface $routeProvider, LanguageManagerInterface $languageManager) {
    $this->moduleHandler = $moduleHandler;
    $this->routeProvider = $routeProvider;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {

    /** @var ModuleHandlerInterface $moduleHandler */
    $moduleHandler = $container->get('module_handler');

    /** @var RouteProviderInterface $routeProvider */
    $routeProvider = $container->get('router.route_provider');

    return new static(
      $moduleHandler,
      $routeProvider,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    $config = \Drupal::config('admin_toolbar_content.settings');
    $show_media_item = $config->get('show_media_item') ?? 0;

    if ($show_media_item && $this->moduleHandler->moduleExists('media')) {
      $links['media'] = [
          'title' => $this->t('Media'),
          'route_name' => 'view.media_library.page',
          'route_parameters' => [],
          'menu_name' => 'admin',
          'parent' => 'system.admin',
          'weight' => -8,
        ] + $base_plugin_definition;

      if ($this->moduleHandler->moduleExists('file') && $this->routeExists('view.files.page_1')) {
        $links['media.files'] = [
          'title' => $this->t('Files'),
          'route_name' => 'view.files.page_1',
          'route_parameters' => [],
          'menu_name' => 'admin',
          'parent' => $base_plugin_definition['id'] . ':' . 'media',
          'weight' => -8,
        ] + $base_plugin_definition;
      }

      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
      $entityTypeManager = \Drupal::service('entity_type.manager');

      // Add a list terms for each vocabulary.
      $media_types = $entityTypeManager->getStorage('media_type')
        ->loadMultiple();

      foreach ($media_types as $media_type) {
        $this->addMediaTypeLink($media_type, $links, $base_plugin_definition);
      }
    }

    return $links;
  }

  protected function addMediaTypeLink($media_type, &$links, $base_plugin_definition) {

    $label = $media_type->label();
    if ($this->languageManager->isMultilingual()) {
      $typeConfig = $this->languageManager->getLanguageConfigOverride('en', 'media.type.' . $media_type->id());
      $label = !empty($typeConfig->get('name')) ? $typeConfig->get('name') : $label;
    }

    $links['media.' . $media_type->id()] = [
      'title' => $this->t($label),
      'route_name' => 'entity.media.collection',
      'route_parameters' => [
        'type' => $media_type->id()
      ],
      'menu_name' => 'admin',
      'parent' =>  $base_plugin_definition['id'] . ':' . 'media',
    ] + $base_plugin_definition;

    $links['media.' . $media_type->id() . '.add'] = [
      'title' => $this->t('Add new'),
      'route_name' => "entity.media.add_form",
      'route_parameters' => [
        'media_type' => $media_type->id()
      ],
      'menu_name' => 'admin',
      'parent' =>  $base_plugin_definition['id'] . ':' . 'media.' . $media_type->id(),
      'metadata' => [
        'entity_type' => 'media_type',
        'entity_id' => $media_type->id()
      ]
    ] + $base_plugin_definition;

  }

  /**
   * Determine if a route exists by name.
   *
   * @param string $route_name
   *   The name of the route to check.
   *
   * @return bool
   *   Whether a route with that route name exists.
   */
  public function routeExists($route_name) {
    return (count($this->routeProvider->getRoutesByNames([$route_name])) === 1);
  }
}
