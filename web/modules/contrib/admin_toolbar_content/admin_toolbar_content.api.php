<?php

/**
 * @file
 * Documentation for admin_toolbar_content API.
 */

/**
 * Provide an array describing content type collections.
 *
 * Collections can group content types together in the admin ui under the
 * content menu.
 *
 * @return array
 *   [
 *     collection_machinename => [
 *       'label' => 'English label',
 *       'content_types' => [
 *           'bundle name content type',
 *           ...
 *           'bundle name content type'
 *       ]
 *     ],
 *     ...
 *   ]
 *
 */
function hook_content_type_collections() {
  return [
    'content' => [
      'label' => 'Content',
      'content_types' => [
        'page',
        'article'
      ]
    ]
  ];
}
