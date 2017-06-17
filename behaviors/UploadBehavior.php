<?php

namespace vova07\fileapi\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\helpers\ArrayHelper;
use yii\validators\Validator;
use vova07\fileapi\actions\UploadAction;

/**
 * Class UploadBehavior
 * @package vova07\fileapi\behaviors
 * Uploading file behavior.
 *
 * @property ActiveRecord $owner Description
 *
 * Usage:
 * ```
 * ...
 * 'uploadBehavior' => [
 *     'class' => UploadBehavior::className(),
 *     'attributes' => [
 *         'preview_url' => [
 *             'url' => '/path/to/file'
 *         ],
 *         'image_url' => [
 *             'url' => '/path/to/file'
 *         ]
 *     ]
 * ]
 * ...
 * ```
 */
class UploadBehavior extends Behavior
{
    /**
     * @event Event that will be call after successful file upload
     */
    const EVENT_AFTER_UPLOAD = 'afterUpload';

    /**
     * Are available 1 index:
     * - `url` Path URL where file will be saved.
     *
     * @var array Attributes array
     */
    public $attributes = [];

    /**
     * @var \vova07\fileapi\FileAPI|array|string FileAPI object or the application component ID of the {@see FileAPI}
     */
    public $fileapi = 'fileapi';

    /**
     * @var boolean If `true` current attribute file will be deleted
     */
    public $unlinkOnSave = true;

    /**
     * @var boolean If `true` current attribute file will be deleted after model deletion
     */
    public $unlinkOnDelete = true;

    /**
     * @var array Publish path cache array
     */
    protected static $_cachePublishPath = [];

    /**
     *
     * @var array
     */
    private $_variants = [];

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        parent::attach($owner);

        if (!is_array($this->attributes) || empty($this->attributes)) {
            throw new InvalidParamException('Invalid or empty attributes array.');
        }

        $this->fileapi = \yii\di\Instance::ensure($this->fileapi, \vova07\fileapi\FileAPI::className());
        foreach ($this->attributes as $attribute => $config)
        {
            /*if (!isset($config['url']) || empty($config['url']))
            {
                $config['url'] = $this->publish($config['path']);
            }*/
            //$this->attributes[$attribute]['path'] = $this->fileapi->filesystem->getAdapter()->getPathPrefix();
            $this->attributes[$attribute]['tempPath'] = FileHelper::normalizePath(Yii::getAlias($this->fileapi->tempPath)) . DIRECTORY_SEPARATOR;
            //$this->attributes[$attribute]['url'] = rtrim($config['url'], '/') . '/';

            $validator = Validator::createValidator('string', $this->owner, $attribute);
            $this->owner->validators[] = $validator;
            unset($validator);
        }
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete'
        ];
    }

    /**
     * Function will be called before inserting the new record.
     */
    public function beforeInsert()
    {
        foreach ($this->attributes as $attribute => $config) {
            if ($this->owner->$attribute) {
                $this->saveAttribute($attribute);
            }
        }
    }

    /**
     * Save file attribute.
     *
     * @param string $attribute Attribute name
     */
    protected function saveAttribute($attribute)
    {
        if(!empty($this->owner->getAttribute($attribute)))
        {
            if($this->saveFile($attribute))
            {
                $this->triggerEventAfterUpload();
            }else{
                $this->owner->setAttribute($attribute, $this->owner->getOldAttribute($attribute));
                return false;
            }
        }
        if ($this->unlinkOnSave && $this->owner->isAttributeChanged($attribute) && !empty($this->owner->getOldAttribute($attribute)))
        {
            foreach ($this->getVariants() as $variant)
            {
                $this->deleteFile($this->oldFile($attribute,$variant));
            }
        }

        return true;
    }

    /**
     * Итак. 1. Если ставить трансформацию на клиенте и включить передачу оригинала то будут ключи из вариантов трансформации + original
     * 2. Если передачу оригинала отключить, то будут только ключи из трансформации(!) основной файл тогда должен быть задан.
     * 3. При отключенной трансформации сохранится только один файл с ключом original
     * В любом случае то что идет с ключом original считается основным файлом.
     * @param string $attribute
     */
    protected function saveFile($attribute)
    {
        $mimeType = FileHelper::getMimeType($this->tempFile($attribute,'original'));
        foreach ($this->getVariants() as $variant){
            if(!is_file($this->tempFile($attribute,$variant)))
            {
                return false;
            }
        }
        foreach ($this->getVariants() as $variant) {
            $tmpFile = $this->tempFile($attribute,$variant);
            $resource = fopen($tmpFile,'r');
            $this->fileapi->filesystem->writeStream($this->file($attribute,$variant), $resource,['ContentType'=> $mimeType]);
            fclose($resource);
            unlink($tmpFile);
        }
        return true;
    }

    /**
     *
     * @return array
     */
    private function getVariants()
    {
        if(!$this->_variants)
        {
            $this->_variants = array_unique(ArrayHelper::merge(['original'], array_keys($this->fileapi->imageTransforms)));
        }
        return $this->_variants;
    }

    /**
     * Delete specified file.
     *
     * @param string $file File name
     * @return bool `true` if file was successfully deleted
     */
    protected function deleteFile($file)
    {
        if($this->fileapi->filesystem->has($file))
        {
            return $this->fileapi->filesystem->delete($file);
        }
        return false;
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return string Old file path
     */
    public function oldFile($attribute,$variant)
    {
        return $this->path($variant) . $this->owner->getOldAttribute($attribute);
    }

    /**
     * @param string $variant Attribute name
     *
     * @return string Path to file
     */
    public function path($variant)
    {
        return $variant == 'original'?'': $variant . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return string Temporary file path
     */
    public function tempFile($attribute, $variant)
    {
        return $this->tempPath($attribute) . $variant . UploadAction::VARIANT_SEPARATOR . $this->owner->$attribute;
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return string Path to temporary file
     */
    public function tempPath($attribute)
    {
        return $this->attributes[$attribute]['tempPath'];
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return string File path
     */
    public function file($attribute,$variant)
    {
        return $this->path($variant) . $this->owner->$attribute;
    }

    /**
     * Publish given path.
     *
     * @param string $path Path
     *
     * @return string Published url (/assets/images/image1.png)
     */
    public function publish($path)
    {
        if (!isset(static::$_cachePublishPath[$path])) {
            static::$_cachePublishPath[$path] = Yii::$app->assetManager->publish($path)[1];
        }
        return static::$_cachePublishPath[$path];
    }

    /**
     * Trigger [[EVENT_AFTER_UPLOAD]] event.
     */
    protected function triggerEventAfterUpload()
    {
        $this->owner->trigger(self::EVENT_AFTER_UPLOAD);
    }

    /**
     * Function will be called before updating the record.
     */
    public function beforeUpdate()
    {
        foreach ($this->attributes as $attribute => $config) {
            if ($this->owner->isAttributeChanged($attribute)) {
                $this->saveAttribute($attribute);
            }
        }
    }

    /**
     * Function will be called before deleting the record.
     */
    public function beforeDelete()
    {
        if ($this->unlinkOnDelete) {
            foreach ($this->attributes as $attribute => $config) {
                if ($this->owner->$attribute) {
                    foreach ($this->getVariants() as $variant) {
                        $this->deleteFile($this->file($attribute,$variant));
                    }
                }
            }
        }
    }

    /**
     * Remove attribute and its file.
     *
     * @param string $attribute Attribute name
     *
     * @return bool Whenever the attribute and its file was removed
     */
    public function removeAttribute($attribute)
    {
        if (isset($this->attributes[$attribute])) {
            if ($this->deleteFile($this->file($attribute))) {
                return $this->owner->updateAttributes([$attribute => null]);
            }
        }

        return false;
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return null|string Full attribute URL
     */
    public function urlAttribute($attribute,$variant = '')
    {
        if(!empty($variant))
        {
            $variant .= '/';
        }
        if (isset($this->attributes[$attribute]) && $this->owner->$attribute) {
            return (isset($this->fileapi->url)?"{$this->fileapi->url}":$this->attributes[$attribute]['url']) . "{$variant}{$this->owner->$attribute}";
        }

        return null;
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return string Attribute mime-type
     */
    public function getMimeType($attribute)
    {
        return $this->fileapi->filesystem->getMimetype($this->file($attribute,'original'));
    }

    /**
     * @param string $attribute Attribute name
     *
     * @return boolean Whether file exist or not
     */
    public function fileExists($attribute)
    {
        return $this->fileapi->filesystem->has($this->file($attribute,'original'));
    }
}
