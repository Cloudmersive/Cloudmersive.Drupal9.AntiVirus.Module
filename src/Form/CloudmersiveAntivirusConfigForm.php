<?php

namespace Drupal\cloudmersiveantivirus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\cloudmersiveantivirus\Config;

/**
 * Configure file system settings for this site.
 */
class CloudmersiveAntivirusConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cloudmersiveantivirus_system_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cloudmersiveantivirus.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cloudmersiveantivirus.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Cloudmersive Anti-virus integration'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['scan_mechanism_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Scan mechanism'),
      '#open' => TRUE,
    ];
    $form['scan_mechanism_wrapper']['scanMode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Scan mechanism'),
      '#options' => [
        Config::MODE_CLOUDMERSIVE  => $this->t('Cloudmersive Anti-virus API'),

      ],
      '#default_value' => $config->get('scanMode'),
      '#description' => $this->t("Configure how Drupal connects to Cloudmersive Anti-virus. <a href='https://account.cloudmersive.com/signup'>Get key now</a>"),
    ];

    // Configuration if CloudmersiveAntivirus is set to Executable mode.
    $form['scan_mechanism_wrapper']['mode_executable'] = [
      '#type' => 'details',
      '#title' => $this->t('Cloudmersive Anti-virus API configuration'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="scanMode"]' => ['value' => Config::MODE_EXECUTABLE],
        ],
      ],
    ];
    $form['scan_mechanism_wrapper']['mode_executable']['executable_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cloudmersive Anti-virus API Key'),
      '#default_value' => $config->get('mode_executable.executable_path'),
      '#maxlength' => 255,

    ];

    // Configuration if CloudmersiveAntivirus is set to Daemon mode.
    $form['scan_mechanism_wrapper']['mode_daemon_tcpip'] = [
      '#type' => 'details',
      '#title' => $this->t('Daemon mode configuration (over TCP/IP)'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="scanMode"]' => ['value' => Config::MODE_DAEMON],
        ],
      ],
    ];
    $form['scan_mechanism_wrapper']['mode_daemon_tcpip']['hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cloudmersive API Key'),
      '#default_value' => $config->get('mode_daemon_tcpip.hostname'),
      '#maxlength' => 255,

    ];

    // Configuration if CloudmersiveAntivirus is set
    // to Daemon mode over Unix socket.
    $form['scan_mechanism_wrapper']['mode_daemon_unixsocket'] = [
      '#type' => 'details',
      '#title' => $this->t('Daemon mode configuration (over Unix socket)'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="scanMode"]' => ['value' => Config::MODE_UNIX_SOCKET],
        ],
      ],
    ];
    $form['scan_mechanism_wrapper']['mode_daemon_unixsocket']['unixsocket'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Socket path'),
      '#default_value' => $config->get('mode_daemon_unixsocket.unixsocket'),
      '#maxlength' => 255,

    ];

    $form['outageActions_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Outage behaviour'),
      '#open' => TRUE,
    ];
    $form['outageActions_wrapper']['outageAction'] = [
      '#type' => 'radios',
      '#title' => $this->t('Behaviour when Cloudmersive Antivirus API is unavailable'),
      '#options' => [
        Config::OUTAGE_BLOCK_UNCHECKED => $this->t('Block unchecked files'),
        Config::OUTAGE_ALLOW_UNCHECKED => $this->t('Allow unchecked files'),
      ],
      '#default_value' => $config->get('outageAction'),
    ];
    $form['outageActions_wrapper']['curl_timeout_value'] = [
      '#type' => 'number',
      '#title' => $this->t('Curl timeout value (in seconds)'),
      '#default_value' => !empty($config->get('curl_timeout_value')) ? $config->get('curl_timeout_value') : 30,
      '#min' => 30,
      '#max' => 300,
      '#step' => 10,
    ];

    // Allow scanning according to scheme-wrapper.
    $form['schemes'] = [
      '#type' => 'details',
      '#title' => 'Scannable schemes / stream wrappers',
      '#open' => TRUE,

      '#description' => $this->t('By default only @local schemes are scannable.',
         ['@local' => Link::fromTextAndUrl('STREAM_WRAPPERS_LOCAL', Url::fromUri('https://api.drupal.org/api/drupal/includes!stream_wrappers.inc/7'))->toString()]),
    ];

    $local_schemes  = $this->schemeWrappersAvailable('local');
    $remote_schemes = $this->schemeWrappersAvailable('remote');

    if (count($local_schemes)) {
      $form['schemes']['cloudmersiveantivirus_local_schemes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Local schemes'),
        '#options' => $local_schemes,
        '#default_value' => $this->schemeWrappersToScan('local'),
      ];
    }
    if (count($remote_schemes)) {
      $form['schemes']['cloudmersiveantivirus_remote_schemes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Remote schemes'),
        '#options' => $remote_schemes,
        '#default_value' => $this->schemeWrappersToScan('remote'),
      ];
    }

    $form['verbosity_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Verbosity'),
      '#open' => TRUE,
    ];
    $form['verbosity_wrapper']['verbosity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verbose'),
      '#description' => $this->t('Verbose mode will log all scanned files, including files which pass the Cloudmersive Antivirus scan.'),
      '#default_value' => $config->get('verbosity'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Configure the stream-wrapper schemes that are overridden.
    // Local schemes behave differently to remote schemes.
    $local_schemes_to_scan  = (is_array($form_state->getValue('cloudmersiveantivirus_local_schemes')))
      ? array_filter($form_state->getValue('cloudmersiveantivirus_local_schemes'))
      : [];
    $remote_schemes_to_scan = (is_array($form_state->getValue('cloudmersiveantivirus_remote_schemes')))
      ? array_filter($form_state->getValue('cloudmersiveantivirus_remote_schemes'))
      : [];
    $overridden_schemes = array_merge(
      $this->getOverriddenSchemes('local', $local_schemes_to_scan),
      $this->getOverriddenSchemes('remote', $remote_schemes_to_scan)
    );

    $this->config('cloudmersiveantivirus.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('outageAction', $form_state->getValue('outageAction'))
      ->set('curl_timeout_value', $form_state->getValue('curl_timeout_value'))
      ->set('overridden_schemes', $overridden_schemes)
      ->set('scanMode', $form_state->getValue('scanMode'))
      ->set('verbosity', $form_state->getValue('verbosity'))

      ->set('mode_executable.executable_path', $form_state->getValue('executable_path'))
      ->set('mode_executable.executable_parameters', $form_state->getValue('executable_parameters'))

      ->set('mode_daemon_tcpip.hostname', $form_state->getValue('hostname'))
      ->set('mode_daemon_tcpip.port', $form_state->getValue('port'))

      ->set('mode_daemon_unixsocket.unixsocket', $form_state->getValue('unixsocket'))

      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * List the available stream-wrappers, according to whether.
   *
   * The stream-wrapper is local or remote.
   *
   * @param string $type
   *   Either 'local' (for local stream-wrappers), or 'remote'.
   *
   * @return array
   *   Array of the names of scheme-wrappers, indexed by the machine-name of
   *   the scheme-wrapper.
   *   For example: array('public' => 'public://').
   */
  public function schemeWrappersAvailable($type) {
    $mgr = \Drupal::service('stream_wrapper_manager');

    switch ($type) {
      case 'local':
        $schemes = array_keys($mgr->getWrappers(StreamWrapperInterface::LOCAL));
        break;

      case 'remote':
        $schemes = array_keys(array_diff_key(
          $mgr->getWrappers(StreamWrapperInterface::ALL),
          $mgr->getWrappers(StreamWrapperInterface::LOCAL)
        ));
        break;
    }

    $options = [];
    foreach ($schemes as $scheme) {
      $options[$scheme] = $scheme . '://';
    }
    return $options;
  }

  /**
   * List the stream-wrapper schemes that are configured to be scannable.
   *
   * According to whether the scheme is local or remote.
   *
   * @param string $type
   *   Either 'local' (for local stream-wrappers), or 'remote'.
   *
   * @return array
   *   Unindexed array of the machine-names of stream-wrappers that should be
   *   scanned.
   *   For example: array('public', 'private').
   */
  public function schemeWrappersToScan($type) {
    switch ($type) {
      case 'local':
        $schemes = array_keys($this->schemeWrappersAvailable('local'));
        break;

      case 'remote':
        $schemes = array_keys($this->schemeWrappersAvailable('remote'));
        break;
    }

    return array_filter($schemes,
      ['\Drupal\cloudmersiveantivirus\Scanner', 'isSchemeScannable']);
  }

  /**
   * List which schemes have been overridden.
   *
   * @param string $type
   *   Type of stream-wrapper: either 'local' or 'remote'.
   * @param array $schemes_to_scan
   *   Unindexed array, listing the schemes that should be scanned.
   *
   * @return array
   *   List of the schemes that have been overridden for this particular
   *   stream-wrapper type.
   */
  public function getOverriddenSchemes($type, array $schemes_to_scan) {
    $available_schemes = $this->schemeWrappersAvailable($type);
    switch ($type) {
      case 'local':
        $overridden = array_diff_key($available_schemes, $schemes_to_scan);
        return array_keys($overridden);

      case 'remote':
        $overridden = array_intersect_key($available_schemes, $schemes_to_scan);
        return array_keys($overridden);
    }
  }

}
