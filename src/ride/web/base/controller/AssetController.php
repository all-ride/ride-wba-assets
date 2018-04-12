<?php

namespace ride\web\base\controller;

use ride\application\orm\asset\entry\AssetFolderEntry;
use ride\application\orm\asset\model\AssetFolderModel;

use ride\library\html\Pagination;
use ride\library\i18n\I18n;
use ride\library\media\exception\UnsupportedMediaException;
use ride\library\orm\OrmManager;
use ride\library\system\file\browser\FileBrowser;
use ride\library\validation\exception\ValidationException;
use ride\library\StringHelper;

use ride\service\AssetService;
use ride\service\OrmService;

use ride\web\base\controller\AbstractController;
use ride\web\base\form\AssetComponent;
use ride\web\base\view\BaseTemplateView;
use ride\web\orm\form\ScaffoldComponent;
use ride\web\WebApplication;

/**
 * Controller to manage the assets
 */
class AssetController extends AbstractController {

    /**
     * Permission to limit a user to his own folder
     * @var string
     */
    const PERMISSION_CHROOT = 'assets.chroot';

    /**
     * Name of the users folder, parent for chrooted folders
     * @var string
     */
    const FOLDER_USERS = 'Users';

    /**
     * Flag to see if embed modus is enabled
     * @var boolean
     */
    private $embed;

    /**
     * Chrooted folder for users with limited access
     * @var \ride\application\orm\asset\entry\AssetFolderEntry
     */
    private $chroot;

    /**
     * Hook before every action
     * @return boolean Flag to see if the action should be invoked
     */
    public function preAction() {
        $this->embed = (boolean) $this->request->getQueryParameter('embed', false);

        return true;
    }

    /**
     * Hook after every action
     * @return null
     */
    public function postAction() {
        $view = $this->response->getView();
        if (!$view instanceof BaseTemplateView) {
            return;
        }

        if ($this->embed) {
            $view->removeTaskbar();
        }
    }

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

        // fetch folder
        $folder = $folderModel->getFolder($folder, $locale, true);
        if ($folder) {
            $folder = $this->applyChroot($folderModel, $folder, $locale);
        }

        if (!$folder) {
            $this->response->setNotFound();

            return;
        }

        // process arguments
        $selected = $this->request->getQueryParameter('selected');

        $views = array('grid', 'list');
        $view = $this->request->getQueryParameter('view', $this->getConfig()->get('assets.view', 'grid'));
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
        /* @deprecated, breaks submit function in js */
        $form->addRow('submit', 'hidden', array());
        /* alternative */
        $form->addRow('_submit', 'hidden', array());
        $form = $form->build();

        // handle form
        if ($form->isSubmitted()) {
            $url = $this->request->getUrl();

            $data = $form->getData();

            if (!$data['submit'] && $data['_submit']) {
                $data['submit'] = $data['_submit'];
            }

            switch ($data['submit']) {
                case 'limit':
                    $limit = $data['limit'];
                case 'filter':
                    if ($folder->getId()) {
                        $url = $this->getUrl('assets.folder.overview', array(
                            'locale' => $locale,
                            'folder' => $folder->getId(),
                        ));
                    } else {
                        $url = $this->getUrl('assets.overview.locale', array('locale' => $locale));
                    }

                    $url .= '?view=' . $view . '&type=' . urlencode($data['type']) . '&date=' . urlencode($data['date']) . '&embed=' . ($this->embed ? 1 : 0) . '&limit=' . $limit . '&page=1';
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

        $urlSuffix = '?view=' . $view . '&type=' . $filter['type'] . '&date=' . $filter['date'] . '&embed=' . ($this->embed ? 1 : 0) . '&limit=' . $limit . '&page=%page%';
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
        $this->setTemplateView('assets/overview', array(
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
            'breadcrumbs' => $folderModel->getBreadcrumbs($folder, $this->resolveChroot($folderModel, $locale)),
            'view' => $view,
            'filter' => $filter,
            'isFiltered' => $isFiltered,
            'embed' => $this->embed,
            'selected' => $selected,
            'urlSuffix' => $urlSuffix,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
            'maxFileSize' => (int) ini_get('post_max_size'),
        ));
    }

    /**
     * Action to process bulk actions on the items of a folder
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @param \ride\application\orm\asset\entry\AssetFolderEntry $folder
     * @return boolean
     */
    protected function processBulkAction(OrmManager $orm, $locale, AssetFolderEntry $folder, array $data) {
        if ($data['action'] == 'move') {
            $url = $this->getUrl('assets.move', array('locale' => $locale), array('folders' => $data['folders'], 'assets' => $data['assets'], 'referer' => $this->request->getUrl()));

            $this->response->setRedirect($url);

            return false;
        }

        $assetModel = $orm->getAssetModel();
        $folderModel = $orm->getAssetFolderModel();

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
     * @param \ride\application\orm\asset\entry\AssetFolderEntry $folder
     * @return null
     */
    protected function processSort(OrmManager $orm, $locale, AssetFolderEntry $folder, array $data) {
        $folderModel = $orm->getAssetFolderModel();
        $folderModel->orderFolder($folder, $data['order']);

        $assetModel = $orm->getAssetModel();
        $assetModel->orderFolder($folder, $data['order']);

        return true;
    }

    /**
     * Action to move assets and folders
     * @param \ride\library\i18n\I18n $i18n Instance of I18n
     * @param \ride\librayr\orm\OrmManager $orm Instance of the ORM
     * @param string $locale Code of the locale
     * @return null
     */
    public function moveAction(I18n $i18n, OrmManager $orm, $locale) {
        $assetModel = $orm->getAssetModel();
        $folderModel = $orm->getAssetFolderModel();

        // resolve all folders to move
        $folders = $this->request->getQueryParameter('folders', array());
        foreach ($folders as $index => $folder) {
            if ($folder == 0) {
                unset($folders[$index]);
            }

            $folders[$index] = $folderModel->getById($folder, $locale, true);
            $folders[$index] = $this->applyChroot($folderModel, $folders[$index], $locale);

            if (!$folders[$index]) {
                $this->addError('error.data.found', array('data' => 'AssetFolder #' . $folder));

                unset($folders[$index]);
            }
        }

        // resolve all assets to move
        $assets = $this->request->getQueryParameter('assets', array());
        foreach ($assets as $index => $asset) {
            $assets[$index] = $assetModel->getById($asset, $locale, true);
            if ($assets[$index]) {
                $assetFolder = $assets[$index]->getFolder();
                if (!$assetFolder) {
                    $assetFolder = $folderModel->getFolder(null, $locale);
                }

                if (!$this->applyChroot($folderModel, $assetFolder, $locale) || ($this->chroot->getId() !== 0 && $assetFolder->getId() == 0)) {
                    $assets[$index] = null;
                }
            }

            if (!$assets[$index]) {
                $this->addError('error.data.found', array('data' => 'Asset #' . $asset));

                unset($assets[$index]);
            }
        }

        // where to go from here?
        $referer = $this->request->getQueryParameter('referer');

        if (!$folders && !$assets) {
            // nothing to do here
            if (!$referer) {
                $referer = $this->getUrl('assets.overview.locale', array(
                    'locale' => $locale,
                ));
            }


            $this->response->setRedirect($referer);

            return;
        }

        // create the form
        $translator = $this->getTranslator();
        $options = array('0' => '---') + $folderModel->getOptionList($locale, true, $this->chroot);

        $form = $this->createFormBuilder();
        $form->addRow('destination', 'option', array(
            'label' => $translator->translate('label.destination'),
            'description' => $translator->translate('label.destination.assets.description'),
            'options' => $options,
            'widget' => 'select',
        ));
        $form = $form->build();

        // process the form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                if ($data['destination']) {
                    $destination = $folderModel->getById($data['destination'], $locale, true);
                } elseif ($this->chroot->getId() == 0) {
                    $destination = null;
                } else {
                    $destination = $this->chroot;
                }

                if ($folders) {
                    $folderModel->move($folders, $destination);
                }
                if ($assets) {
                    $assetModel->move($assets, $destination);
                }

                $this->addSuccess('success.assets.moved');

                if (!$referer) {
                    $referer = $this->getUrl('assets.folder.overview', array(
                        'locale' => $locale,
                        'folder' => $destination->getId(),
                    ));
                }

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        // set the view
        $this->setTemplateView('assets/move', array(
            'form' => $form->getView(),
            'folders' => $folders,
            'assets' => $assets,
            'embed' => $this->embed,
            'referer' => $referer,
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
    public function folderFormAction(WebApplication $web, I18n $i18n, OrmService $ormService, $locale, $folder = null) {
        $folderModel = $ormService->getModel('AssetFolder');

        // get the folder to add or edit
        if ($folder) {
            $folder = $folderModel->getFolder($folder, $locale, true);
            if ($folder) {
                $folder = $this->applyChroot($folderModel, $folder, $locale);
            }

            if (!$folder) {
                $this->response->setNotFound();

                return;
            }

            $breadcrumbsFolder = $folder;
        } else {
            $folder = $folderModel->createEntry();

            $parent = $this->request->getQueryParameter('folder');
            if ($parent) {
                $parent = $folderModel->getFolder($parent, $locale, true);
                if ($parent) {
                    $parent = $this->applyChroot($folderModel, $parent, $locale);
                }

                if (!$parent) {
                    $this->response->setNotFound();

                    return;
                }
            } else {
                $parent = $folderModel->getFolder(null, $locale);
                $parent = $this->applyChroot($folderModel, $parent, $locale);
            }

            $folder->setParent($parent->getPath());

            $breadcrumbsFolder = $parent;
        }

        $referer = $this->getFolderReferer($folder, $locale);

        // create the form
        $translator = $this->getTranslator();
        $data = array('folder' => $folder);

        $folderComponent = new ScaffoldComponent($web, $this->getSecurityManager(), $ormService, $folderModel);
        $folderComponent->setLocale($locale);
        $folderComponent->setLog($this->getLog());
        $folderComponent->omitField('parent');
        $folderComponent->omitField('assets');
        $folderComponent->omitField('orderIndex');

        $form = $this->createFormBuilder($data);
        $form->setId('form-asset-folder');
        $form->addRow('folder', 'component', array(
            'component' => $folderComponent,
            'embed' => true,
        ));
        $form = $form->build();

        // process the form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $folder = $data['folder'];
                $folder->setLocale($locale);

                $folderModel->save($folder);

                $this->addSuccess('success.data.saved', array('data' => $folder->getName()));

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        // set the view
        $view = $this->setTemplateView('assets/folder', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'breadcrumbs' => $folderModel->getBreadcrumbs($breadcrumbsFolder, $this->chroot),
            'embed' => $this->embed,
            'referer' => $referer,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));

        $form->processView($view);
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
            $folder = $folderModel->getFolder($folder, $locale, true);
            if ($folder) {
                $folder = $this->applyChroot($folderModel, $folder, $locale);
            }

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

        $folder = $folderModel->getFolder($folder, $locale, true);
        if ($folder) {
            $folder = $this->applyChroot($folderModel, $folder, $locale);
        }

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

        $this->setTemplateView('assets/delete', array(
            'name' => $folder->getName(),
            'embed' => $this->embed,
            'referer' => $referer,
        ));
    }

    /**
     * Action to get the original value of an asset
     * @param \ride\library\orm\OrmManager $orm
     * @param FileBrowser $fileBrowser
     * @param AssetService $assetService
     * @param string $asset
     * @return null
     */
    public function assetValueAction(OrmManager $orm, FileBrowser $fileBrowser, AssetService $assetService, $asset) {
        $assetModel = $orm->getAssetModel();

        if (is_numeric($asset)) {
            $assetEntry = $assetModel->getById($asset);
        } else {
            $locales = $orm->getLocales();

            foreach ($locales as $locale) {
                $assetEntry = $assetModel->getBy(['filter' => ['slug' => $asset]], $locale);

                if (!empty($assetEntry)) {
                    break;
                }
            }
        }

        if (empty($assetEntry)) {
            $this->response->setNotFound();

            return;
        }

        $url = $assetService->getAssetUrl($assetEntry, $this->request->getQueryParameter('style'));
        if ($url) {
            $this->response->setRedirect($url);

            return;
        }

        $file = $fileBrowser->getFile($assetEntry->getValue());
        if (!$file) {
            $this->response->setNotFound();

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
        $styleModel = $orm->getImageStyleModel();

        // prepare or lookup asset
        if ($asset) {
            $asset = $assetModel->getById($asset, $locale, true);
            if (!$asset) {
                $this->response->setNotFound();

                return;
            }

            $folder = $asset->getFolder();
            if (!$folder) {
                $folder = $folderModel->getFolder(null, $locale, true);
            }

            // secure assets in chrooted folders
            $chroot = $this->applyChroot($folderModel, $folder, $locale);
            if (!$chroot || ($chroot->getId() !== 0 && $folder->getId() == 0)) {
                $this->response->setNotFound();

                return;
            }
        } else {
            $asset = $assetModel->createEntry();

            $folder = $this->request->getQueryParameter('folder');
            $folder = $folderModel->getFolder($folder, $locale, true);
            if ($folder) {
                $folder = $this->applyChroot($folderModel, $folder, $locale);
            }

            if (!$folder) {
                $this->response->setNotFound();

                return;
            }

            if ($folder->getId() != 0) {
                $asset->setFolder($folder);
            }
        }

        $media = null;
        $referer = $this->getAssetReferer($asset, $locale);
        $view = $this->request->getQueryParameter('view', 'grid');

        if ($asset->isUrl()) {
            try {
                $media = $assetModel->getMediaFactory()->createMediaItem($asset->value);
            } catch (UnsupportedMediaException $exception) {

            }
        }

        $data = array(
            'asset' => $asset,
        );

        $styles = $styleModel->find(null, null, true);
        foreach ($styles as $style) {
            $data['style-' . $style->getSlug()] = $asset->getStyleImage($style->getSlug());
        }

        // create form
        $form = $this->createFormBuilder($data);
        $form->addRow('asset', 'component', array(
            'component' => $assetComponent,
            'embed' => true,
        ));

        if ($asset->getId()) {
            foreach ($styles as $styleId => $style) {
                if (!$style->isExposed()) {
                    unset($styles[$styleId]);

                    continue;
                }

                $imageStyleImage = $asset->getStyle($style->getSlug());

                $form->addRow('style-' . $style->getSlug(), 'image', array(
                    'path' => $assetComponent->getDirectory(),
                    'attributes' => array(
                        'data-id' => $imageStyleImage ? $imageStyleImage->getId() : null,
                    ),
                ));
            }
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
                        'embed' => $this->embed,
                        'referer' => $referer,
                        'locale' => $locale,
                        'view' => $view,
                    ));
                } else {
                    // regular client
                    $this->addSuccess('success.data.saved', array('data' => $asset->getName()));

                    $this->response->setRedirect($referer);
                }

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $view = $this->setTemplateView('assets/asset', array(
            'form' => $form->getView(),
            'folder' => $folder,
            'styles' => $styles,
            'asset' => $asset,
            'embed' => $this->embed,
            'referer' => $referer,
            'breadcrumbs' => $folder ? $folderModel->getBreadcrumbs($folder, $this->chroot) : array(),
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

        // secure assets in chrooted folders
        $chrootFolder = $this->applyChroot($folderModel, $folder, $locale);
        if (!$chrootFolder) {
            $this->response->setNotFound();

            return;
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

        // lookup asset
        $asset = $assetModel->getById($asset, $locale);
        if (!$asset) {
            $this->response->setNotFound();

            return;
        }

        // lookup asset folder
        $folderModel = $orm->getAssetFolderModel();

        $folder = $asset->getFolder();
        if ($folder === null) {
            $folder = $folderModel->getFolder(0, $locale);
        }

        // secure assets in chrooted folders
        $chrootFolder = $this->applyChroot($folderModel, $folder, $locale);
        if (!$chrootFolder || $folder->getId() != $chrootFolder->getId()) {
            $this->response->setNotFound();

            return;
        }

        $referer = $this->getAssetReferer($asset, $locale);

        if ($this->request->isPost()) {
            // perform delete
            $assetModel->delete($asset);

            $this->addSuccess('success.data.deleted', array('data' => $asset->getName()));

            $this->response->setRedirect($referer);

            return;
        }

        // show confirmation
        $this->setTemplateView('assets/delete', array(
            'name' => $asset->getName(),
            'embed' => $this->embed,
            'referer' => $referer,
        ));
    }

    /**
     * Applies the user's chroot for the provided folder
     * @param \ride\application\orm\asset\model\AssetFolderModel $model
     * @param \ride\application\orm\asset\entry\AssetFolderEntry $folder
     * @param string $locale
     * @return \ride\application\orm\asset\entry\AssetFolderEntry|boolean The
     * folder if allowed for the user, false if the user is outside his chroot
     */
    protected function applyChroot(AssetFolderModel $model, AssetFolderEntry $folder, $locale) {
        $this->resolveChroot($model, $locale);

        if ($folder->getId() == '0') {
            // root folder should be chrooted folder
            return $this->chroot;
        } elseif ($this->chroot->getId() != 0 && $folder->getId() != $this->chroot->getId() && !$folder->hasParentFolder($this->chroot)) {
            // not inside chrooted folder
            return false;
        }

        // we're good
        return $folder;
    }

    /**
     * Resolves the chroot folder of the current user
     * @param \ride\application\orm\asset\model\AssetFolderModel $model
     * @param string $locale
     * @return \ride\application\orm\asset\entry\AssetFolderEntry
     */
    private function resolveChroot(AssetFolderModel $model, $locale) {
        if ($this->chroot) {
            return $this->chroot;
        }

        $securityManager = $this->getSecurityManager();
        $user = $securityManager->getUser();

        if (!$user || $user->isSuperUser() || !$securityManager->isPermissionGranted(self::PERMISSION_CHROOT)) {
            // no restriction, root folder
            $this->chroot = $model->getFolder(null, $locale, true);
        } else {
            $username = str_replace('.', '', StringHelper::safeString($user->getUserName()));
            $this->chroot = $model->getFolder($username, $locale, true);
            if (!$this->chroot) {
                // fetch users folder
                $usersFolder = $model->getFolder(self::FOLDER_USERS, $locale, true);
                if (!$usersFolder) {
                    // create users folder
                    $usersFolder = $model->createEntry();
                    $usersFolder->setName(self::FOLDER_USERS);
                    $usersFolder->setLocale($locale);

                    $model->save($usersFolder);
                }

                // create user's folder
                $this->chroot = $model->createEntry();
                $this->chroot->setName($user->getUserName());
                $this->chroot->setParent($usersFolder->getPath());

                $model->save($this->chroot);
            }
        }

        return $this->chroot;
    }

    /**
     * Gets the referer of a folder
     * @param \ride\application\orm\asset\entry\AssetFolderEntry $folder
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
     * Gets the referer of an asset
     * @param \ride\application\orm\asset\entry\AssetEntry $asset
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
