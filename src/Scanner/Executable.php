<?php

namespace Drupal\cloudmersiveantivirus\Scanner;

use Drupal\file\FileInterface;
use Drupal\cloudmersiveantivirus\ScannerInterface;
use Drupal\cloudmersiveantivirus\Scanner;
use Drupal\cloudmersiveantivirus\Config;

/**
 * Class of scanner item.
 */
class Executable implements ScannerInterface {
  /**
   * The Executable path.
   *
   * @var executablepath
   */
  private $executablePath = '';
  /**
   * The Executable parameters.
   *
   * @var executableparameters
   */
  private $executableParameters = '';
  /**
   * The file.
   *
   * @var file
   */
  private $file = '';
  /**
   * The virus Name.
   *
   * @var virusname
   */
  protected $virusName = '';

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config) {
    $this->_executable_path       = $config->get('mode_executable.executable_path');
    $this->_executable_parameters = $config->get('mode_executable.executable_parameters');
  }

  /**
   * {@inheritdoc}
   */
  public function scan(FileInterface $file) {

    // Verify that the executable exists.
    // Redirect STDERR to STDOUT to capture
    // the full output of the CloudmersiveAntivirus script.
    $script = "{$this->_executable_path} {$this->_executable_parameters}";
    $filename = \Drupal::service('file_system')->realpath($file->getFileUri());

    $cmd = escapeshellcmd($script) . ' ' . escapeshellarg($filename) . ' 2>&1';

    $config = \Drupal::config('cloudmersiveantivirus.settings');
    $timeout = !empty($config->get('curl_timeout_value')) ? $config->get('curl_timeout_value') : 30;

    // Text output from the executable is assigned to: $output
    // Return code from the executable is assigned to: $return_code.
    // Possible return codes (see `man clamscan`):
    // - 0 = No virus found.
    // - 1 = Virus(es) found.
    // - 2 = Some error(s) occured.
    $curl = curl_init();

    $key = $this->_executable_path;

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.cloudmersive.com/virus/scan/file",
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_HTTPHEADER => [
        "cache-control: no-cache",
        "Apikey: " . $key,
        "content-type: multipart/form-data",
      ],
      CURLOPT_POSTFIELDS => [
        'inputFile' => new \CURLFile($filename),
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {

    }
    else {

    }

    $strResponse = (string) $response;

    if (strpos($strResponse, '"CleanResult":true') !== FALSE) {

      return Scanner::FILE_IS_CLEAN;
    }
    else {

      return Scanner::FILE_IS_INFECTED;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function virusName() {
    return $this->virusName;
  }

  /**
   * {@inheritdoc}
   */
  public function version() {

    return "1";

  }

}
