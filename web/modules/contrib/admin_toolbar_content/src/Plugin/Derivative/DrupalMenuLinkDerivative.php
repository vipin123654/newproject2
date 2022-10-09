<?php
namespace Drupal\admin_toolbar_content\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DrupalMenuLinkDerivative extends DeriverBase implements ContainerDeriverInterface {

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

    if ($this->moduleHandler->moduleExists('user')) {
      $user = \Drupal::currentUser();

      if ($user->isAuthenticated()) {
        $config = \Drupal::config('admin_toolbar_content.settings');
        $setting = $config->get('show_account_link') ?? '';

        if ($setting == 'user' || $setting == 'both') {
          $links['user.entity'] = [
            'title' => $this->t('My account'),
            'route_name' => 'user.page',
            'route_parameters' => [],
            'menu_name' => 'admin',
            'parent' => 'admin_toolbar_tools.help',
            'weight' => 9,
          ] + $base_plugin_definition;
        }

        if ($setting == 'edit' || $setting == 'both') {
          $links['user.entity.' . $user->id()] = [
            'title' => $setting == 'both' ? $this->t("Edit my account") : $this->t("My account"),
            'route_name' => "entity.user.edit_form",
            'route_parameters' => [
              'user' => $user->id()
            ],
            'menu_name' => 'admin',
            'weight' => 10,
            'parent' => 'admin_toolbar_tools.help'
          ] + $base_plugin_definition;
        }
      }
    }

    return $links;
  }

}
