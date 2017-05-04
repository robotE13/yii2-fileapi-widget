<?php

/*
 * The MIT License
 *
 * Copyright 2017 Tartharia.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace vova07\fileapi\adapters;

use Yii;

/**
 * Description of Local
 *
 * @author Tartharia
 */
class Local extends \League\Flysystem\Adapter\Local implements \yii\base\Configurable
{
    public $maxFilesPerDir = 1024;

    public $applySharding;

    private $dirindex;
    public function __construct($root, $writeFlags = LOCK_EX, $linkHandling = self::DISALLOW_LINKS, array $permissions = array(),array $config=[]) {

        parent::__construct($root, $writeFlags, $linkHandling, $permissions);
        if (!empty($config)) {
            Yii::configure($this, $config);
        }
    }
    public function writeStream($path, $resource, \League\Flysystem\Config $config)
    {
        return parent::writeStream($path, $resource, $config);
    }

        /**
     * @return false|int|string
     */
    protected function getDirIndex()
    {
        if (!$this->getFilesystem()->has('.dirindex')) {
            $this->getFilesystem()->write('.dirindex', (string) $this->dirindex);
        } else {
            $this->dirindex = $this->getFilesystem()->read('.dirindex');
            if ($this->maxDirFiles !== -1) {
                $filesCount = count($this->getFilesystem()->listContents($this->dirindex));
                if ($filesCount > $this->maxDirFiles) {
                    $this->dirindex++;
                    $this->getFilesystem()->put('.dirindex', (string) $this->dirindex);
                }
            }
        }
        return $this->dirindex;
    }
}
