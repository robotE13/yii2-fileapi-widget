<?php

/*
 * The MIT License
 *
 * Copyright 2016 Tartharia.
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

namespace vova07\fileapi;

use Yii;
use yii\di\Instance;
use League\Flysystem\Filesystem;

/**
 * Description of FileAPI
 *
 * @author Tartharia
 */
class FileAPI extends \yii\base\Component
{

    /**
     * Path alias for temporary files directory.
     * @var string
     */
    public $tempPath = '@app/runtime';

    /**
     * Filesystem component {@see http://flysystem.thephpleague.com/}.
     * @var \League\Flysystem\Filesystem|\Closure|string
     */
    public $filesystem;

    /**
     * Rules of image transformation on the client {@see https://github.com/mailru/FileAPI#imagetransformobject-1}
     * @var array ['variantName'=>['maxWidth'=>800, 'maxHeight'=>600],'variant2Name'=>[...]
     */
    public $imageTransforms = [];

    /**
     * Sent to the server the original image or not, if defined imageTransform option.
     * @var boolean default: true
     */
    public $imageOriginal = true;

    public function init()
    {
        if($this->imageTransforms && !$this->imageOriginal && !key_exists('original', $this->imageTransforms))
        {
            throw new \yii\base\InvalidConfigException('When disabled the transmission of the original image, and set transformation variants, you must specify the key "original". This image will be considered as the base image.');
        }
        if($this->filesystem instanceof \Closure)
        {
            $this->filesystem = call_user_func($this->filesystem);
        }
        $this->filesystem = Instance::ensure($this->filesystem, '\League\Flysystem\Filesystem');
    }
}
