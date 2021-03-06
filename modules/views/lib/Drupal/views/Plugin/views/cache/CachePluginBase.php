<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\cache\CachePluginBase.
 */

namespace Drupal\views\Plugin\views\cache;

use Drupal\views\ViewExecutable;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\Core\Database\Query\Select;

/**
 * @defgroup views_cache_plugins Views cache plugins
 * @{
 * The base plugin to handler caching of a view.
 *
 * Cache plugins can handle both caching of just the database result and
 * the rendered output of the view.
 */

/**
 * The base plugin to handle caching.
 */
abstract class CachePluginBase extends PluginBase {

  /**
   * Contains all data that should be written/read from cache.
   */
  var $storage = array();

  /**
   * What table to store data in.
   */
  var $table = 'views_results';

  /**
   * Stores the cache ID used for the results cache.
   *
   * The cache ID is stored in generateResultsKey() got executed.
   *
   * @var string
   *
   * @see Drupal\views\Plugin\views\cache\CachePluginBase::generateResultsKey()
   */
  protected $resultsKey;

  /**
   * Stores the cache ID used for the output cache, once generateOutputKey() got
   * executed.
   *
   * @var string
   *
   * @see Drupal\views\Plugin\views\cache\CachePluginBase::generateOutputKey()
   */
  protected $outputKey;

  /**
   * Initialize the plugin.
   *
   * @param $view
   *   The view object.
   * @param $display
   *   The display handler.
   */
  public function init(ViewExecutable $view, &$display, $options = NULL) {
    $this->setOptionDefaults($this->options, $this->defineOptions());
    $this->view = &$view;
    $this->displayHandler = &$display;

    $this->unpackOptions($this->options, $options);
  }

  /**
   * Returns the outputKey property.
   *
   * @return string
   *   The outputKey property.
   */
  public function getOutputKey() {
    return $this->outputKey;
  }

  /**
   * Returns the resultsKey property.
   *
   * @return string
   *   The resultsKey property.
   */
  public function getResultsKey() {
    return $this->resultsKey;
  }

  /**
   * Return a string to display as the clickable title for the
   * access control.
   */
  public function summaryTitle() {
    return t('Unknown');
  }

  /**
   * Determine the expiration time of the cache type, or NULL if no expire.
   *
   * Plugins must override this to implement expiration.
   *
   * @param $type
   *   The cache type, either 'query', 'result' or 'output'.
   */
  function cache_expire($type) { }

   /**
    * Determine expiration time in the cache table of the cache type
    * or CACHE_PERMANENT if item shouldn't be removed automatically from cache.
    *
    * Plugins must override this to implement expiration in the cache table.
    *
    * @param $type
    *   The cache type, either 'query', 'result' or 'output'.
    */
  function cache_set_expire($type) {
    return CacheBackendInterface::CACHE_PERMANENT;
  }


  /**
   * Save data to the cache.
   *
   * A plugin should override this to provide specialized caching behavior.
   */
  function cache_set($type) {
    switch ($type) {
      case 'query':
        // Not supported currently, but this is certainly where we'd put it.
        break;
      case 'results':
        $data = array(
          'result' => $this->view->result,
          'total_rows' => isset($this->view->total_rows) ? $this->view->total_rows : 0,
          'current_page' => $this->view->getCurrentPage(),
        );
        cache($this->table)->set($this->generateResultsKey(), $data, $this->cache_set_expire($type));
        break;
      case 'output':
        $this->gather_headers();
        $this->storage['output'] = $this->view->display_handler->output;
        cache($this->table)->set($this->generateOutputKey(), $this->storage, $this->cache_set_expire($type));
        break;
    }
  }


  /**
   * Retrieve data from the cache.
   *
   * A plugin should override this to provide specialized caching behavior.
   */
  function cache_get($type) {
    $cutoff = $this->cache_expire($type);
    switch ($type) {
      case 'query':
        // Not supported currently, but this is certainly where we'd put it.
        return FALSE;
      case 'results':
        // Values to set: $view->result, $view->total_rows, $view->execute_time,
        // $view->current_page.
        if ($cache = cache($this->table)->get($this->generateResultsKey())) {
          if (!$cutoff || $cache->created > $cutoff) {
            $this->view->result = $cache->data['result'];
            $this->view->total_rows = $cache->data['total_rows'];
            $this->view->setCurrentPage($cache->data['current_page']);
            $this->view->execute_time = 0;
            return TRUE;
          }
        }
        return FALSE;
      case 'output':
        if ($cache = cache($this->table)->get($this->generateOutputKey())) {
          if (!$cutoff || $cache->created > $cutoff) {
            $this->storage = $cache->data;
            $this->view->display_handler->output = $cache->data['output'];
            $this->restore_headers();
            return TRUE;
          }
        }
        return FALSE;
    }
  }

  /**
   * Clear out cached data for a view.
   *
   * We're just going to nuke anything related to the view, regardless of display,
   * to be sure that we catch everything. Maybe that's a bad idea.
   */
  function cache_flush() {
    cache($this->table)->invalidateTags(array($this->view->storage->name => TRUE));
  }

  /**
   * Post process any rendered data.
   *
   * This can be valuable to be able to cache a view and still have some level of
   * dynamic output. In an ideal world, the actual output will include HTML
   * comment based tokens, and then the post process can replace those tokens.
   *
   * Example usage. If it is known that the view is a node view and that the
   * primary field will be a nid, you can do something like this:
   *
   * <!--post-FIELD-NID-->
   *
   * And then in the post render, create an array with the text that should
   * go there:
   *
   * strtr($output, array('<!--post-FIELD-1-->', 'output for FIELD of nid 1');
   *
   * All of the cached result data will be available in $view->result, as well,
   * so all ids used in the query should be discoverable.
   */
  function post_render(&$output) { }

  /**
   * Start caching javascript, css and other out of band info.
   *
   * This takes a snapshot of the current system state so that we don't
   * duplicate it. Later on, when gather_headers() is run, this information
   * will be removed so that we don't hold onto it.
   */
  function cache_start() {
    $this->storage['head'] = drupal_add_html_head();
    $this->storage['css'] = drupal_add_css();
    $this->storage['js'] = drupal_add_js();
  }

  /**
   * Gather out of band data, compare it to what we started with and store the difference.
   */
  function gather_headers() {
    // Simple replacement for head
    if (isset($this->storage['head'])) {
      $this->storage['head'] = str_replace($this->storage['head'], '', drupal_add_html_head());
    }
    else {
      $this->storage['head'] = '';
    }

    // Slightly less simple for CSS:
    $css = drupal_add_css();
    $css_start = isset($this->storage['css']) ? $this->storage['css'] : array();
    $this->storage['css'] = array_diff_assoc($css, $css_start);

    // Get javascript after/before views renders.
    $js = drupal_add_js();
    $js_start = isset($this->storage['js']) ? $this->storage['js'] : array();
    // If there are any differences between the old and the new javascript then
    // store them to be added later.
    $this->storage['js'] = array_diff_assoc($js, $js_start);

    // Special case the settings key and get the difference of the data.
    $settings = isset($js['settings']['data']) ? $js['settings']['data'] : array();
    $settings_start = isset($js_start['settings']['data']) ? $js_start['settings']['data'] : array();
    $this->storage['js']['settings'] = array_diff_assoc($settings, $settings_start);
  }

  /**
   * Restore out of band data saved to cache. Copied from Panels.
   */
  function restore_headers() {
    if (!empty($this->storage['head'])) {
      drupal_add_html_head($this->storage['head']);
    }
    if (!empty($this->storage['css'])) {
      foreach ($this->storage['css'] as $args) {
        drupal_add_css($args['data'], $args);
      }
    }
    if (!empty($this->storage['js'])) {
      foreach ($this->storage['js'] as $key => $args) {
        if ($key !== 'settings') {
          drupal_add_js($args['data'], $args);
        }
        else {
          foreach ($args as $setting) {
            drupal_add_js($setting, 'setting');
          }
        }
      }
    }
  }

  /**
   * Calculates and sets a cache ID used for the result cache.
   *
   * @return string
   *   The generated cache ID.
   */
  public function generateResultsKey() {
    global $user;

    if (!isset($this->resultsKey)) {
      $build_info = $this->view->build_info;

      foreach (array('query', 'count_query') as $index) {
        // If the default query back-end is used generate SQL query strings from
        // the query objects.
        if ($build_info[$index] instanceof Select) {
          $query = clone $build_info[$index];
          $query->preExecute();
          $build_info[$index] = (string) $query;
        }
      }
      $key_data = array(
        'build_info' => $build_info,
        'roles' => array_keys($user->roles),
        'super-user' => $user->uid == 1, // special caching for super user.
        'langcode' => language(LANGUAGE_TYPE_INTERFACE)->langcode,
        'base_url' => $GLOBALS['base_url'],
      );
      foreach (array('exposed_info', 'page', 'sort', 'order', 'items_per_page', 'offset') as $key) {
        if (isset($_GET[$key])) {
          $key_data[$key] = $_GET[$key];
        }
      }

      $this->resultsKey = $this->view->storage->name . ':' . $this->displayHandler->display['id'] . ':results:' . md5(serialize($key_data));
    }

    return $this->resultsKey;
  }

  /**
   * Calculates and sets a cache ID used for the output cache.
   *
   * @return string
   *   The generated cache ID.
   */
  public function generateOutputKey() {
    global $user;
    if (!isset($this->outputKey)) {
      $key_data = array(
        'result' => $this->view->result,
        'roles' => array_keys($user->roles),
        'super-user' => $user->uid == 1, // special caching for super user.
        'theme' => $GLOBALS['theme'],
        'langcode' => language(LANGUAGE_TYPE_INTERFACE)->langcode,
        'base_url' => $GLOBALS['base_url'],
      );

      $this->outputKey = $this->view->storage->name . ':' . $this->displayHandler->display['id'] . ':output:' . md5(serialize($key_data));
    }

    return $this->outputKey;
  }

}

/**
 * @}
 */
