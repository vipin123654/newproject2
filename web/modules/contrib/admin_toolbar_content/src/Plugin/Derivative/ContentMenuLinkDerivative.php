<?php
namespace Drupal\admin_toolbar_content\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentMenuLinkDerivative extends DeriverBase implements ContainerDeriverInterface {

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
    $enhance_content_item = $config->get('enhance_content_item') ?? 0;

    if ($enhance_content_item && $this->moduleHandler->moduleExists('node')) {

      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
      $entityTypeManager = \Drupal::service('entity_type.manager');

      $content_type_collections = \Drupal::service('module_handler')
        ->invokeAll('content_type_collections');

      /** @var \Drupal\node\NodeTypeInterface[] $contentTypes */
      $contentTypes = $entityTypeManager->getStorage('node_type')
        ->loadMultiple();

      foreach ($content_type_collections as $collection => $content_type_collection) {
        $links[$collection] = [
            'title' => $this->t((string) $content_type_collection['label']),
            'route_name' => 'system.admin_content',
            'route_parameters' => [
              'collection' => $collection,
            ],
            'menu_name' => 'admin',
            'parent' => 'system.admin_content',
          ] + $base_plugin_definition;

        foreach ($content_type_collection['content_types'] as $content_type_id) {
          if (isset($contentTypes[$content_type_id])) {
            $this->addContentTypeLink($contentTypes[$content_type_id], $collection, $links, $base_plugin_definition);
            unset($contentTypes[$content_type_id]);
          }
        }
      }

      $collection = 'content';
      foreach ($contentTypes as $content_type) {
        $this->addContentTypeLink($content_type, $collection, $links, $base_plugin_definition);
      }
    }

    return $links;
  }

  /**
   * @param \Drupal\node\NodeTypeInterface $content_type
   * @param string $collection
   * @param array $links
   * @param array $base_plugin_definition
   */
  protected function addContentTypeLink(\Drupal\node\NodeTypeInterface $content_type, $collection, &$links, $base_plugin_definition) {

    $link_name = $collection . '.' . $content_type->id();

    $label = $content_type->label();
    if ($this->languageManager->isMultilingual()) {
      $typeConfig = $this->languageManager->getLanguageConfigOverride('en', 'node.type.' . $content_type->id());
      $label = !empty($typeConfig->get('name')) ? $typeConfig->get('name') : $label;
    }

    $links[$link_name] = [
      'title' => $this->t($label),
      'route_name' => 'system.admin_content',
      'route_parameters' => [
        'type' => $content_type->id(),
        'collection' => $collection,
      ],
      'menu_name' => 'admin',
      'parent' => !empty($links[$collection]) ? $base_plugin_definition['id'] . ':' . $collection :  "system.admin_content",
    ] + $base_plugin_definition;

    $links[$link_name . '.add'] = [
      'title' => $this->t('Add new'),
      'route_name' => "node.add",
      'route_parameters' => [
        'node_type' => $content_type->id()
      ],
      'weight' => -1,
      'menu_name' => 'admin',
      'parent' =>  $base_plugin_definition['id'] . ':' . $link_name,
      'options' => [
        'attributes' => [
          'class' => [
            'admin-toolbar-content-add-new-item'
          ]
        ]
      ]
    ] + $base_plugin_definition;

    // Add the last x items
    $config = \Drupal::config('admin_toolbar_content.settings');
    $count = $config->get('recent_items') ?? 0;

    if (!empty($count)) {

      $entity_storage = \Drupal::service('entity_type.manager')->getStorage('node');
      $ids = $entity_storage->getQuery()
        ->condition('type', $content_type->id())
        ->pager($count + 1)
        ->sort('changed', 'DESC')
        ->execute();

      $c = 0;
      foreach ($ids as $id) {
        // Skip the last one.
        if ($c++ == $count) {
          break;
        }

        /** @var \Drupal\node\NodeInterface $entity */
        $entity = $entity_storage->load($id);
        if ($entity === null) continue; // HACK fix, getQuery can return results which load can't load???
        $links[$link_name . '.entity.' . $entity->id()] = [
          'title' => $entity->label(),
          'route_name' => "entity.node.edit_form",
          'route_parameters' => [
            'node' => $entity->id()
          ],
          'menu_name' => 'admin',
          'weight' => $c,
          'parent' => $base_plugin_definition['id'] . ':' . $link_name
        ] + $base_plugin_definition;
      }

      if (count($ids) > $count) {
        $links[$link_name . '.more'] = [
          'title' => $this->t('More'),
          'route_name' => 'system.admin_content',
          'route_parameters' => [
            'type' => $content_type->id(),
            'collection' => $collection,
          ],
          'weight' => $count + 1,
          'menu_name' => 'admin',
          'parent' => $base_plugin_definition['id'] . ':' . $link_name,
          'options' => [
            'attributes' => [
              'class' => [
                'admin-toolbar-content-more-item'
              ]
            ]
          ]
        ] + $base_plugin_definition;
      }

    }

  }
}
