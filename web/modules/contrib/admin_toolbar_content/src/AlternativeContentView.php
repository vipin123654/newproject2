<?php

namespace Drupal\admin_toolbar_content;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\views\Views;

/**
 * Class AlternativeContentView.
 *
 * If a 'content_<content_type>' view is provided, use that in stead of the
 * normal 'content' view.
 */
class AlternativeContentView implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Do the #pre_render callback.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array.
   */

  public static function preRender($element) {
    // Allow specific Views displays to explicitly perform pre-rendering, for
    // those displays that need to be able to know the fully built render array.
    if (!empty($element['#pre_rendered'])) {
      return $element;
    }

    if ($element['#name'] == 'content') {

      $content_type = \Drupal::request()->get('type');

      $view = Views::getView('content_' . $content_type);
      if (is_object($view)) {
        $element['#name'] = 'content_' . $content_type;
        $element['#view_id'] = $element['#name'];

        // Update the contextual links if enabled.
        if (isset($element['#contextual_links']['entity.view.edit_form'])) {
          $element['#contextual_links']['entity.view.edit_form']['route_parameters']['view'] = $element['#view_id'];
          $element['#contextual_links']['entity.view.edit_form']['metadata']['name'] = $element['#view_id'];
        }
      }
    }

    return $element;
  }
}
