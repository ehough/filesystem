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
 * Test class for Filesystem.
 */
class ehough_filesystem_ExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testGetPath()
    {
        $e = new ehough_filesystem_exception_IOException('', 0, null, '/foo');
        $this->assertEquals('/foo', $e->getPath(), 'The pass should be returned.');
    }

    public function testGeneratedMessage()
    {
        $e = new ehough_filesystem_exception_FileNotFoundException(null, 0, null, '/foo');
        $this->assertEquals('/foo', $e->getPath());
        $this->assertEquals('File "/foo" could not be found.', $e->getMessage(), 'A message should be generated.');
    }

    public function testGeneratedMessageWithoutPath()
    {
        $e = new ehough_filesystem_exception_FileNotFoundException();
        $this->assertEquals('File could not be found.', $e->getMessage(), 'A message should be generated.');
    }

    public function testCustomMessage()
    {
        $e = new ehough_filesystem_exception_FileNotFoundException('bar', 0, null, '/foo');
        $this->assertEquals('bar', $e->getMessage(), 'A custom message should be possible still.');
    }
}
