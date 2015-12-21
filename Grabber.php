<?php

namespace grabber;

/**
 * PHP class to grab multiple images from specified URLs
 *
 * @namespace grabber
 * @file Grabber.php
 *
 * @author Denis Alexandrov <stm.switcher@gmail.com>
 * @date 12.12.2015 14:23:10
 */

class Grabber
{
    /** Timeout interval for single file */
    const TIMEOUT = 20;

    /** Relative path to store archive file. Trailing slash required */
    const ARCHIVE_PATH = '/';

    /** File with urls to grab images from */
    const INPUT_URLS = 'urls.txt';

    /** @var \ZipArchive Archive to work with */
    private $archive;

    /** @var string Absolute path to archive file */
    private $file_path;

    /** @var string Name of the archive to be created */
    private $file_name;

    /** @var int Offset for our input URLs */
    private $offset = 0;

    /** @var array input URLs to grab images from */
    private $urls;

    /** @var int count of URLs */
    private $urls_count;

    /**
     * List of bootstrap classes and short assosiations
     * @var array
     */
    private static $BUTTON_CLASSES = [
        'error' => 'btn-warning',
        'wait'  => 'btn-default',
        'complete' => 'btn-success',
        'error' => 'btn-danger',
    ];

    /**
     * Class constructor
     * Here we'll only check for PHP session and try to set class' offset.
     */
    public function __construct()
    {
        try {
            $this->checkSession();
            $this->setOffsetFromGet();
        } catch (\Exception $ex) {
            $this->error($ex);
        }
    }

    /**
     * Main method sequence which will initialize archive and process URLs.
     */
    public function process()
    {
        try {
            // Build archive's filename based on session ID
            $this->buildFilename();

            // If offset is equal to zero (the script runs for the first time) check
            // if file exists and try to remove it.
            $this->checkOffsetAndUnlinkFile();

            // Initialize archive (creating a new one or opening existing)
            $this->initArchive();

            // Read input URLs
            $this->readInput();

            // Processing current URL and close archive
            $this->processUrl();
        } catch (\Exception $ex) {
            $this->error($ex, false);
        }

        exit(0);
    }

    /**
     * Build filename based on session ID
     */
    private function buildFilename()
    {
        $this->file_name = substr(sha1(session_id()), 0, 8) . '.zip';
        $this->file_path = getcwd() . self::ARCHIVE_PATH;
    }

    /**
     * Remove file if offset equals 0
     */
    private function checkOffsetAndUnlinkFile()
    {
        if ($this->offset === 0)
            $this->unlinkFileIfPresent();
    }

    /**
     * Check if PHP session was started
     * @throws \Exception If no PHP session
     */
    private function checkSession()
    {
        switch(session_status()) {
            case PHP_SESSION_DISABLED:
                throw new \Exception("PHP sessions disabled. Unable to start the script.", 500);
            case PHP_SESSION_NONE:
                throw new \Exception("Session must be started before initializing script.", 400);
            default:
                return;
        }
    }

    /**
     * Return context stream for file_get_contents
     * @return resource
     */
    private function getContextStream()
    {
        return stream_context_create([
            'http' => [
                'timeout' => 20,
            ],
        ]);
    }

    /**
     * Finish archiving and return filepath within primary button
     */
    private function completeArchive()
    {
        $this->archive->close();
        $this->outputResult(true, 'Archive ready', 'Download', 'complete', 0, self::ARCHIVE_PATH . $this->file_name);
    }

    /**
     * Return error message and exit with status = 1
     * @param \Exception $ex
     * @param bool $fatal if script shouldn't proceed
     */
    private function error(\Exception $ex, $fatal = true)
    {
        $message = $ex->getCode() . ': ' . $ex->getMessage();

        $this->outputResult($fatal, $message, $fatal ? 'Error' : 'Skipping', 'error', $fatal ? 0 : ++$this->offset);

        if ($fatal && $this->archive instanceof \ZipArchive)
            $this->archive->close();

        exit(1);
    }

    /**
     * Get real image extension based on MIME-type
     * @param string $filename
     * @param string $url URL being processed
     * @return strring File extension
     * @throws \Exception if not image or unsupported filetype
     */
    private function getFileExtension($filename, $url)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $filename);

        $info = explode("/", $mime);
        if ($info[0] !== 'image')
            throw new \Exception($url . ': incorrect file type, skipping', 415);

        switch($info[1]) {
            case 'png':
                return 'png';
            case 'gif':
                return 'gif';
            case 'jpeg':
            case 'jpg':
            case 'pjpeg':
                return 'jpg';
            default:
                throw new \Exception($url . ': unsupported file type (' . $info[1] . ') skipping', 415);
        }
    }

    /**
     * Initialize archive
     * Create new file if offset equals zero
     * @return \ZipArchive
     */
    private function initArchive()
    {
        $this->archive = new \ZipArchive();
        $flags   = null;

        if ($this->offset == 0)
            $flags = \ZipArchive::CREATE;

        $this->archive->open($this->file_path . $this->file_name, $flags);
    }

    /**
     * Render results of the script's work
     * @param bool $end Script finished it's work
     * @param string $log_info Info to be outputed into AJAX-log
     * @param string $btn_text Text to set to primary button
     * @param string $btn_class Class to set to primary button {@see self::$BUTTON_CLASSES}
     * @param int $offset New offset to return to JavaScript
     * @param string $filepath URL to download complete archive
     */
    private function outputResult($end, $log_info, $btn_text, $btn_class, $offset, $filepath = null)
    {
        header('Content-Type: application/json');
        $btn_bootstrap_class = self::$BUTTON_CLASSES[$btn_class];

        echo json_encode(compact('end', 'log_info', 'btn_text', 'btn_bootstrap_class', 'offset', 'filepath'));
    }

    /**
     * Processing current url
     *
     * If current offset is equal to number of URLs in input we're closing
     * archive and sending download link.
     *
     * For each URL we have, we're grabbing contents with file_get_contents and
     * stream params as set in {@see getContextStream()}.
     * Key param for stream - is timeout. If data wasn't retrieved within 20 seconds
     * the URL will be scipped and script will proceed to the next one with this
     * information beign input into AJAX-log.
     *
     * Next, we create temporary file in /tmp dir, catching it's extension based
     * on MIME-type of the file and adding the file to archive.
     *
     * At the end of this method offset will be incremented.
     *
     * @throws \Exception if were unable to get data
     */
    private function processUrl()
    {
        if ($this->offset >= $this->urls_count) {
            $this->completeArchive();
            return;
        }

        $url  = $this->urls[$this->offset];
        $data = file_get_contents($url, 0, $this->getContextStream());

        if ( !$data )
            throw new \Exception("Unable to recieve $url, skipping");

        $tmp_name  = tempnam("/tmp", "zip_");
        file_put_contents($tmp_name, $data);

        $extension = $this->getFileExtension($tmp_name, $url);

        $path_info = pathinfo($tmp_name);
        $base_name = $path_info['basename'] . '.' . $extension;

        $this->archive->addFile($tmp_name, $base_name);
        $this->outputResult(false, $url . ' - success', ($this->offset + 1) . " / $this->urls_count", 'wait', ++$this->offset);

        $this->archive->close();
        unlink($tmp_name);
    }

    /**
     * Reads URLs from INPUT_URLS file and counts 'em
     */
    private function readInput()
    {
        $this->urls       = file(self::INPUT_URLS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->urls_count = sizeof($this->urls);
    }

    /**
     * Try to set offset from GET param
     * @throws Exception If no GET param 'offset' present
     */
    private function setOffsetFromGet()
    {
        $this->offset = filter_input(INPUT_GET, 'offset');

        if (is_null($this->offset))
            throw new \Exception('Unable to get offset', 424);
    }

    /**
     * Check if archive file is present and try to remove it.
     * @throws \Exception If unable to remove file
     */
    private function unlinkFileIfPresent()
    {
        if (!is_file($this->file_path . $this->file_name))
            return;

        $result = unlink($this->file_path . $this->file_name);
        if ($result)
            return;

        throw new \Exception('Unable to remove existing archive file ' . $this->file_path . $this->file_name, 403);
    }
}