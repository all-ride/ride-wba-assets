<?php

namespace ride\web\cms\form;


use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\web\cms\asset\AssetEntry;
use ride\library\media\MediaFactory;
use ride\library\http\client\Client;
use ride\library\validation\factory\ValidationFactory;
use ride\library\validation\constraint\ConditionalConstraint;
use ride\library\system\file\FileSystem;
use ride\library\image\ImageFactory;

class AssetComponent extends AbstractComponent
{

    /**
     * @var String
     * The path media thumbnails are saved to.
     */
    protected $thumbnailFolder;

    /**
     * @var String
     * The path asset files are saved to.
     */
    protected $assetFolder;

    /**
     * @var \ride\library\media\MediaFactory
     */
    protected $mediaFactory;

    /**
     * @var \ride\library\image\ImageFactory
     */
    protected $imageFactory;

    /**
     * @var \ride\library\validation\factory\ValidationFactory
     */
    protected $validationFactory;

    /**
     * @var \ride\library\http\client\Client;
     */
    protected $client;

    /**
     * @var \ride\library\system\file\FileSystem
     */
    protected $fileSystem;

    /**
     * Constructs a new AssetComponent
     * @param MediaFactory $mediaFactory
     */
    public function __construct(MediaFactory $mediaFactory, Client $client, ValidationFactory $validationFactory,
                                FileSystem $fileSystem, ImageFactory $imageFactory, $thumbnailFolder, $assetFolder)
    {
        $this->mediaFactory = $mediaFactory;
        $this->imageFactory = $imageFactory;
        $this->client = $client;
        $this->validationFactory = $validationFactory;
        $this->fileSystem = $fileSystem;
        $this->thumbnailFolder = $thumbnailFolder;
        $this->assetFolder = $assetFolder;
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType()
    {
        return 'ride\web\cms\asset\AssetEntry';
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        $this->data = $data;
        $data = array(
            'thumbnail' => $data->thumbnail,
            'name' => $data->name,
            'description' => $data->description,
            'isUrl' => $data->isUrl,
            'file' => ($data->isUrl == 0) ? $data->value : '',
            'url' => ($data->isUrl == 1) ? $data->value : '',
        );

        return $data;
    }

    /**
     * Parse the form values to data of the component
     * If no thumbnail is provided and this is a media asset, find and save the thumbnail.
     * @param array $data
     * @return mixed $data
     */
    public function parseGetData(array $data)
    {
        $asset = $this->data;
        $asset->setDescription($data['description']);
        $asset->setName($data['name']);
        $asset->SetThumbnail($data['thumbnail']);
        $asset->setIsUrl(FALSE);
        if ($data['isUrl'] == 1) {
            $media = $this->mediaFactory->createMediaItem($data['url']);
            $asset->value = $data['url'];
            $asset->setIsUrl(TRUE);
            if (!$asset->getId()) {
                $asset->setName($media->getTitle());
                $asset->setDescription($media->getDescription());
            }
            if ($data['thumbnail'] == NULL) {
                $client = $this->client;
                $response = $client->get($media->getThumbnailUrl());
                if ($response->getStatusCode() == 200) {
                    $img = $this->thumbnailFolder . '/' . $media->getId() . '_thumb.png';
                    file_put_contents($img, $response->getBody());
                    $asset->setThumbnail($img);
                }
            }
            $asset->setSource($media->getType());
            switch ($asset->source) {
                case 'youtube':
                case 'vimeo':
                    $asset->type = 'video';

                    break;
                case 'soundcloud':
                    $asset->type =' audio';

                    break;
            }
        } else {
            $file = $this->fileSystem->getFile($data['file']);
            if (empty($data['name'])) {
                $fileName = $file->getName();
                $fileName = explode('.', $fileName);
                array_pop($fileName);
                $fileName = implode('_', $fileName);
                $asset->setName($fileName);
            }
            if ($data['thumbnail'] == NULL) {
                $thumb = $this->fileSystem->getFile($this->thumbnailFolder . '/' . $asset->name . '_thumb.png');
                $image = $this->imageFactory->createImage();
                $image->read($file);

                $dimension = $image->getDimension();
                $dimension->setWidth(150);
                $dimension->setHeight(150);
                $image = $image->resize($dimension);
                $image->write($thumb);
                $asset->setThumbnail($thumb->getPath());
            }

            $asset->value = $data['file'];
            switch ($file->getExtension()) {
                case 'mp3':
                    $asset->type = 'audio';

                    break;
                case 'gif':
                case 'jpg':
                case 'png':
                    $asset->type = 'image';

                    break;
                default:
                    $asset->type = 'unknown';

                    break;
            }
        }


        return $asset;
    }

    function prepareForm(FormBuilder $builder, array $options)
    {
        k($options);
        $translator = $options['translator'];
        $fileBrowser = $options['fileBrowser'];

        $builder->addRow('isUrl', 'option', array(
            'label' => $translator->translate('label.asset.is.url'),
            'options' => array(
                0 => 'file',
                1 => 'web',
            ),
            'default' => 0,
        ));
        $builder->addRow('file', 'file', array(
            'label' => $translator->translate('label.file'),
            'path' => $this->assetFolder,
            'attributes' => array(
                'class' => 'file-field',
            ),
        ));
        $builder->addRow('url', 'string', array(
            'label' => $translator->translate('label.url'),
            'attributes' => array(
                'class' => 'url-field',
            ),
        ));

        $builder->addRow('thumbnail', 'image', array(
            'label' => $translator->translate('label.thumbnail'),
            'path' => $this->thumbnailFolder,
        ));
        $builder->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
            'filters' => array(
                'trim' => array(),
            )
        ));

        $builder->addRow('description', 'wysiwyg', array(
            'label' => $translator->translate('label.description'),
        ));

        $requiredValidator = $this->validationFactory->createValidator('required', array());

        $webIsSelected = new ConditionalConstraint();
        $webIsSelected->addValueCondition('isUrl', 1);
        $webIsSelected->addValidator($requiredValidator, 'url');
        $builder->addValidationConstraint($webIsSelected);

        $fileIsSelected = new ConditionalConstraint();
        $fileIsSelected->addValueCondition('isUrl', 0);
        $fileIsSelected->addValidator($requiredValidator, 'file');
        $builder->addValidationConstraint($fileIsSelected);
    }
}
