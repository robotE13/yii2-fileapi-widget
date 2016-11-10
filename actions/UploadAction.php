<?php

namespace vova07\fileapi\actions;

use Yii;
use yii\helpers\ArrayHelper;
use vova07\fileapi\Widget;
use vova07\fileapi\FileAPI;
use yii\base\Action;
use yii\base\DynamicModel;
use yii\base\InvalidCallException;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\di\Instance;
use vova07\fileapi\components\UploadedFile;

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
    const VARIANT_SEPARATOR = '!_!';
    /**
     * @var FileAPI|array|string FileAPI object or the application component ID of the {@see FileAPI}
     */
    public $fileapi = 'fileapi';

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
     * @var boolean saving result
     */
    protected $result = true;

    /**
     * @var string Path to directory where temp files will be uploaded.
     */
    protected $path;

    /**
     * @var string Model validator name
     */
    private $_validator = 'image';

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->fileapi = Instance::ensure($this->fileapi, FileAPI::className());
        $this->path = FileHelper::normalizePath(Yii::getAlias($this->fileapi->tempPath)) . DIRECTORY_SEPARATOR;

        if (!FileHelper::createDirectory($this->path)) {
            throw new InvalidCallException("Directory specified in 'path' attribute doesn't exist or cannot be created.");
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

            return $this->saveTempFiles($files);

        } else {
            throw new BadRequestHttpException('Only POST is allowed');
        }
    }

    /**
     * Save uploaded files into temp directory.
     * @param UploadedFile[] $files
     * @return [] uploaded file name or error text
     */
    protected function saveTempFiles($files)
    {
        $basename = '';
        while (($file = current($files)) && !isset($result)) {
            $model = new DynamicModel(compact('file'));
            $model->addRule('file', $this->_validator, $this->validatorOptions);
            if(empty($basename))
            {
                $basename = $this->unique ? uniqid() . ".{$model->file->extension}" : $model->file->name;
            }

            if(!$model->validate())
            {
                $result = $model->getFirstError('file');
            }
            if(!$model->file->saveAs($this->path . key($files) . self::VARIANT_SEPARATOR . $basename))
            {
                $result = Widget::t('fileapi', 'ERROR_CAN_NOT_UPLOAD_FILE');
            }
            next($files);
        }
        return isset($result)?['error'=>$result]:['name'=>$basename];
    }
}
