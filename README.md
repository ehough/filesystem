# filesystem [![Build Status](https://secure.travis-ci.org/ehough/filesystem.png)](http://travis-ci.org/ehough/filesystem)

Fork of [Symfony's Filesystem component](https://github.com/symfony/Filesystem) compatible with PHP 5.2+.

### Differences from [Symfony's Filesystem component](https://github.com/symfony/EventDispatcher)

The primary difference is naming conventions of the Symfony classes.
Instead of the `\Symfony\Component\Filesystem` namespace (and sub-namespaces), prefix the Symfony class names
with `ehough_filesystem` and follow the [PEAR naming convention](http://pear.php.net/manual/en/standards.php)

An examples of class naming conversion:

    \Symfony\Component\Filesystem\Filesystem   ----->    ehough_filesystem_Filesystem

### Usage

```php
<?php

$filesystem = new ehough_filesystem_Filesystem();

$filesystem->copy($originFile, $targetFile, $override = false);

$filesystem->mkdir($dirs, $mode = 0777);

$filesystem->touch($files, $time = null, $atime = null);

$filesystem->remove($files);

$filesystem->chmod($files, $mode, $umask = 0000, $recursive = false);

$filesystem->chown($files, $user, $recursive = false);

$filesystem->chgrp($files, $group, $recursive = false);

$filesystem->rename($origin, $target);

$filesystem->symlink($originDir, $targetDir, $copyOnWindows = false);

$filesystem->makePathRelative($endPath, $startPath);

$filesystem->mirror($originDir, $targetDir, \Traversable $iterator = null, $options = array());

$filesystem->isAbsolutePath($file);
```