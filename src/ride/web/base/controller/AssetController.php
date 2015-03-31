<?php

namespace ride\web\base\controller;

use ride\library\html\Pagination;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\image\ImageFactory;
use ride\library\media\MediaFactory;
use ride\library\orm\OrmManager;
use ride\library\system\file\browser\FileBrowser;
use ride\library\validation\exception\ValidationException;

use ride\web\base\controller\AbstractController;
use ride\web\base\form\AssetComponent;

/**
 * Controller to manage the assets
 */
class AssetController extends AbstractController {

    /**
     * Action to show an overview of a folder
     * @param \ride\library\i18n\I18n $i18n Instance of I18n
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the content locale
     * @param string $folder Id of the folder to show, null for root folder
     * @return null
     */
    public function indexAction(I18n $i18n, OrmManager $orm, $locale = null, $folder = null) {
        if (!$locale) {
            $url = $this->getUrl('assets.overview.locale', array('locale' => $this->getLocale()));

            $this->response->setRedirect($url);

            return;
        }

        try {
            $locale = $i18n->getLocale($locale)->getCode();
        } catch (LocaleNotFoundException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $folderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();

        // process arguments
        $embed = $this->request->getQueryParameter('embed', false);

        $views = array('grid', 'list');
        $view = $this->request->getQueryParameter('view', 'grid');
        if (!in_array($view, $views)) {
            $view = 'grid';
        }

        $limit = $this->request->getQueryParameter('limit', 24);
        $limit = $this->request->getBodyParameter('limit', $limit);
        if (!is_numeric($limit) || $limit < 1) {
            $limit = 24;
        }
        $limit = (integer) $limit;

        $page = $this->request->getQueryParameter('page', 1);
        if (!is_numeric($page) || $page < 1) {
            $page = 1;
        }
        $page = (integer) $page;

        $filter = array(
            'type' => $this->request->getQueryParameter('type', 'all'),
            'date' => $this->request->getQueryParameter('date', 'all'),
            'query' => $this->request->getQueryParameter('query'),
        );
        $flatten = $this->request->getQueryParameter('flatten', 0);

        // create the filter form
        $translator = $this->getTranslator();

        $types = $assetModel->getTypes($translator);
        $types = array(
            'all' => $translator->translate('label.types.all'),
            'folder' => $translator->translate('label.folder'),
        ) + $types;

        $months = $assetModel->getMonths();
        $months = array(
            'all' => $translator->translate('label.dates.all'),
            'today' => $translator->translate('label.today'),
        ) + $months;

        $form = $this->createFormBuilder($filter);
        $form->setId('form-filter');
        $form->addRow('type', 'select', array(
            'options' => $types,
        ));
        $form->addRow('date', 'select', array(
            'options' => $months,
        ));
        $form->addRow('query', 'string', array(
            'attributes' => array(
                'placeholder' => $translator->translate('label.search'),
            ),
        ));
        $form = $form->build();

        // handle filter form
        if ($form->isSubmitted()) {
            $data = $form->getData();

            if ($folder) {
                $url = $this->getUrl('assets.folder.overview', array(
                    'locale' => $locale,
                    'folder' => $folder,
                ));
            } else {
                $url = $this->getUrl('assets.overview.locale', array('locale' => $locale));
            }

            $url .= '?view=' . $view . '&type=' . urlencode($data['type']) . '&date=' . urlencode($data['date']) . '&embed=' . ($embed ? 1 : 0) . '&flatten=' . $flatten . '&limit=' . $limit . '&page=' . $page;
            if ($data['query']) {
                $url .= '&query=' . urlencode($data['query']);
            }

            $this->response->setRedirect($url);

            return;
        }

        // fetch folder
        $folder = $folderModel->getFolder($folder, $locale, true);
        if (!$folder) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $items = $folderModel->getItems($folder, $locale, true, $filter, $flatten, $limit, $page);
        $numItems = $folderModel->countItems($folder, $locale, true, $filter, $flatten);

        $urlSuffix = '?view=' . $view . '&type=' . $filter['type'] . '&date=' . $filter['date'] . '&embed=' . ($embed ? 1 : 0);

        $urlPagination = $this->getUrl('assets.folder.overview', array(
            'locale' => $locale,
            'folder' => $folder->getId(),
        )) . $urlSuffix . '&flatten=' . ($flatten ? 1 : 0) . '&limit=' . $limit . '&page=%page%';
        if ($filter['query']) {
            $urlPagination .= '&query=' . urlencode($filter['query']);
        }

        $pages = ceil($numItems / $limit);
        $pagination = new Pagination($pages, $page);
        $pagination->setHref($urlPagination);

        // assign everything to view
        $view = $this->setTemplateView('assets/overview', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'items' => $items,
            'numItems' => $numItems,
            'limit' => $limit,
            'pagination' => $pagination,
            'page' => $page,
            'pages' => $pages,
            'breadcrumbs' => $folderModel->getBreadcrumbs($folder),
            'view' => $view,
            'filter' => $filter,
            'flatten' => $flatten,
            'embed' => $embed,
            'urlSuffix' => $urlSuffix,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));
    }

    /**
     * Action to add or edit a new folder
     * @param \ride\library\i18n\I18n $i18n Instance of I18n
     * @param \ride\librayr\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id of slug of the folder to edit
     * @return null
     */
    public function folderFormAction(I18n $i18n, OrmManager $orm, $locale, $folder = null) {
        $folderModel = $orm->getAssetFolderModel();

        // get the folder to add or edit
        if ($folder) {
            $folder = $folderModel->getFolder($folder, $locale, true);
            if (!$folder) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $folder = $folderModel->createEntry();
            $folder->setParent($this->request->getQueryParameter('folder', ''));
        }

        $referer = $this->getFolderReferer($folder, $locale);

        // create the form
        $translator = $this->getTranslator();

        $form = $this->createFormBuilder($folder);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
        ));
        $form->addRow('description', 'wysiwyg', array(
            'label' => $translator->translate('label.description'),
        ));
        $form = $form->build();

        // process the form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $folder = $form->getData();
                $folder->setLocale($locale);

                $folderModel->save($folder);

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $embed = $this->request->getQueryParameter('embed', false);

        // set the view
        $this->setTemplateView('assets/folder', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'embed' => $embed,
            'referer' => $referer,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));
    }

    /**
     * Action to order the items of a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id or slug of the folder
     * @return null
     */
    public function folderSortAction(OrmManager $orm, $locale, $folder = null) {
        $folderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();

        if ($folder) {
            $folder = $folderModel->getFolder($folder, $locale);
            if (!$folder) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $folder = $folderModel->createEntry();
        }

        $index = $this->request->getBodyParameter('index', 1);
        $order = $this->request->getBodyParameter('order');

        foreach ($order as $item) {
            if ($item['type'] == 'folder') {
                $folder = $folderModel->createProxy($item['id']);
                $folder->setOrderIndex($index);

                $folderModel->save($folder);
            } else {
                $asset = $assetModel->createProxy($item['id']);
                $asset->setOrderIndex($index);

                $assetModel->save($asset);
            }

            $index++;
        }
    }

    /**
     * Action to process bulk actions on the items of a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id or slug of the folder
     * @return null
     */
    public function folderBulkAction(OrmManager $orm, $locale, $folder) {
        $assetModel = $orm->getAssetModel();
        $folderModel = $orm->getAssetFolderModel();

        $folder = $folderModel->getFolder($folder);
        if (!$folder) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $action = $this->request->getBodyParameter('action');
        if ($action == 'delete') {
            $children = $this->request->getBodyParameter('folders');
            if ($children) {
                foreach ($children as $childId) {
                    $child = $folderModel->getById($childId, $locale);
                    if (!$child) {
                        continue;
                    }

                    $folderModel->delete($child);
                }
            }

            $assets = $this->request->getBodyParameter('assets');
            if ($assets) {
                foreach ($assets as $assetId) {
                    $asset = $assetModel->getById($assetId, $locale);
                    if (!$asset) {
                        continue;
                    }

                    $assetModel->delete($asset);
                }
            }
        }

        $referer = $this->getFolderReferer($folder, $locale);

        $this->response->setRedirect($referer);
    }

    /**
     * Action to delete a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id or slug of the folder
     * @return null
     */
    public function folderDeleteAction(OrmManager $orm, $locale, $folder) {
        $folderModel = $orm->getAssetFolderModel();

        $folder = $folderModel->getFolder($folder);
        if (!$folder) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $referer = $this->getFolderReferer($folder, $locale);

        if ($this->request->isPost()) {
            $folderModel->delete($folder);

            $this->addSuccess('success.data.deleted', array('data' => $folder->getName()));

            $this->response->setRedirect($referer);

            return;
        }

        $embed = $this->request->getQueryParameter('embed', false);

        $this->setTemplateView('assets/delete', array(
            'name' => $folder->getName(),
            'embed' => $embed,
            'referer' => $referer,
        ));
    }

    /**
     * Gets the referer of a folder
     * @param AssetFolderEntry $folder
     * @return string
     */
    protected function getFolderReferer($folder, $locale) {
        $referer = $this->request->getQueryParameter('referer');
        if ($referer) {
            return $referer;
        }

        $parentFolderId = $folder->getParentFolderId();
        if (!$parentFolderId) {
            $parentFolderId = 0;
        }

        return $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $parentFolderId));
    }

    /**
     * Action to get the original value of an asset
     * @param \ride\library\orm\OrmManager $orm
     * @param string $asset
     * @return null
     */
    public function assetValueAction(OrmManager $orm, FileBrowser $fileBrowser, $asset) {
        $assetModel = $orm->getAssetModel();
        $asset = $assetModel->getById($asset);
        if (!$asset) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($asset->isUrl()) {
            $this->response->setRedirect($asset->getValue());

            return;
        }

        $file = $fileBrowser->getFile($asset->getValue());
        if (!$file) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $this->setFileView($file);
    }

    /**
     * Action to add or edit a asset
     * @param \ride\library\i18n\I18n $i18n Instance of I18n
     * @param \ride\library\orm\OrmManager $orm
     * @param \ride\web\cms\form\AssetComponent
     * @param string $locale
     * @param string $asset
     * @return null
     */
    public function assetFormAction(I18n $i18n, OrmManager $orm, AssetComponent $assetComponent, $locale, $asset = null) {
        $folderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();
        $dimension = null;

        if ($asset) {
            $asset = $assetModel->getById($asset, $locale, true);
            if (!$asset) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            $folder = $asset->getFolder();
        } else {
            $asset = $assetModel->createEntry();

            $folder = $this->request->getQueryParameter('folder');
            if ($folder) {
                $folder = $folderModel->createProxy($folder, $locale);

                $asset->setFolder($folder);
            }
        }

        $media = $asset->isUrl() ? $assetModel->getMediaFactory()->createMediaItem($asset->value) : NULL;
        $referer = $this->getAssetReferer($asset, $locale);

        $form = $this->buildForm($assetComponent, $asset);
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $asset = $form->getData();
                $asset->setLocale($locale);

                $assetModel->save($asset);

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $dimension = null;
        if ($asset->isImage()) {
            $file = $assetModel->getFileBrowser()->getFile($asset->getValue());

            $image = $assetModel->getImageFactory()->createImage();
            $image->read($file);

            $dimension = $image->getDimension();
        }

        $embed = $this->request->getQueryParameter('embed', false);

        $view = $this->setTemplateView('assets/asset', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'asset' => $asset,
            'embed' => $embed,
            'referer' => $referer,
            'media' => $media,
            'dimension' => $dimension,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));

        $form->processView($view);
    }

    /**
     * Action to delete an item
     * @param \ride\library\orm\OrmManager $orm
     * @param string $locale Code of the locale
     * @param string $asset Id of an asset
     * @return null
     */
    public function assetDeleteAction(OrmManager $orm, $locale, $asset) {
        $assetModel = $orm->getAssetModel();

        $asset = $assetModel->getById($asset, $locale);
        if (!$asset) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $referer = $this->getAssetReferer($asset, $locale);

        if ($this->request->isPost()) {
            $assetModel->delete($asset);

            $this->addSuccess('success.data.deleted', array('data' => $asset->getName()));

            $this->response->setRedirect($referer);

            return;
        }

        $embed = $this->request->getQueryParameter('embed', false);

        $this->setTemplateView('assets/delete', array(
            'name' => $asset->getName(),
            'embed' => $embed,
            'referer' => $referer,
        ));
    }

    /**
     * Gets the referer of an asset
     * @param AssetEntry $asset
     * @return string
     */
    protected function getAssetReferer($asset, $locale) {
        $referer = $this->request->getQueryParameter('referer');
        if ($referer) {
            return $referer;
        }

        $folder = $asset->getFolder();
        if ($folder) {
            $folderId = $folder->getId();
        } else {
            $folderId = 0;
        }

        return $this->getUrl('assets.folder.overview', array('locale' => $locale, 'folder' => $folderId));
    }

}
