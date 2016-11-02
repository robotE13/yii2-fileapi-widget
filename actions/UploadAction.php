<?php

namespace vova07\fileapi\actions;

use vova07\fileapi\Widget;
use yii\base\Action;
use yii\base\DynamicModel;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use vova07\fileapi\components\UploadedFile;
use Yii;

/**
 * UploadAction for images and files.
 *
 * Usage:
 * ```php
 * public function actions()
 * {
 *     return [
 *         'upload' => [
 *             'class' => 'vova07\fileapi\actions\UploadAction',
 *             'path' => '@path/to/files',
 *             'uploadOnlyImage' => false
 *         ]
 *     ];
 * }
 * ```
 */
class UploadAction extends Action
{
    /**
     * @var string Path to directory where files will be uploaded
     */
    public $path;

    /**
     * @var string Validator name
     */
    public $uploadOnlyImage = true;

    /**
     * @var string The parameter name for the file form data (the request argument name).
     */
    public $paramName = 'file';

    /**
     * @var boolean If `true` unique filename will be generated automatically
     */
    public $unique = true;

    /**
     * @var array Model validator options
     */
    public $validatorOptions = [];

    /**
     * @var string Model validator name
     */
    private $_validator = 'image';

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->path === null) {
            throw new InvalidConfigException('The "path" attribute must be set.');
        } else {
            $this->path = FileHelper::normalizePath(Yii::getAlias($this->path)) . DIRECTORY_SEPARATOR;

            if (!FileHelper::createDirectory($this->path)) {
                throw new InvalidCallException("Directory specified in 'path' attribute doesn't exist or cannot be created.");
            }
        }
        if ($this->uploadOnlyImage !== true) {
            $this->_validator = 'file';
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        if (Yii::$app->request->isPost) {
            
            Yii::$app->response->format = Response::FORMAT_JSON;
            $files = UploadedFile::getInstancesByName($this->paramName);
            $result = [];
            foreach ($files as $key => $file)
            {
                FileHelper::createDirectory($this->path . $key . DIRECTORY_SEPARATOR);
                $result[$key]=$this->saveTempFile($file,$key);
            }
            return \yii\helpers\ArrayHelper::getValue($result,'original');
        } else {
            throw new BadRequestHttpException('Only POST is allowed');
        }
    }
    
    protected function saveTempFile(UploadedFile $file, $variant)
    {
        $model = new DynamicModel(compact('file'));
        $model->addRule('file', $this->_validator, $this->validatorOptions)->validate();

        if ($model->hasErrors()) {
            $result = [
                'error' => $model->getFirstError('file')
            ];
        } else {
            if ($this->unique === true && $model->file->extension) {
                $model->file->name = uniqid() . '.' . $model->file->extension;
            }
            if ($model->file->saveAs($this->path . $variant . DIRECTORY_SEPARATOR . $model->file->name)) {
                $result = ['name' => $variant . DIRECTORY_SEPARATOR . $model->file->name];
            } else {
                $result = ['error' => Widget::t('fileapi', 'ERROR_CAN_NOT_UPLOAD_FILE')];
            }
        }         

        return $result;
    }
}
