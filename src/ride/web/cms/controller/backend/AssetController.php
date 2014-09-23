<?php

namespace ride\web\cms\controller\backend;

use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\orm\OrmManager;
use ride\library\system\file\browser\FileBrowser;
use ride\library\validation\exception\ValidationException;
use ride\library\media\SimpleMediaFactory;

use ride\web\base\controller\AbstractController;

class AssetController extends AbstractController {

    public function indexAction(I18n $i18n, OrmManager $orm, $locale = null, $folder = null) {
        if (!$locale) {
            $url = $this->getUrl('assets.overview.locale', array('locale' => $this->getLocale()));

            $this->response->setRedirect($url);

            return;
        } else {
            try {
                $locale = $i18n->getLocale($locale);
                $locale = $locale->getCode();
            } catch (LocaleNotFoundException $exception) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        }

        $translator = $this->getTranslator();
        $assetFolderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();

        $data = array(
            'folder' => $folder,
        );

        $form = $this->createFormBuilder($data);

        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            $data = $form->getData();

            $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $data['folder']));

            $this->response->setRedirect($url);

            return;
        }

        if ($folder) {
            $folder = $assetFolderModel->getFolder($folder, 2, $locale);
            if (!$folder) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            $folder->assets = $assetModel->getAssetsForFolder($folder->id, $locale);
        } else {

            $folder = $assetFolderModel->createEntry();
            $folder->id = 0;
            $folder->children = $assetFolderModel->getFolders(null, null, 1, $locale);
            $folder->assets = $assetModel->getAssetsForFolder(null, $locale);
        }

        foreach ($folder->children as $child) {
            $child->assets = $assetModel->getAssetsForFolder($child->id, $locale);
        }

        $this->setTemplateView('cms/backend/assets.overview', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));
    }

    public function sortAction(OrmManager $orm, $locale, $folder = null) {
        $assetFolderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getassetModel();

        if ($folder) {
            $folder = $assetFolderModel->getFolder($folder, 2, $locale);
            if (!$folder) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            $folder->assets = $assetModel->getAssetsForFolder($folder->id, $locale);
        } else {
            $folder = $assetFolderModel->createEntry();
            $folder->children = $assetFolderModel->getFolders(null, null, 1, $locale);
            $folder->assets = $assetModel->getAssetsForFolder(null, $locale);
        }

        $index = 1;
        $folders = $this->request->getQueryParameter('folder');
        if ($folders) {
            foreach ($folders as $folderId) {
                if (isset($folder->children[$folderId])) {
                    $folder->children[$folderId]->orderIndex = $index;

                    $assetFolderModel->save($folder->children[$folderId]);
                }

                $index++;
            }
        }

        $index = 1;
        $items = $this->request->getQueryParameter('item');
        if ($items) {
            foreach ($items as $itemId) {
                if (isset($folder->assets[$itemId])) {
                    $folder->assets[$itemId]->orderIndex = $index;

                    $assetModel->save($folder->assets[$itemId]);
                }

                $index++;
            }
        }
    }

    public function folderAction(OrmManager $orm, $locale, $folder = null) {
        $assetFolderModel = $orm->getAssetFolderModel();

        if ($folder) {
            $folder = $assetFolderModel->getById($folder);
            if (!$folder) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $folder = $assetFolderModel->createEntry();
            $folder->parent = $this->request->getQueryParameter('folder');
        }

        $translator = $this->getTranslator();

        $data = array(
            'name' => $folder->name,
            'description' => $folder->description,
            'parent' => $folder->getParentfolderId(),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('parent', 'select', array(
            'label' => $translator->translate('label.parent'),
            'options' => array('' => '/') + $assetFolderModel->getDataList(array('locale' => $locale)),
        ));
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('description', 'wysiwyg', array(
            'label' => $translator->translate('label.description'),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $folder = $folder->getParentfolderId();
                if (!$folder) {
                    $folder = '';
                }

                $url = $this->getUrl('assets.overview.folder', array('locale' => $locale, 'folder' => $folder));

                $this->response->setRedirect($url);

                return;
            }

            try {
                $form->validate();

                $data = $form->getData();

                $folder->name = $data['name'];
                $folder->description = $data['description'];
                $folder->parent = $data['parent'];

                if (!$folder->parent) {
                    $folder->parent = null;
                }

                $assetFolderModel->save($folder);

                $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $folder->id));

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('cms/backend/assets.folder', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

    public function folderDeleteAction(OrmManager $orm, $locale, $folder) {
        $assetFolderModel = $orm->getAssetFolderModel();

        $folder = $assetFolderModel->getById($folder);
        if (!$folder) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($this->request->isPost()) {
            $assetFolderModel->delete($folder);

            $folder = $folder->getParentfolderId();
            if (!$folder) {
                $folder = '';
            }

            $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $folder));

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/asset.delete', array(
            'name' => $folder->name,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

    public function assetAction(OrmManager $orm, FileBrowser $fileBrowser, SimpleMediaFactory $mediaFactory, $locale, $item = null) {
        $assetFolderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getassetModel();

        if ($item) {
            $asset = $assetModel->getById($item);
            if (!$asset) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $asset = $assetModel->createEntry();
            $asset->folder = $assetFolderModel->createProxy($this->request->getQueryParameter('folder'), $locale);
        }

        $translator = $this->getTranslator();
        $data = array(
            'name' => $asset->name,
            'folder' => $asset->folder,
            'description' => $asset->description,
            'file' => $asset->value,
            'thumbnail' => $asset->thumbnail,
        );

        $form = $this->createFormBuilder($data);

        $form->addRow('assetUploadType', 'option', array(
           'label' => $translator->translate('label.asset.upload.type'),
            'options' => array(
              'file' => 'file',
              'web' => 'web',
            ),
            'default' => 'file',
        ));

        $form->addRow('file', 'file', array(
            'label' => $translator->translate('label.file'),
            'path' => $fileBrowser->getApplicationDirectory()->getChild('data/upload/asset'),
        ));

        $form->addRow('webUrl', 'string', array(
            'label' => $translator->translate('label.web'),
        ));

        $form->addRow('thumbnail', 'image', array(
            'label' => $translator->translate('label.thumbnail'),
            'path' => $fileBrowser->getPublicDirectory()->getChild('asset'),
        ));


        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
            'filters' => array(
                'trim' => array(),
            )
        ));
        $form->addRow('description', 'wysiwyg', array(
            'label' => $translator->translate('label.description'),
        ));

        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $url = $this->getUrl('assets.overview.folder', array('locale' => $locale, 'folder' => $asset->folder->id));

                $this->response->setRedirect($url);

                return;
            }

            try {
                $form->validate();

                $data = $form->getData();

                if ($data['assetUploadType'] == 'web') {
                    if (!empty($data['webUrl'])) {
                        $media = $mediaFactory->createMediaItem($data['webUrl']);
                        k($media);
                        k($media->getDescription());
                    }
                    else {
                        Throw new ValidationException('Provide a media url');
                    }
                }
                else if ($data['assetUploadType'] == 'file' && empty($data['file'])) {
                    Throw new ValidationException('Provide a file');
                }

                $asset->dataLocale = $locale;
                $asset->folder = $data['folder'];
                $asset->name = $data['name'];
                $asset->description = $data['description'];
                $asset->value = $data['file'];
                $asset->thumbnail = $data['thumbnail'];


                $file = $fileBrowser->getFile($asset->value);
                if (!$file) {
                    $file = $fileBrowser->getPublicFile($asset->value);
                }

                if (!$asset->name) {
                    $asset->name = $file->getName();
                }

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

                $assetModel->save($asset);

                $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $asset->folder->id));

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form, $exception->getMessage());
            }
        }

        $this->setTemplateView('cms/backend/asset', array(
            'form' => $form->getView(),
            'asset' => $asset,
            'locale' => $locale,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

    public function assetDeleteAction(OrmManager $orm, $locale, $item) {
        $assetModel = $orm->getassetModel();

        $item = $assetModel->getById($item);
        if (!$item) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($this->request->isPost()) {
            $assetModel->delete($item);

            $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $item->folder->id));

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/asset.delete', array(
            'name' => $item->name,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

}
