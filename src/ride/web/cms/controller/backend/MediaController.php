<?php

namespace ride\web\cms\controller\backend;

use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\orm\OrmManager;
use ride\library\system\file\browser\FileBrowser;
use ride\library\validation\exception\ValidationException;

use ride\web\base\controller\AbstractController;

class MediaController extends AbstractController {

    public function indexAction(I18n $i18n, OrmManager $orm, $locale = null, $album = null) {
        if (!$locale) {
            $url = $this->getUrl('media.overview.locale', array('locale' => $this->getLocale()));

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

        $mediaAlbumModel = $orm->getMediaAlbumModel();
        $mediaModel = $orm->getMediaModel();

        $data = array(
            'album' => $album,
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('album', 'select', array(
            'label' => $translator->translate('label.album.current'),
            'options' => array('' => '/') + $mediaAlbumModel->getDataList(),
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            $data = $form->getData();

            $url = $this->getUrl('media.album.overview', array('locale' => $locale, 'album' => $data['album']));

            $this->response->setRedirect($url);

            return;
        }

        if ($album) {
            $album = $mediaAlbumModel->getAlbum($album, 2, $locale);
            if (!$album) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            $album->media = $mediaModel->getMediaForAlbum($album->id, $locale);
        } else {
            $album = $mediaAlbumModel->createEntry();
            $album->id = 0;
            $album->children = $mediaAlbumModel->getAlbums(null, null, 1, $locale);
            $album->media = $mediaModel->getMediaForAlbum(null, $locale);
        }

        foreach ($album->children as $child) {
            $child->media = $mediaModel->getMediaForAlbum($child->id, $locale);
        }

        $this->setTemplateView('cms/backend/media.overview', array(
            'form' => $form->getView(),
            'album' => $album,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));
    }

    public function sortAction(OrmManager $orm, $locale, $album = null) {
        $mediaAlbumModel = $orm->getMediaAlbumModel();
        $mediaModel = $orm->getMediaModel();

        if ($album) {
            $album = $mediaAlbumModel->getAlbum($album, 2, $locale);
            if (!$album) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            $album->media = $mediaModel->getMediaForAlbum($album->id, $locale);
        } else {
            $album = $mediaAlbumModel->createEntry();
            $album->children = $mediaAlbumModel->getAlbums(null, null, 1, $locale);
            $album->media = $mediaModel->getMediaForAlbum(null, $locale);
        }

        $index = 1;
        $albums = $this->request->getQueryParameter('album');
        if ($albums) {
            foreach ($albums as $albumId) {
                if (isset($album->children[$albumId])) {
                    $album->children[$albumId]->orderIndex = $index;

                    $mediaAlbumModel->save($album->children[$albumId]);
                }

                $index++;
            }
        }

        $index = 1;
        $items = $this->request->getQueryParameter('item');
        if ($items) {
            foreach ($items as $itemId) {
                if (isset($album->media[$itemId])) {
                    $album->media[$itemId]->orderIndex = $index;

                    $mediaModel->save($album->media[$itemId]);
                }

                $index++;
            }
        }
    }

    public function albumAction(OrmManager $orm, $locale, $album = null) {
        $mediaAlbumModel = $orm->getMediaAlbumModel();

        if ($album) {
            $album = $mediaAlbumModel->getById($album);
            if (!$album) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $album = $mediaAlbumModel->createEntry();
            $album->parent = $this->request->getQueryParameter('album');
        }

        $translator = $this->getTranslator();

        $data = array(
            'name' => $album->name,
            'parent' => $album->getParentAlbumId(),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('parent', 'select', array(
            'label' => $translator->translate('label.parent'),
            'options' => array('' => '/') + $mediaAlbumModel->getDataList(array('locale' => $locale)),
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
                $album = $album->getParentAlbumId();
                if (!$album) {
                    $album = '';
                }

                $url = $this->getUrl('media.overview.album', array('locale' => $locale, 'album' => $album));

                $this->response->setRedirect($url);

                return;
            }

            try {
                $form->validate();

                $data = $form->getData();

                $album->name = $data['name'];
                $album->description = $data['description'];
                $album->parent = $data['parent'];

                if (!$album->parent) {
                    $album->parent = null;
                }

                $mediaAlbumModel->save($album);

                $url = $this->getUrl('media.album.overview', array('locale' => $locale, 'album' => $album->id));

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('cms/backend/media.album', array(
            'form' => $form->getView(),
            'album' => $album,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

    public function albumDeleteAction(OrmManager $orm, $locale, $album) {
        $mediaAlbumModel = $orm->getMediaAlbumModel();

        $album = $mediaAlbumModel->getById($album);
        if (!$album) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($this->request->isPost()) {
            $mediaAlbumModel->delete($album);

            $album = $album->getParentAlbumId();
            if (!$album) {
                $album = '';
            }

            $url = $this->getUrl('media.album.overview', array('locale' => $locale, 'album' => $album));

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/media.delete', array(
            'name' => $album->name,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

    public function itemAction(OrmManager $orm, FileBrowser $fileBrowser, $locale, $item = null) {
        $mediaAlbumModel = $orm->getMediaAlbumModel();
        $mediaModel = $orm->getMediaModel();

        if ($item) {
            $media = $mediaModel->getById($item);
            if (!$media) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            if ($media->album) {
                $media->album = $media->album->id;
            }
        } else {
            $media = $mediaModel->createEntry();
            $media->album = $mediaAlbumModel->createProxy($this->request->getQueryParameter('album'), $locale);
        }

        $translator = $this->getTranslator();

        $data = array(
            'album' => $media->album,
            'name' => $media->name,
            'description' => $media->description,
            'file' => $media->value,
            'thumbnail' => $media->thumbnail,
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('album', 'object', array(
            'label' => $translator->translate('label.album'),
            'options' => $mediaAlbumModel->find(array('locale' => $locale)),
            'value' => 'id',
            'property' => 'name',
            'validators' => array(
                'required' => array(),
            )
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
        $form->addRow('file', 'file', array(
            'label' => $translator->translate('label.file'),
            'path' => $fileBrowser->getApplicationDirectory()->getChild('data/upload/media'),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('thumbnail', 'image', array(
            'label' => $translator->translate('label.thumbnail'),
            'path' => $fileBrowser->getPublicDirectory()->getChild('media'),
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                $url = $this->getUrl('media.overview.album', array('locale' => $locale, 'album' => $media->album->id));

                $this->response->setRedirect($url);

                return;
            }

            try {
                $form->validate();

                $data = $form->getData();

                $media->dataLocale = $locale;
                $media->album = $data['album'];
                $media->name = $data['name'];
                $media->description = $data['description'];
                $media->value = $data['file'];
                $media->thumbnail = $data['thumbnail'];

                $file = $fileBrowser->getFile($media->value);
                if (!$file) {
                    $file = $fileBrowser->getPublicFile($media->value);
                }

                if (!$media->name) {
                    $media->name = $file->getName();
                }

                switch ($file->getExtension()) {
                    case 'mp3':
                        $media->type = 'audio';

                        break;
                    case 'gif':
                    case 'jpg':
                    case 'png':
                        $media->type = 'image';

                        break;
                    default:
                        $media->type = 'unknown';

                        break;
                }

                $mediaModel->save($media);

                $url = $this->getUrl('media.album.overview', array('locale' => $locale, 'album' => $media->album->id));

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('cms/backend/media.item', array(
            'form' => $form->getView(),
            'media' => $media,
            'locale' => $locale,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

    public function itemDeleteAction(OrmManager $orm, $locale, $item) {
        $mediaModel = $orm->getMediaModel();

        $item = $mediaModel->getById($item);
        if (!$item) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($this->request->isPost()) {
            $mediaModel->delete($item);

            $url = $this->getUrl('media.album.overview', array('locale' => $locale, 'album' => $item->album->id));

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/media.delete', array(
            'name' => $item->name,
            'referer' => $this->request->getQueryParameter('referer'),
        ));
    }

}
