<?php

namespace ride\web\cms\form;


use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\web\cms\asset\AssetEntry;
use ride\library\media\MediaFactory;
use ride\library\http\client\Client;

class AssetComponent extends AbstractComponent {

    /**
     * @var String
     * The path assets are saved to.
     */
    protected $thumbnailFolder;

    /**
     * @var \ride\web\cms\asset\AssetEntry
     * The asset
     */
    protected $asset;

    /**
     * @var \ride\library\media\MediaFactory
     */
    protected $mediaFactory;

    /**
     * @var \ride\library\http\client\Client;
     */
    protected $client;

    /**
     * Constructs a new AssetComponent
     * @param MediaFactory $mediaFactory
     */
    public function __construct(MediaFactory $mediaFactory, Client $client, $thumbnailFolder) {
        $this->mediaFactory = $mediaFactory;
        $this->client = $client;
        $this->path = $thumbnailFolder;
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
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
        );

        return $data;
    }

    /**
     * Parse the form values to data of the component
     * If no thumbnail is provided and this is a media asset, find and save the thumbnail.
     * @param array $data
     * @return mixed $data
     */
    public function parseGetData(array $data) {
        $asset = $this->data;
        $asset->setDescription($data['description']);
        $asset->setName($data['name']);
        $asset->SetThumbnail($data['thumbnail']);
        $asset->setIsUrl(FALSE);
        if ($data['isUrl']) {
            $media = $this->mediaFactory->createMediaItem($data['url']);
            $asset->setIsUrl(TRUE);
            if ($data['thumbnail'] == NULL) {
                $client = $this->client;
                $response = $client->get($media->getThumbnailUrl());
                if ($response->getStatusCode() == 200) {
                    $img = $this->thumbnailFolder . '/' . $media->getId() . '_thumb.png';
                    file_put_contents($img, $response->getBody());
                    $asset->setThumbnail($img);
                }
                k($asset->getId());
            }
        }
        return $asset;
    }

    function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];
        $fileBrowser = $options['fileBrowser'];

        $builder->addRow('isUrl', 'option', array(
            'label' => $translator->translate('label.asset.is.url'),
            'options' => array(
                0 => 'file',
                1 => 'web',
            ),
            'default' => 'file',
        ));
        $builder->addRow('file', 'file', array(
            'label' => $translator->translate('label.file'),
            'path' => $this->path,
        ));
        $builder->addRow('url', 'string', array(
            'label' => $translator->translate('label.url'),
        ));

        $builder->addRow('thumbnail', 'image', array(
            'label' => $translator->translate('label.thumbnail'),
            'path' => $this->path,
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
    }
}
