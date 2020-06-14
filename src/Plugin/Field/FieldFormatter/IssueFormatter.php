<?php

namespace Drupal\blaetter_formatters\Plugin\Field\FieldFormatter;

use \Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'blaetter_formatters_issue' formatter.
 *
 * @FieldFormatter(
 *   id = "blaetter_formatters_issue",
 *   label = @Translation("Blaetter issue formatter"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class IssueFormatter extends FormatterBase
{
    /**
     * {@inheritdoc}
     */
    public function settingsSummary()
    {
        $summary = [];
        $settings = $this->getSettings();

        $summary[] = $this->t('Displays the issue of the article.');

        return $summary;
    }
    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
            // Declare a settings and default values
            'prefix'        => '',
            'display_pages' => false,
            'extra_styles'  => '',
        ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $elements = [];
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
        $elements['display_pages'] = [
            '#title'          => $this->t('display pages'),
            '#description'    => $this->t('Decide, if the pages should also be displayed behind the issue.'),
            '#type'           => 'select',
            '#options'        => [
                '1' => $this->t('display pages'),
                '0' => $this->t('do not display pages'),
            ],
            '#default_value'  => $this->getSetting('display_pages'),
        ];
        $elements['extra_styles'] = [
          '#title'            => $this->t('extra styles'),
          '#description'      => $this->t('Choose extra styling options for this field.'),
          '#type'             => 'select',
          '#options'          => [
              '' => $this->t('none'),
              'info--big' => $this->t('Big font size'),
          ],
          '#default_value'    => $this->getSetting('extra_styles'),
        ];

        return $elements;
    }

    /**
     * Implementation of viewElements
     *
     * This method is a hacky way to use an empty field (or at least ignore the field content)
     * to display information about the edition the current article is linked with.
     *
     * So this only works, if we have a main book (bid) and its node id is different from the current one.
     * In this case we do check, if we have the fields for ausgabe (edition) and jahr (year) to display theese
     * information along with a link to the edition itself.
     *
     * @param FieldItemListInterface $items    The items that are usually coming along with this field.
     * @param string                 $langcode The langcode given with the field data
     *
     * @return array Render array with the data to display.
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $node = $items->getEntity();
        if ($node instanceof FieldableEntityInterface
            && !empty($node->book)
            && $node->id() !== $node->book['bid']
          ) {
            /**
             * @var FieldableEntityInterface $book
             */
            $book = \Drupal::entityTypeManager()->getStorage('node')->load($node->book['bid']);
            if ($book instanceof FieldableEntityInterface
              && $book->hasField('field_ausgabe')
              && $book->hasField('field_jahr')
              && $book->get('field_ausgabe')->entity instanceof FieldableEntityInterface
              && $book->get('field_jahr')->entity instanceof FieldableEntityInterface
            ) {
                // get the edition
                $edition = $this->editionToMonth($book->get('field_ausgabe')->entity->get('name')->value);
                $year = $book->get('field_jahr')->entity->get('name')->value;

                $page_from = $page_to = null;

                if (true == $this->getSetting('display_pages')
                  && $node->hasField('field_seite_von')
                  && $node->hasField('field_seite_bis')
                ) {
                    $page_from = $node->get('field_seite_von')->value;
                    $page_to = $node->get('field_seite_bis')->value;
                }

                return [
                    '#theme'          => 'blaetter_formatters_issue',
                    '#edition'        => $edition,
                    '#year'           => $year,
                    '#book_id'        => $book->id(),
                    '#issue_prefix'   => $this->getSetting('prefix') ?: $this->t('Edition'),
                    '#display_pages'  => $this->getSetting('display_pages'),
                    '#page_from'      => $page_from,
                    '#page_to'        => $page_to,
                    '#extra_styles'   => $this->getSetting('extra_styles')
                ];
            }
        }
        return [];
    }

    /**
     * This method takes an edition string (aka german month name) and returns the appropriate numeric month
     *
     * @param string $edition The edition to convert.
     *
     * @return string $months[$edition] if set, $edition otherwise
     */
    public function editionToMonth(string $edition)
    {
        $months = [
            'januar'    => '1',
            'februar'   => '2',
            'märz'      => '3',
            'april'     => '4',
            'mai'       => '5',
            'juni'      => '6',
            'juli'      => '7',
            'august'    => '8',
            'september' => '9',
            'oktober'   => '10',
            'november'  => '11',
            'dezember'  => '12',
        ];
        if (array_key_exists(mb_strtolower($edition), $months)) {
            return $months[mb_strtolower($edition)];
        }
        return $edition;
    }
}
