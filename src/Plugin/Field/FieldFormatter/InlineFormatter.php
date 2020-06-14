<?php

namespace Drupal\blaetter_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'blaetter_formatters_inline' formatter.
 *
 * @FieldFormatter(
 *   id = "blaetter_formatters_inline",
 *   label = @Translation("Blaetter inline formatter"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class InlineFormatter extends EntityReferenceFormatterBase
{
    /**
    * {@inheritdoc}
    */
    public function settingsSummary()
    {
        $summary = [];
        $settings = $this->getSettings();

        $summary[] = $this->t('Displays the referenced entites as string, separated by a configurable separator.');

        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
            // Declare a settings and default values
            'prefix'    => '',
            'separator' => ', ',
            'use_links' => true,
        ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $elements = [];
        $elements['separator'] = [
            '#title'          => $this->t('Separator'),
            '#description'    => $this->t('Choose the separator which separates the inline elements.'),
            '#type'           => 'textfield',
            '#maxlength'      => 2,
            '#size'           => 4,
            '#default_value'  => $this->getSetting('separator'),
        ];
        $elements['prefix'] = [
            '#title'          => $this->t('Prefix'),
            '#description'    => $this->t(
                'Choose an optional prefix that is displayed right before the inline elements.'
            ),
            '#type'           => 'textfield',
            '#maxlength'      => 64,
            '#size'           => 64,
            '#default_value'  => $this->getSetting('prefix'),
        ];
        $elements['use_links'] = [
            '#title'          => $this->t('Use links'),
            '#description'    => $this->t('Decide, if the referenced entities should be linked or not.'),
            '#type'           => 'select',
            '#options'        => [
                '1' => $this->t('Use links'),
                '0' => $this->t('Do not use links'),
            ],
            '#default_value'  => $this->getSetting('use_links'),
        ];

        return $elements;
    }


    /**
    * {@inheritdoc}
    */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $entities = [];
        $output_as_link = $this->getSetting('use_links');

        foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
            $label = $entity->label();
            // If the link is to be displayed and the entity has a uri, display a
            // link.
            if ('1' == $output_as_link && !$entity->isNew()) {
                try {
                    $uri = $entity->toUrl();
                } catch (UndefinedLinkTemplateException $e) {
                    // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
                    // and it means that the entity type doesn't have a link template nor
                    // a valid "uri_callback", so don't bother trying to output a link for
                    // the rest of the referenced entities.
                    $output_as_link = false;
                }
            }

            if ($output_as_link && isset($uri) && !$entity->isNew()) {
                $entities[$delta] = [
                    '#type' => 'link',
                    '#title' => $label,
                    '#url' => $uri,
                    '#options' => $uri->getOptions(),
                ];
            } else {
                $entities[$delta] = ['#plain_text' => $label];
            }
            $entities[$delta]['#cache']['tags'] = $entity->getCacheTags();
        }
        if (empty($entities)) {
            return [];
        }
        $element[] = [
            '#theme' => 'blaetter_formatters_inline',
            '#entities'   => $entities,
            '#prefix'     => $this->getSetting('prefix'),
            '#separator'  => $this->getSetting('separator'),
        ];

        return $element;
    }
}
