<?php

/**
 * @file
 * Definition of Views\file\Plugin\views\field\Status.
 */

namespace Views\file\Plugin\views\field;

use Drupal\Core\Annotation\Plugin;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Field handler to translate a node type into its readable form.
 *
 * @ingroup views_field_handlers
 *
 * @Plugin(
 *   id = "file_status",
 *   module = "file"
 * )
 */
class Status extends FieldPluginBase {

  function render($values) {
    $value = $this->get_value($values);
    return _views_file_status($value);
  }

}
