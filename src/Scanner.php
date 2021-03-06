<?php

namespace Drupal\cloudmersiveantivirus;

use Drupal\cloudmersiveantivirus\Scanner\DaemonUnixSocket;
use Drupal\cloudmersiveantivirus\Scanner\DaemonTCPIP;
use Drupal\cloudmersiveantivirus\Scanner\Executable;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\file\FileInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Service class for the CloudmersiveAntivirus scanner instance.
 *
 * Passes the methods "scan" and "version" to a specific handler, according to
 * the configuration.
 */
class Scanner {

  // Constants defining the infection state of a specific file.
  const FILE_IS_UNCHECKED = -1;
  const FILE_IS_CLEAN     = 0;
  const FILE_IS_INFECTED  = 1;

  // Constants defining whether a specific file should be scanned.
  const FILE_IS_SCANNABLE     = TRUE;
  const FILE_IS_NOT_SCANNABLE = FALSE;
  const FILE_SCANNABLE_IGNORE = NULL;


  /**
   * Scanner object.
   *
   * @var InstanceofascannerclassimplementingScannerInterface
   */
  protected $scanner = NULL;

  /**
   * Config object.
   *
   * @var CloudmersiveAntivirusconfiguration
   */
  protected $config = NULL;

  /**
   * Constructor.
   *
   * @param object $config
   *   An instance of \Drupal\cloudmersiveantivirus\Config.
   */
  public function __construct($config) {
    $this->config = $config;

    switch ($config->scanMode()) {
      case Config::MODE_EXECUTABLE:
        $this->scanner = new Executable($this->config);
        break;

      case Config::MODE_DAEMON:
        $this->scanner = new DaemonTCPIP($this->config);
        break;

      case Config::MODE_UNIX_SOCKET:
        $this->scanner = new DaemonUnixSocket($this->config);
        break;
    }
  }

  /**
   * Check whether the anti-virus checks are enabled.
   *
   * @return bool
   *   TRUE if files should be scanned.
   */
  public function isEnabled() {
    return $this->config->enabled();
  }

  /**
   * Check whether files that have not been scanned can be uploaded.
   *
   * @return bool
   *   TRUE if unchecked files are permitted.
   */
  public function allowUncheckedFiles() {
    return $this->config->outageAction() === Config::OUTAGE_ALLOW_UNCHECKED;
  }

  /**
   * Check whether files that have not been scanned can be uploaded.
   *
   * @return bool
   *   TRUE if unchecked files are permitted.
   */
  public function isVerboseModeEnabled() {
    return $this->config->verbosity();
  }

  /**
   * Check whether a specific file should be scanned by CloudmersiveAntivirus.
   *
   * Specific files can be excluded from anti-virus scanning, such as:
   * - Image files
   * - Large files that might take a long time to scan
   * - Files uploaded by trusted administrators
   * - Viruses, intended to be deliberately uploaded to a virus database.
   *
   * Files can be excluded from the scans by implementing
   * hook_cloudmersiveantivirus_file_is_scannable().
   *
   * @see hook_cloudmersiveantivirus_file_is_scannable()
   *
   * @return bool
   *   TRUE if a file should be scanned by the anti-virus service.
   */
  public function isScannable(FileInterface $file) {
    // Check whether this stream-wrapper scheme is scannable.
    if (!empty($file->destination)) {
      $scheme = StreamWrapperManager::getScheme($file->destination);
    }
    else {
      $scheme = StreamWrapperManager::getScheme($file->getFileUri());
    }
    $scannable = self::isSchemeScannable($scheme);

    // Iterate each module implementing
    // hook_cloudmersiveantivirus_file_is_scannable().
    // Modules that do not wish to affact the result should return
    // FILE_SCANNABLE_IGNORE.
    foreach (\Drupal::moduleHandler()->getImplementations('cloudmersiveantivirus_file_is_scannable') as $module) {
      $result = \Drupal::moduleHandler()->invoke($module, 'cloudmersiveantivirus_file_is_scannable', [$file]);
      if ($result !== self::FILE_SCANNABLE_IGNORE) {
        $scannable = $result;
      }
    }

    return $scannable;
  }

  /**
   * Scan a file for viruses.
   *
   * @param Drupal\file\FileInterface $file
   *   The file to scan for viruses.
   *
   * @return int
   *   One of the following class constants:
   *   - CLOUDMERSIVEAV_SCANRESULT_UNCHECKED
   *     The file was not scanned.
   *     The CloudmersiveAntivirus service may be unavailable.
   *   - CLOUDMERSIVEAV_SCANRESULT_CLEAN
   *     The file was scanned, and no infection was found.
   *   - CLOUDMERSIVEAV_SCANRESULT_INFECTED
   *     The file was scanned, and found to be infected with a virus.
   */
  public function scan(FileInterface $file) {
    // Empty files are never infected.
    if ($file->getSize() === 0) {
      return self::FILE_IS_CLEAN;
    }

    $result = $this->scanner->scan($file);

    // Prepare to log results.
    $verbose_mode = $this->config->verbosity();
    $replacements = [
      '%filename'  => $file->getFileUri(),
      '%virusname' => $this->scanner->virusName(),
    ];

    switch ($result) {
      // Log every infected file.
      case self::FILE_IS_INFECTED:
        $message = 'Virus %virusname detected in uploaded file %filename.';
        \Drupal::logger('Cloudmersive Antivirus')->error($message, $replacements);
        break;

      // Log clean files if verbose mode is enabled.
      case self::FILE_IS_CLEAN:
        if ($verbose_mode) {
          $message = 'Uploaded file %filename checked and found clean.';
          \Drupal::logger('Cloudmersive Antivirus')->info($message, $replacements);
        }
        break;

      // Log unchecked files if they are accepted, or verbose mode is enabled.
      case self::FILE_IS_UNCHECKED:
        if ($this->config->outageAction() === Config::OUTAGE_ALLOW_UNCHECKED) {
          $message = 'Uploaded file %filename could not be checked, and was uploaded without checking.';
          \Drupal::logger('Cloudmersive Antivirus')->notice($message, $replacements);
        }
        elseif ($verbose_mode) {
          $message = 'Uploaded file %filename could not be checked, and was deleted.';
          \Drupal::logger('Cloudmersive Antivirus')->info($message, $replacements);
        }
        break;
    }
    return $result;
  }

  /**
   * The version of the CloudmersiveAntivirus service.
   *
   * @return string
   *   The version number provided by CloudmersiveAntivirus.
   */
  public function version() {
    return $this->scanner->version();
  }

  /**
   * Determine whether files of a given scheme should be scanned.
   *
   * @param string $scheme
   *   The machine name of a stream-wrapper scheme, such as "public", or
   *   "youtube".
   *
   * @return bool
   *   TRUE if the scheme should be scanned.
   */
  public static function isSchemeScannable($scheme) {
    if (empty($scheme)) {
      return TRUE;
    }

    // By default all local schemes should be scannable.
    $mgr = \Drupal::service('stream_wrapper_manager');
    $local_schemes = array_keys($mgr->getWrappers(StreamWrapperInterface::LOCAL));
    $scheme_is_local = in_array($scheme, $local_schemes);

    // The default can be overridden per scheme.
    $config = \Drupal::config('cloudmersiveantivirus.settings');
    $overridden_schemes = $config->get('overridden_schemes');
    $scheme_is_overridden = in_array($scheme, $overridden_schemes);

    return ($scheme_is_local xor $scheme_is_overridden);
  }

}
