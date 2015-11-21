<?php

namespace ride\web\base\controller;

use ride\library\html\Pagination;
use ride\library\i18n\I18n;
use ride\library\media\exception\UnsupportedMediaException;
use ride\library\orm\OrmManager;
use ride\library\system\file\browser\FileBrowser;
use ride\library\validation\exception\ValidationException;

use ride\service\AssetService;

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
        // force a locale
        if (!$locale) {
            $url = $this->getUrl('assets.overview.locale', array('locale' => $this->getContentLocale()));

            $this->response->setRedirect($url);

            return;
        }

        // check locale
        try {
            $locale = $i18n->getLocale($locale)->getCode();
        } catch (LocaleNotFoundException $exception) {
            $this->response->setNotFound();

            return;
        }

        $this->setContentLocale($locale);

        $folderModel = $orm->getAssetFolderModel();
        $assetModel = $orm->getAssetModel();

        // process arguments
        $embed = $this->request->getQueryParameter('embed', false);
        $selected = $this->request->getQueryParameter('selected');

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
        $isFiltered = $filter['type'] != 'all' || $filter['date'] != 'all' || $filter['query'];

        // create the form
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
        $form->addRow('action', 'hidden', array());
        $form->addRow('order', 'hidden', array());
        $form->addRow('limit', 'hidden', array());
        $form->addRow('assets', 'hidden', array(
            'multiple' => true,
        ));
        $form->addRow('folders', 'hidden', array(
            'multiple' => true,
        ));
        $form->addRow('submit', 'hidden', array());
        $form = $form->build();

        // handle form
        if ($form->isSubmitted()) {
            $url = $this->request->getUrl();

            $data = $form->getData();
            switch ($data['submit']) {
                case 'limit':
                    $limit = $data['limit'];
                case 'filter':
                    if ($folder) {
                        $url = $this->getUrl('assets.folder.overview', array(
                            'locale' => $locale,
                            'folder' => $folder,
                        ));
                    } else {
                        $url = $this->getUrl('assets.overview.locale', array('locale' => $locale));
                    }

                    $url .= '?view=' . $view . '&type=' . urlencode($data['type']) . '&date=' . urlencode($data['date']) . '&embed=' . ($embed ? 1 : 0) . '&limit=' . $limit . '&page=1';
                    if ($selected) {
                        $url .= '&selected=' . $selected;
                    }
                    if ($data['query']) {
                        $url .= '&query=' . urlencode($data['query']);
                    }

                    break;
                case 'bulk-action':
                    if (!$this->processBulkAction($orm, $locale, $folder, $data)) {
                        return;
                    }

                    break;
                case 'order':
                    if (!$this->processSort($orm, $locale, $folder, $data)) {
                        return;
                    }

                    break;
            }

            $this->response->setRedirect($url);

            return;
        }

        // fetch folder
        $folder = $folderModel->getFolder($folder, $locale, true);
        if (!$folder) {
            $this->response->setNotFound();

            return;
        }

        $numFolders = $folderModel->countFolders($folder, $locale, true, $filter);
        $numAssets = $assetModel->countByFolder($folder, $locale, true, $filter);
        $numItems = $numFolders + $numAssets;

        $folders = $folderModel->getFolders($folder, $locale, true, $filter, $limit, $page);
        if (count($folders) < $limit) {
            $assetLimit = $limit;
            $assetPage = $page;
            $offset = 0;

            if ($folders) {
                $assetLimit -= count($folders);
                $assetPage = 1;
            } else {
                if ($numFolders) {
                    $assetPage -= ceil($numFolders / $limit);
                }

                if ($page != 1 && $numFolders) {
                    $offset = $limit - ($numFolders % $limit);
                }
            }

            $assets = $assetModel->getByFolder($folder, $locale, true, $filter, (integer) $assetLimit, (integer) $assetPage, $offset);
        } else {
            $assets = array();
        }

        $urlSuffix = '?view=' . $view . '&type=' . $filter['type'] . '&date=' . $filter['date'] . '&embed=' . ($embed ? 1 : 0) . '&limit=' . $limit . '&page=%page%';
        if ($filter['query']) {
            $urlSuffix .= '&query=' . urlencode($filter['query']);
        }
        if ($selected) {
            $urlSuffix .= '&selected=' . $selected;
        }

        $urlPagination = $this->getUrl('assets.folder.overview', array(
            'locale' => $locale,
            'folder' => $folder->getId(),
        )) . $urlSuffix;

        $pages = ceil($numItems / $limit);
        $pagination = new Pagination($pages, $page);
        $pagination->setHref($urlPagination);

        $urlSuffix = str_replace('%page%', $page, $urlSuffix);

        // assign everything to view
        $view = $this->setTemplateView('assets/overview', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'folders' => $folders,
            'assets' => $assets,
            'numFolders' => $numFolders,
            'numAssets' => $numAssets,
            'numItems' => $numItems,
            'limit' => $limit,
            'pagination' => $pagination,
            'page' => $page,
            'pages' => $pages,
            'breadcrumbs' => $folderModel->getBreadcrumbs($folder),
            'view' => $view,
            'filter' => $filter,
            'isFiltered' => $isFiltered,
            'embed' => $embed,
            'selected' => $selected,
            'urlSuffix' => $urlSuffix,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));
    }

    /**
     * Action to process bulk actions on the items of a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id or slug of the folder
     * @return null
     */
    protected function processBulkAction(OrmManager $orm, $locale, $folder = null, array $data) {
        $assetModel = $orm->getAssetModel();
        $folderModel = $orm->getAssetFolderModel();

        $folder = $folderModel->getFolder($folder);
        if (!$folder) {
            $this->response->setNotFound();

            return false;
        }

        if ($data['action'] == 'delete') {
            $children = $data['folders'];
            if ($children) {
                foreach ($children as $childId) {
                    $child = $folderModel->getById($childId, $locale);
                    if (!$child) {
                        continue;
                    }

                    $folderModel->delete($child);

                    $this->addSuccess('success.data.deleted', array('data' => $child->getName()));
                }
            }

            $assets = $data['assets'];
            if ($assets) {
                foreach ($assets as $assetId) {
                    $asset = $assetModel->getById($assetId, $locale);
                    if (!$asset) {
                        continue;
                    }

                    $assetModel->delete($asset);

                    $this->addSuccess('success.data.deleted', array('data' => $asset->getName()));
                }
            }
        }

        return true;
    }

    /**
     * Sorts the items of a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id or slug of the folder
     * @return null
     */
    protected function processSort(OrmManager $orm, $locale, $folder = null, array $data) {
        $folderModel = $orm->getAssetFolderModel();

        $folder = $folderModel->getFolder($folder, $locale);
        if (!$folder) {
            $this->response->setNotFound();

            return false;
        }

        $assetModel = $orm->getAssetModel();

        $folderModel->orderFolder($folder, $data['order']);
        $assetModel->orderFolder($folder, $data['order']);

        return true;
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
                $this->response->setNotFound();

                return;
            }
        } else {
            $folder = $folderModel->createEntry();

            $parent = $this->request->getQueryParameter('folder');
            if ($parent) {
                $parent = $folderModel->getById($parent);
                if ($parent) {
                    $folder->setParent($parent->getPath());
                }
            }
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
     * Action to manually order the subfolders of a folder
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
                $this->response->setNotFound();

                return;
            }
        }

        $folders = array();

        $order = $this->request->getBodyParameter('order');
        foreach ($order as $folder) {
            $folders[] = $folderModel->createProxy($folder);
        }

        $folderModel->order($folders);
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
            $this->response->setNotFound();

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
    public function assetValueAction(OrmManager $orm, FileBrowser $fileBrowser, AssetService $assetService, $asset) {
        $assetModel = $orm->getAssetModel();

        if (is_numeric($asset)) {
            $asset = $assetModel->getById($asset);
        } else {
            $asset = $assetModel->getBy(array('filter' => array('slug' => $asset)));
        }

        if (!$asset) {
            $this->response->setNotFound();

            return;
        }

        if (!$asset->isUrl()) {
            $url = $assetService->getAssetUrl($asset, $this->request->getQueryParameter('style'));
        } else {
            $url = $asset->getValue();
        }

        $this->response->setRedirect($url);
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
        $styleModel = $orm->getImageStyleModel();

        // prepare or lookup asset
        if ($asset) {
            $asset = $assetModel->getById($asset, $locale, true);
            if (!$asset) {
                $this->response->setNotFound();

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

        $media = null;
        $referer = $this->getAssetReferer($asset, $locale);
        $embed = $this->request->getQueryParameter('embed', false);

        if ($asset->isUrl()) {
            try {
                $media = $assetModel->getMediaFactory()->createMediaItem($asset->value);
            } catch (UnsupportedMediaException $exception) {

            }
        }


        $data = array(
            'asset' => $asset,
        );

        $styles = $styleModel->find();
        foreach ($styles as $style) {
            $data['style-' . $style->getSlug()] = $asset->getStyleImage($style->getSlug());
        }

        // create form
        $form = $this->createFormBuilder($data);
        $form->addRow('asset', 'component', array(
            'component' => $assetComponent,
            'embed' => true,
        ));
        foreach ($styles as $style) {
            $form->addRow('style-' . $style->getSlug(), 'image', array(
                'path' => $assetComponent->getDirectory(),
            ));
        }
        $form = $form->build();

        // process form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $asset = $data['asset'];
                $asset->setLocale($locale);

                $assetStyleModel = $orm->getAssetImageStyleModel();

                foreach ($styles as $style) {
                    $image = $data['style-' . $style->getSlug()];
                    $assetStyle = $asset->getStyle($style->getSlug());

                    if ($image) {
                        if (!$assetStyle) {
                            // style addition
                            $assetStyle = $assetStyleModel->createEntry();
                            $assetStyle->setStyle($style);

                            $asset->addToStyles($assetStyle);
                        }

                        $assetStyle->setImage($image);
                    } elseif ($assetStyle) {
                        // style removal
                        $asset->removeFromStyles($assetStyle);
                    }
                }

                $assetModel->save($asset);

                if ($this->request->isXmlHttpRequest()) {
                    // ajax request
                    $this->setTemplateView('assets/detail', array(
                        'item' => $asset,
                        'embed' => $embed,
                        'referer' => $referer,
                        'locale' => $locale,
                    ));
                } else {
                    // regular client
                    $this->response->setRedirect($referer);
                }

                return;
            } catch (ValidationException $exception) {
                k($exception->getErrorsAsString());
                $this->setValidationException($exception, $form);
            }
        }

        $view = $this->setTemplateView('assets/asset', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'styles' => $styles,
            'asset' => $asset,
            'embed' => $embed,
            'referer' => $referer,
            'media' => $media,
            'dimension' => $assetModel->getDimension($asset),
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));

        $form->processView($view);
    }

    /**
     * Action to manually order the assets of a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param string $folder Id or slug of the folder
     * @return null
     */
    public function assetSortAction(OrmManager $orm, $locale, $folder = null) {
        $folderModel = $orm->getAssetFolderModel();

        // resolve folder
        if ($folder) {

            $folder = $folderModel->getFolder($folder, $locale);
            if (!$folder) {
                $this->response->setNotFound();

                return;
            }
        } else {
            $folder = $folderModel->createEntry();
        }

        // gather assets to order
        $assetModel = $orm->getAssetModel();
        $assets = array();

        $order = $this->request->getBodyParameter('order');
        foreach ($order as $asset) {
            $assets[] = $assetModel->createProxy($asset);
        }

        // perform order
        $assetModel->order($assets);
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
            $this->response->setNotFound();

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
