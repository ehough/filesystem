<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Provides basic utility to manipulate the file system.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ehough_filesystem_Filesystem implements ehough_filesystem_FilesystemInterface
{
    /**
     * Get the absolute path of a temporary directory, preferably the system directory.
     *
     * @return string The absolute path of a temporary directory, preferably the system directory.
     */
    public final function getSystemTempDirectory()
    {
        if (function_exists('sys_get_temp_dir')) {

            return realpath(sys_get_temp_dir());
        }

        return $this->getSimulatedSystemTempDirectory();
    }

    /**
     * Copies a file.
     *
     * This method only copies the file if the origin file is newer than the target file.
     *
     * By default, if the target already exists, it is not overridden.
     *
     * @param string  $originFile The original filename
     * @param string  $targetFile The target filename
     * @param bool    $override   Whether to override an existing file or not
     *
     * @throws ehough_filesystem_exception_FileNotFoundException    When originFile doesn't exist
     * @throws ehough_filesystem_exception_IOException              When copy fails
     */
    public function copy($originFile, $targetFile, $override = false)
    {
        if (stream_is_local($originFile) && !is_file($originFile)) {
            throw new ehough_filesystem_exception_FileNotFoundException(sprintf('Failed to copy "%s" because file does not exist.', $originFile), 0, null, $originFile);
        }

        $this->mkdir(dirname($targetFile));

        if (!$override && is_file($targetFile) && null === parse_url($originFile, PHP_URL_HOST)) {
            $doCopy = filemtime($originFile) > filemtime($targetFile);
        } else {
            $doCopy = true;
        }

        if ($doCopy) {
            // https://bugs.php.net/bug.php?id=64634
            $source = fopen($originFile, 'r');
            // Stream context created to allow files overwrite when using FTP stream wrapper - disabled by default
            $target = fopen($targetFile, 'w', null, stream_context_create(array('ftp' => array('overwrite' => true))));
            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            unset($source, $target);

            if (!is_file($targetFile)) {
                throw new ehough_filesystem_exception_IOException(sprintf('Failed to copy "%s" to "%s".', $originFile, $targetFile), 0, null, $originFile);
            }
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param string|array|Traversable $dirs The directory path
     * @param int                      $mode The directory mode
     *
     * @throws ehough_filesystem_exception_IOException On any directory creation failure
     */
    public function mkdir($dirs, $mode = 0777)
    {
        foreach ($this->toIterator($dirs) as $dir) {
            if (is_dir($dir)) {
                continue;
            }

            if (true !== @mkdir($dir, $mode, true)) {
                $error = error_get_last();
                if (!is_dir($dir)) {
                    // The directory was not created by a concurrent process. Let's throw an exception with a developer friendly error message if we have one
                    if ($error) {
                        throw new ehough_filesystem_exception_IOException(sprintf('Failed to create "%s": %s.', $dir, $error['message']), 0, null, $dir);
                    }
                    throw new ehough_filesystem_exception_IOException(sprintf('Failed to create "%s"', $dir), 0, null, $dir);
                }
            }
        }
    }

    /**
     * Checks the existence of files or directories.
     *
     * @param string|array|Traversable $files A filename, an array of files, or a Traversable instance to check
     *
     * @return bool    true if the file exists, false otherwise
     */
    public function exists($files)
    {
        foreach ($this->toIterator($files) as $file) {
            if (!file_exists($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets access and modification time of file.
     *
     * @param string|array|Traversable $files A filename, an array of files, or a Traversable instance to create
     * @param int                      $time  The touch time as a Unix timestamp
     * @param int                      $atime The access time as a Unix timestamp
     *
     * @throws ehough_filesystem_exception_IOException When touch fails
     */
    public function touch($files, $time = null, $atime = null)
    {
        foreach ($this->toIterator($files) as $file) {
            $touch = $time ? @touch($file, $time, $atime) : @touch($file);
            if (true !== $touch) {
                throw new ehough_filesystem_exception_IOException(sprintf('Failed to touch "%s".', $file), 0, null, $file);
            }
        }
    }

    /**
     * Removes files or directories.
     *
     * @param string|array|Traversable $files A filename, an array of files, or a Traversable instance to remove
     *
     * @throws ehough_filesystem_exception_IOException When removal fails
     */
    public function remove($files)
    {
        $files = iterator_to_array($this->toIterator($files));
        $files = array_reverse($files);
        foreach ($files as $file) {
            if (!file_exists($file) && !is_link($file)) {
                continue;
            }

            if (is_dir($file) && !is_link($file)) {

                if (version_compare(PHP_VERSION, '5.3.0') < 0) {

                    $this->remove(new ehough_filesystem_iterator_SkipDotsRecursiveDirectoryIterator($file));

                } else {

                    $this->remove(new FilesystemIterator($file));
                }

                if (true !== @rmdir($file)) {
                    throw new ehough_filesystem_exception_IOException(sprintf('Failed to remove directory "%s".', $file), 0, null, $file);
                }
            } else {
                // https://bugs.php.net/bug.php?id=52176
                if (defined('PHP_WINDOWS_VERSION_MAJOR') && is_dir($file)) {
                    if (true !== @rmdir($file)) {
                        throw new ehough_filesystem_exception_IOException(sprintf('Failed to remove file "%s".', $file), 0, null, $file);
                    }
                } else {
                    if (true !== @unlink($file)) {
                        throw new ehough_filesystem_exception_IOException(sprintf('Failed to remove file "%s".', $file), 0, null, $file);
                    }
                }
            }
        }
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param string|array|Traversable $files     A filename, an array of files, or a Traversable instance to change mode
     * @param int                      $mode      The new mode (octal)
     * @param int                      $umask     The mode mask (octal)
     * @param bool                     $recursive Whether change the mod recursively or not
     *
     * @throws ehough_filesystem_exception_IOException When the change fail
     */
    public function chmod($files, $mode, $umask = 0000, $recursive = false)
    {
        foreach ($this->toIterator($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {

                if (version_compare(PHP_VERSION, '5.3.0') < 0) {

                    $this->chmod(new ehough_filesystem_iterator_SkipDotsRecursiveDirectoryIterator($file), $mode, $umask, true);

                } else {

                    $this->chmod(new FilesystemIterator($file), $mode, $umask, true);
                }
            }
            if (true !== @chmod($file, $mode & ~$umask)) {
                throw new ehough_filesystem_exception_IOException(sprintf('Failed to chmod file "%s".', $file), 0, null, $file);
            }
        }
    }

    /**
     * Change the owner of an array of files or directories
     *
     * @param string|array|Traversable $files     A filename, an array of files, or a Traversable instance to change owner
     * @param string                    $user      The new owner user name
     * @param bool                      $recursive Whether change the owner recursively or not
     *
     * @throws ehough_filesystem_exception_IOException When the change fail
     */
    public function chown($files, $user, $recursive = false)
    {
        foreach ($this->toIterator($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {

                if (version_compare(PHP_VERSION, '5.3.0') < 0) {

                    $this->chown(new ehough_filesystem_iterator_SkipDotsRecursiveDirectoryIterator($file), $user, true);

                } else {

                    $this->chown(new FilesystemIterator($file), $user, true);
                }
            }
            if (is_link($file) && function_exists('lchown')) {
                if (true !== @lchown($file, $user)) {
                    throw new ehough_filesystem_exception_IOException(sprintf('Failed to chown file "%s".', $file), 0, null, $file);
                }
            } else {
                if (true !== @chown($file, $user)) {
                    throw new ehough_filesystem_exception_IOException(sprintf('Failed to chown file "%s".', $file), 0, null, $file);
                }
            }
        }
    }

    /**
     * Change the group of an array of files or directories
     *
     * @param string|array|Traversable $files     A filename, an array of files, or a Traversable instance to change group
     * @param string                    $group     The group name
     * @param bool                      $recursive Whether change the group recursively or not
     *
     * @throws ehough_filesystem_exception_IOException When the change fail
     */
    public function chgrp($files, $group, $recursive = false)
    {
        foreach ($this->toIterator($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {

                if (version_compare(PHP_VERSION, '5.3.0') < 0) {

                    $this->chgrp(new ehough_filesystem_iterator_SkipDotsRecursiveDirectoryIterator($file), $group, true);

                } else {

                    $this->chgrp(new FilesystemIterator($file), $group, true);
                }

            }
            if (is_link($file) && function_exists('lchgrp')) {
                if (true !== @lchgrp($file, $group)) {
                    throw new ehough_filesystem_exception_IOException(sprintf('Failed to chgrp file "%s".', $file), 0, null, $file);
                }
            } else {
                if (true !== @chgrp($file, $group)) {
                    throw new ehough_filesystem_exception_IOException(sprintf('Failed to chgrp file "%s".', $file), 0, null, $file);
                }
            }
        }
    }

    /**
     * Renames a file or a directory.
     *
     * @param string  $origin    The origin filename or directory
     * @param string  $target    The new filename or directory
     * @param bool    $overwrite Whether to overwrite the target if it already exists
     *
     * @throws ehough_filesystem_exception_IOException When target file or directory already exists
     * @throws ehough_filesystem_exception_IOException When origin cannot be renamed
     */
    public function rename($origin, $target, $overwrite = false)
    {
        // we check that target does not exist
        if (!$overwrite && is_readable($target)) {
            throw new ehough_filesystem_exception_IOException(sprintf('Cannot rename because the target "%s" already exists.', $target), 0, null, $target);
        }

        if (true !== @rename($origin, $target)) {
            throw new ehough_filesystem_exception_IOException(sprintf('Cannot rename "%s" to "%s".', $origin, $target), 0, null, $target);
        }
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @param string  $originDir     The origin directory path
     * @param string  $targetDir     The symbolic link name
     * @param bool    $copyOnWindows Whether to copy files if on Windows
     *
     * @throws ehough_filesystem_exception_IOException When symlink fails
     */
    public function symlink($originDir, $targetDir, $copyOnWindows = false)
    {
        if (!function_exists('symlink') && $copyOnWindows) {
            $this->mirror($originDir, $targetDir);

            return;
        }

        $this->mkdir(dirname($targetDir));

        $ok = false;
        if (is_link($targetDir)) {
            if (readlink($targetDir) != $originDir) {
                $this->remove($targetDir);
            } else {
                $ok = true;
            }
        }

        if (!$ok) {
            if (true !== @symlink($originDir, $targetDir)) {
                $report = error_get_last();
                if (is_array($report)) {
                    if (defined('PHP_WINDOWS_VERSION_MAJOR') && false !== strpos($report['message'], 'error code(1314)')) {
                        throw new ehough_filesystem_exception_IOException('Unable to create symlink due to error code 1314: \'A required privilege is not held by the client\'. Do you have the required Administrator-rights?');
                    }
                }

                throw new ehough_filesystem_exception_IOException(sprintf('Failed to create symbolic link from "%s" to "%s".', $originDir, $targetDir), 0, null, $targetDir);
            }
        }
    }

    /**
     * Given an existing path, convert it to a path relative to a given starting path
     *
     * @param string $endPath   Absolute path of target
     * @param string $startPath Absolute path where traversal begins
     *
     * @return string Path of target relative to starting path
     */
    public function makePathRelative($endPath, $startPath)
    {
        // Normalize separators on Windows
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $endPath = strtr($endPath, '\\', '/');
            $startPath = strtr($startPath, '\\', '/');
        }

        // Split the paths into arrays
        $startPathArr = explode('/', trim($startPath, '/'));
        $endPathArr = explode('/', trim($endPath, '/'));

        // Find for which directory the common path stops
        $index = 0;
        while (isset($startPathArr[$index]) && isset($endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            $index++;
        }

        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        $depth = count($startPathArr) - $index;

        // Repeated "../" for each level need to reach the common path
        $traverser = str_repeat('../', $depth);

        $endPathRemainder = implode('/', array_slice($endPathArr, $index));

        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relativePath = $traverser.(strlen($endPathRemainder) > 0 ? $endPathRemainder.'/' : '');

        return (strlen($relativePath) === 0) ? './' : $relativePath;
    }

    /**
     * Mirrors a directory to another.
     *
     * @param string       $originDir The origin directory
     * @param string       $targetDir The target directory
     * @param Traversable $iterator  A Traversable instance
     * @param array        $options   An array of boolean options
     *                               Valid options are:
     *                                 - $options['override'] Whether to override an existing file on copy or not (see copy())
     *                                 - $options['copy_on_windows'] Whether to copy files instead of links on Windows (see symlink())
     *                                 - $options['delete'] Whether to delete files that are not in the source directory (defaults to false)
     *
     * @throws ehough_filesystem_exception_IOException When file type is unknown
     */
    public function mirror($originDir, $targetDir, Traversable $iterator = null, $options = array())
    {
        $targetDir = rtrim($targetDir, '/\\');
        $originDir = rtrim($originDir, '/\\');

        // Iterate in destination folder to remove obsolete entries
        if ($this->exists($targetDir) && isset($options['delete']) && $options['delete']) {
            $deleteIterator = $iterator;
            if (null === $deleteIterator) {

                if (version_compare(PHP_VERSION, '5.3.0') < 0) {

                    $deleteIterator = new RecursiveIteratorIterator(new ehough_filesystem_iterator_SkipDotsRecursiveDirectoryIterator($targetDir), RecursiveIteratorIterator::CHILD_FIRST);

                } else {

                    $flags = FilesystemIterator::SKIP_DOTS;
                    $deleteIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetDir, $flags), RecursiveIteratorIterator::CHILD_FIRST);
                }

            }
            foreach ($deleteIterator as $file) {
                $origin = str_replace($targetDir, $originDir, $file->getPathname());
                if (!$this->exists($origin)) {
                    $this->remove($file);
                }
            }
        }

        $copyOnWindows = false;
        if (isset($options['copy_on_windows']) && !function_exists('symlink')) {
            $copyOnWindows = $options['copy_on_windows'];
        }

        if (null === $iterator) {

            if (version_compare(PHP_VERSION, '5.3.0') < 0) {

                $iterator = new RecursiveIteratorIterator(new ehough_filesystem_iterator_SkipDotsRecursiveDirectoryIterator($originDir), RecursiveIteratorIterator::SELF_FIRST);

            } else {

                $flags = $copyOnWindows ? FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS : FilesystemIterator::SKIP_DOTS;
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($originDir, $flags), RecursiveIteratorIterator::SELF_FIRST);
            }
        }

        foreach ($iterator as $file) {
            $target = str_replace($originDir, $targetDir, $file->getPathname());

            if ($copyOnWindows) {
                if (is_link($file) || is_file($file)) {
                    $this->copy($file, $target, isset($options['override']) ? $options['override'] : false);
                } elseif (is_dir($file)) {
                    $this->mkdir($target);
                } else {
                    throw new ehough_filesystem_exception_IOException(sprintf('Unable to guess "%s" file type.', $file), 0, null, $file);
                }
            } else {
                if (is_link($file)) {
                    $this->symlink($file->getLinkTarget(), $target);
                } elseif (is_dir($file)) {
                    $this->mkdir($target);
                } elseif (is_file($file)) {
                    $this->copy($file, $target, isset($options['override']) ? $options['override'] : false);
                } else {
                    throw new ehough_filesystem_exception_IOException(sprintf('Unable to guess "%s" file type.', $file), 0, null, $file);
                }
            }
        }
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param string $file A file path
     *
     * @return bool
     */
    public function isAbsolutePath($file)
    {
        if (strspn($file, '/\\', 0, 1)
            || (strlen($file) > 3 && ctype_alpha($file[0])
                && substr($file, 1, 1) === ':'
                && (strspn($file, '/\\', 2, 1))
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
        ) {
            return true;
        }

        return false;
    }

    /**
     * This function should not be used externally, outside of testing.
     *
     * @return string The absolute path of a temporary directory, preferably the system directory.
     */
    public final function getSimulatedSystemTempDirectory()
    {
        $fromEnv = $this->_getFromEnvPaths(
            array(
                'TMP',
                'TEMP',
                'TMPDIR',
            )
        );

        if ($fromEnv !== null) {

            return $fromEnv;
        }

        $tempfile = tempnam(
            md5(
                uniqid(
                    rand(),
                    true
                )
            ),
            ''
        );

        if (is_file($tempfile)) {

            $tempdir = realpath(dirname($tempfile));

            unlink($tempfile);

            return realpath($tempdir);
        }

        return false;
    }

    /**
     * Try to fetch a temp path from environment variables.
     *
     * @param array $envKeys The environment variable names to check.
     *
     * @return null|string The first path found, null if none found.
     */
    private function _getFromEnvPaths(array $envKeys)
    {
        foreach ($envKeys as $key) {

            $value = getenv($key);

            if (! empty($value)) {

                return realpath($value);
            }
        }

        return null;
    }

    /**
     * Atomically dumps content into a file.
     *
     * @param  string       $filename The file to be written to.
     * @param  string       $content  The data to write into the file.
     * @param  null|int     $mode     The file mode (octal). If null, file permissions are not modified
     *                                Deprecated since version 2.3.12, to be removed in 3.0.
     * @throws ehough_filesystem_exception_IOException            If the file cannot be written to.
     */
    public function dumpFile($filename, $content, $mode = 0666)
    {
        $dir = dirname($filename);

        if (!is_dir($dir)) {
            $this->mkdir($dir);
        } elseif (!is_writable($dir)) {
            throw new ehough_filesystem_exception_IOException(sprintf('Unable to write to the "%s" directory.', $dir), 0, null, $dir);
        }

        $tmpFile = tempnam($dir, basename($filename));

        if (false === @file_put_contents($tmpFile, $content)) {
            throw new ehough_filesystem_exception_IOException(sprintf('Failed to write file "%s".', $filename), 0, null, $filename);
        }

        $this->rename($tmpFile, $filename, true);
        if (null !== $mode) {
            $this->chmod($filename, $mode);
        }
    }

    /**
     * @param mixed $files
     *
     * @return \Traversable
     */
    private function toIterator($files)
    {
        if (!$files instanceof Traversable) {
            $files = new ArrayObject(is_array($files) ? $files : array($files));
        }

        return $files;
    }
}
