<?php

/*
Plugin Name: Trending Posts Query
Plugin URI: https://github.com/ethanbutler/TrendingPostsQuery
Description: Adds 'orderby' => 'trending' as an option for WP_Query.
Version: 0.1
Author: Ethan Butler
Author URI: https://github.com/ethanbutler
License: GPL2
Text Domain: tpq
*/

defined('ABSPATH') or die;
define('TPQ_SETTINGS_FLUSH', 'tpq_flush_interval');

class Trending_Posts_Query {

  const CLEAR_VIEWS_HOOK = 'tpq_clear_views';
  const LOG_VIEWS_HOOK   = 'tpq_log_view';
  const META_KEY         = 'tpq_view_count';
  const INTERVAL_KEY     = 'tpq_custom_interval';
  const SETTINGS_GROUP   = 'tpq_settings_group';

  function __construct() {
    if(is_admin() && isset($_POST[TPQ_SETTINGS_FLUSH])) {
      $this->setup_post_count();
      $this->setup_cron(self::INTERVAL_KEY);
    }

    add_action('admin_init',           [$this, 'register_settings']);
    add_action('admin_menu',           [$this, 'register_settings_page']);
    add_action('pre_get_posts',        [$this, 'filter_query']);
    add_action(self::CLEAR_VIEWS_HOOK, [$this, 'setup_post_count']);
    add_action(self::LOG_VIEWS_HOOK,   [$this, 'increment_view'], 0);

  }

  function filter_query($query) {
    if($query->query['orderby'] === 'trending') {
      $query->set('orderby', 'meta_value');
      $query->set('meta_key', self::META_KEY);
    }
  }

  function setup_post_count() {
    global $wpdb;
    $wpdb->show_errors();
    $wpdb->update(
      $wpdb->prefix.'postmeta',
      [ 'meta_value' => 0 ],
      [ 'meta_key' => self::META_KEY ],
      '%d'
    );
  }

  function increment_view($id) {
    $id = $id ? $id : get_the_ID();
    if(!$id) return;

    $current_count = get_post_meta($id, self::META_KEY, true);
    $new_count = ($current_count ? $current_count : 0) + 1;
    update_post_meta($id, self::META_KEY, $new_count);
  }

  function setup_cron($recurrence = 'daily') {
    wp_clear_scheduled_hook(self::CLEAR_VIEWS_HOOK);
    wp_schedule_event(time(), $recurrence, self::CLEAR_VIEWS_HOOK);
  }

  function manage_cron($schedules) {
    $schedules[self::INTERVAL_KEY] = [
      'interval' => $interval,
      'display'  => $interval . __(' days', 'tpq')
    ];
    return $schedules;
  }

  function register_settings() {
    register_setting(self::SETTINGS_GROUP, TPQ_SETTINGS_FLUSH);
  }

  function register_settings_page() {
    add_options_page(
      __('Trending Posts Query Settings', 'tpq'),
      __('TPQ', 'tpq'),
      'manage_options',
      'tpq',
      [$this, 'settings_page']
    );
  }

  function settings_page() {
    ?>
    <div class="wrap">
      <h1><?php _e('Trending Posts Query Settings', 'tpq'); ?></h1>
      <form method="post" action="options.php">
      <?php settings_fields(self::SETTINGS_GROUP); ?>
      <?php do_settings_sections(self::SETTINGS_GROUP); ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><?php _e('Flush every (x) days', 'tpq'); ?></th>
            <td>
              <input type="number" name="<?= TPQ_SETTINGS_FLUSH; ?>" value="<?= esc_attr(get_option(TPQ_SETTINGS_FLUSH)); ?>">
            </td>
          </tr>
        </table>
        <?php submit_button(__('Reset Flush')); ?>
        <p>
          <em>
            <? _e('Next flush at:') ?>
            <?= date(get_option('date_format') . ', ' . get_option('time_format'), wp_next_scheduled(self::CLEAR_VIEWS_HOOK)); ?>
          </em>
        </p>
      </form>
    </div>
    <?php
  }
}

add_filter('cron_schedules', function($schedules) {
  $interval = get_option(TPQ_SETTINGS_FLUSH);
  $interval = ($interval ? (int) $interval : 1) * 24 * 60 * 60;

  $schedules['tpq_custom_interval'] = [
    'interval' => $interval,
    'display' => __('Custom Interval')
  ];
  return $schedules;
});

register_activation_hook(__FILE__, ['Trending_Posts_Query', 'setup_cron']);

$tp = new Trending_Posts_Query();
