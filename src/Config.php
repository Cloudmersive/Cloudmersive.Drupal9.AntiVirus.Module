<?php

namespace Drupal\cloudmersiveantivirus;

/**
 * Class of config item.
 */
class Config {
  const MODE_DAEMON = 0;
  const MODE_EXECUTABLE = 1;
  const MODE_UNIX_SOCKET = 2;

  const MODE_CLOUDMERSIVE = 1;

  const OUTAGE_BLOCK_UNCHECKED = 0;
  const OUTAGE_ALLOW_UNCHECKED = 1;

  /**
   * Drupalreadonly config object.
   *
   * @var config
   */
  protected $config;

  /**
   * Constructor.
   *
   * Load the config from Drupal's CMI.
   */
  public function __construct() {
    $this->_config = \Drupal::config('cloudmersiveantivirus.settings');
  }

  /**
   * Global config options.
   */
  public function enabled() {
    return $this->_config->get('enabled');
  }

  /**
   * Global config options.
   */
  public function scanMode() {
    return $this->_config->get('scanMode');
  }

  /**
   * Global config options.
   */
  public function outageAction() {
    return $this->_config->get('outageAction');
  }

  /**
   * Global config options.
   */
  public function verbosity() {
    return $this->_config->get('verbosity');
  }

  /**
   * Global config options.
   */
  public function get($name) {
    return $this->_config->get($name);
  }

}
