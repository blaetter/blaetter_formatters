<?php

namespace Drupal\blaetter_formatters\Plugin\Media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
* External reference entity media source.
*
* @see \Drupal\file\FileInterface
*
* @MediaSource(
*   id = "generic_iframe",
*   label = @Translation("Generic iFrame"),
*   description = @Translation("Uses iFrames to display external content."),
*   allowed_field_types = {"string_long"},
* )
*/
class GenericIframe extends MediaSourceBase {

  public function getMetadataAttributes()
  {
      return [
          'title' => $this->t('Title'),
          'id' => $this->t('ID'),
          'uri' => $this->t('URL'),
      ];
  }

  public function getMetadata(MediaInterface $media, $attribute_name) {
    $default_image = null;
    if ('Detektor.fm' == $media->get('field_provider')->value) {
        $default_image = '/modules/contrib/blaetter_formatters/images/detektorfm.svg';
    } elseif ('Google Maps' == $media->get('field_provider')->value) {
        $default_image = '/modules/contrib/blaetter_formatters/images/map.svg';
    }

    switch ($attribute_name) {
      // This is used to set the name of the media entity if the user leaves the field blank.
      case 'thumbnail_uri':
        return $default_image != '' ? $default_image : parent::getMetadata($media, $attribute_name);
      default:
        return parent::getMetadata($media, $attribute_name);
    }

  }
}
