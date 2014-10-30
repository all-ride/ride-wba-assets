<?php

namespace ride\web\cms\controller\backend;

use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\orm\OrmManager;
use ride\library\validation\exception\ValidationException;
use ride\web\cms\form\AssetComponent;
use ride\web\cms\form\EntryFolderComponent;
use ride\library\media\MediaFactory;
use ride\library\image\ImageFactory;
use ride\library\system\file\FileSystem;

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

        $assetFolderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();

        $breadcrumbs = array();
        $folder = $assetFolderModel->getFolder($folder, 2, $locale);
        if (!$folder) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
            return;
        }

        if ($folder->getId() > 0) {
            $breadcrumbs = $assetFolderModel->getBreadcrumbs($folder);
        }
        foreach ($folder->children as $child) {
            $child->assets = $assetModel->getAssetsForFolder($child->id, $locale);
        }

        //create the bulk selection/edit form
        $translator = $this->getTranslator();
        $builder = $this->createFormBuilder();
        $types = $assetModel->getAssetTypes();
        array_unshift($types, 'all');

        $builder->addRow('style', 'option', array(
            'label' => '',
            'default' => 'grid',
            'options' => array(
                'grid' => 'grid',
                'list' => 'list',
            ),
        ));

        $builder->addRow('source', 'select', array(
            'label' => '',
            'default' => 'all',
            'options' => $types,
        ));

        $builder->addRow('uploaded', 'select', array(
            'label' =>  '',
            'default' => 'all',
            'options' => array(
                'all' => "Don't filter on date",
                'today' => 'Today',
                'last_week' => 'Last week',
                'last_month' => 'Last month',
                'last_year' => 'Last year',
            ),
        ));

        $bulkSelectForm = $builder->build();
        $bulkSelectForm->setId('asset-overview-bulk');

        // handle form
        if ($bulkSelectForm->isSubmitted()) {
            try{
                $bulkSelectForm->validate();
            }
            catch (ValidationException $e) {

            }
        }
        $view = $this->setTemplateView('cms/backend/assets.overview', array(
            'bulkSelectForm' => $bulkSelectForm->getView(),
            'folder' => $folder,
            'breadcrumbs' => $breadcrumbs,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));

        $view->addJavascript('js/assets-frontend.js');
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

    public function folderAction(OrmManager $orm, EntryFolderComponent $entryFolderComponent, $locale, $folder = null) {
        $assetFolderModel = $orm->getAssetFolderModel();
        if ($folder) {
            $folder = $assetFolderModel->getById($folder);
            if (!$folder) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $folder = $assetFolderModel->createEntry();
            $folderParent = $this->request->getQueryParameter('folder') ? $this->request->getQueryParameter('folder') : '';
            $folder->parent = $folderParent;
        }

        $form = $this->buildForm($entryFolderComponent, $folder);

        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $folder = $folder->getParentFolderId();
                if (!$folder) {
                    $folder = '';
                }

                $url = $this->getUrl('assets.overview.folder', array('locale' => $locale, 'folder' => $folder));

                $this->response->setRedirect($url);

                return;
            }

            try {
                $form->validate();

                $folder = $form->getData();

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

    public function assetAction(OrmManager $orm, AssetComponent $assetComponent, MediaFactory $mediaFactory, ImageFactory $imageFactory,
                                FileSystem $fileSystem, $locale, $item = null) {
        $assetFolderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();
        $dimension = NULL;
        if ($item) {
            $asset = $assetModel->getById($item);
            if (!$asset) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
                return;
            }
            if (!$asset->isUrl) {
                $path = $asset->getImage();
                $file = $fileSystem->getFile($path);
                $image = $imageFactory->createImage();
                $image->read($file);
                $dimension = $image->getDimension();
            }
        } else {
            $asset = $assetModel->createEntry();
            $folder =  $assetFolderModel->createProxy($this->request->getQueryParameter('folder'), $locale);
            $asset->folder = $folder->id > 0 ? $folder : NULL;
        }

        $media = $asset->isUrl ? $mediaFactory->createMediaItem($asset->value) : NULL;

        $form = $this->buildForm($assetComponent, $asset);
        $form->setId('asset-edit-form');

        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $url = $this->getUrl('assets.overview.folder', array('locale' => $locale, 'folder' => $asset->folder->id));

                $this->response->setRedirect($url);
                return;
            }

            try {
                $form->validate();
                $asset = $form->getData();

                $assetModel->save($asset);
                $folder_id = isset($asset->folder) ? $asset->folder->id : 0;
                $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $folder_id));

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $exception) {
                echo "<pre>" . $exception->getTraceAsString() . "</pre>";
                echo $exception->getErrorsAsString();
                $this->setValidationException($exception, $form, $exception->getMessage());
            }
        }

        $referer = $this->getUrl('assets.overview', array('locale' => $locale));
        if ($asset && $folder = $asset->getFolder()) {
            $referer = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $folder->getId()));
        }
        $view = $this->setTemplateView('cms/backend/asset', array(
            'form' => $form->getView(),
            'asset' => $asset,
            'locale' => $locale,
            'referer' => $referer,
            'media' => $media,
            'dimension' => $dimension,
        ));

        $view->addJavascript('js/cms/assets-backend.js');
    }

    public function assetDeleteAction(OrmManager $orm, $locale, $item) {
        $assetModel = $orm->getassetModel();

        $asset = $assetModel->getById($item);
        if (!$asset) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }
        if ($this->request->isPost()) {
            $assetModel->delete($asset);
            $folder_id = isset($asset->folder->id) ? $asset->folder->id : 0;
            $url = $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $folder_id));

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/asset.delete', array(
            'name' => $asset->name,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }
}
