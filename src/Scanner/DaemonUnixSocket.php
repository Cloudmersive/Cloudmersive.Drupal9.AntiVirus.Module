<?php

namespace Drupal\cloudmersiveantivirus\Scanner;

use Drupal\file\FileInterface;
use Drupal\cloudmersiveantivirus\ScannerInterface;
use Drupal\cloudmersiveantivirus\Scanner;
use Drupal\cloudmersiveantivirus\Config;

/**
 * Class of scanner item.
 */
class DaemonUnixSocket implements ScannerInterface {
  /**
   * The file.
   *
   * @var file
   */
  protected $file;
  /**
   * The Unix Socket.
   *
   * @var unixsocket
   */
  protected $unixSocket;
  /**
   * The Virus Name.
   *
   * @var virusname
   */
  protected $virusName = '';

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config) {
    $this->_unix_socket = $config->get('mode_daemon_unixsocket.unixsocket');
  }

  /**
   * {@inheritdoc}
   */
  public function scan(FileInterface $file) {
    // Attempt to open a socket to the CloudmersiveAntivirus host and the file.
    $file_handler    = fopen($file->getFileUri(), 'r');
    $scanner_handler = @fsockopen("unix://{$this->_unix_socket}", 0);

    // Abort if the CloudmersiveAntivirus server is unavailable.
    if (!$scanner_handler) {
      \Drupal::logger('Cloudmersive Antivirus')->warning('Unable to connect to CloudmersiveAntivirus daemon on unix socket @unix_socket', ['@unix_socket' => $this->_unix_socket]);
      return Scanner::FILE_IS_UNCHECKED;
    }

    // Push to the CloudmersiveAntivirus socket.
    $bytes = $file->getSize();
    fwrite($scanner_handler, "zINSTREAM\0");
    fwrite($scanner_handler, pack("N", $bytes));
    stream_copy_to_stream($file_handler, $scanner_handler);

    // Send a zero-length block to indicate that we're done sending file data.
    fwrite($scanner_handler, pack("N", 0));

    // Request a response from the service.
    $response = trim(fgets($scanner_handler));

    fclose($scanner_handler);

    if (preg_match('/^stream: OK$/', $response)) {
      $result = Scanner::FILE_IS_CLEAN;
    }
    elseif (preg_match('/^stream: (.*) FOUND$/', $response, $matches)) {
      $this->virusName = $matches[1];
      $result = Scanner::FILE_IS_INFECTED;
    }
    else {
      preg_match('/^stream: (.*) ERROR$/', $response, $matches);
      $result = Scanner::FILE_IS_UNCHECKED;
    }

    return $result;
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
    $handler = @fsockopen("unix://{$this->_unix_socket}", 0);
    if (!$handler) {
      \Drupal::logger('Cloudmersive Antivirus')->warning('Unable to connect to CloudmersiveAntivirus daemon on unix socket @unix_socket', ['@unix_socket' => $this->_unix_socket]);
      return NULL;
    }

    fwrite($handler, "VERSION\n");
    $content = fgets($handler);
    fclose($handler);
    return $content;
  }

}
