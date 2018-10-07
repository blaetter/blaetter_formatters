<?php

namespace Drupal\blaetter_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
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

        $summary[] = t('Displays the issue of the article.');

        return $summary;
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
        $node = \Drupal::routeMatch()->getParameter('node');
        if ($node && !empty($node->book) && $node->id !== $node->book['bid']) {
            $book = \Drupal::entityTypeManager()->getStorage('node')->load($node->book['bid']);
            if ($book && $book->hasField('field_ausgabe') && $book->hasField('field_jahr')) {
                // get the edition
                $edition = $this->editionToMonth($book->get('field_ausgabe')->entity->getName());
                $year = $book->get('field_jahr')->entity->getName();

                return [
                    '#theme' => 'blaetter_formatters_issue',
                    '#edition' => $edition,
                    '#year' => $year,
                    '#book_id' => $book->id(),
                ];
            }
        }
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
