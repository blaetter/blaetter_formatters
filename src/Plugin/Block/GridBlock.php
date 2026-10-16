<?php

namespace Drupal\blaetter_formatters\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\views\Views;

/**
 * Provides a 'GridBlock' block.
 *
 * @Block(
 *  id = "grid_block",
 *  admin_label = @Translation("Grid block"),
 * )
 */
class GridBlock extends BlockBase
{
    use LoggerChannelTrait;

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'grid_option'     => '1:2',
            'left_title'      => '',
            'left_block_ids'  => '',
            'right_title'     => '',
            'right_block_ids' => '',
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state)
    {
        $form['grid_option'] = [
            '#type' => 'select',
            '#title' => $this->t('Grid'),
            '#description' => $this->t('Select the grid system for this block'),
            '#options' => [
                '1:1' => $this->t('1:1'),
                '1:2' => $this->t('1:2'),
                '1:3' => $this->t('1:3'),
                '3:1' => $this->t('3:1'),
                '2:1' => $this->t('2:1')],
            '#default_value' => $this->configuration['grid_option'] ?? '',
            '#size' => 1,
            '#weight' => '20',
        ];
        $form['left_side'] = [
            '#type' => 'details',
            '#open' => true,
            '#title' => $this->t('Settings for the left side'),
            '#weight' => '30',
        ];
        $form['left_side']['left_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
            '#description' => $this->t('The optional title for the left side'),
            '#default_value' => $this->configuration['left_title'] ?? '',
            '#maxlength' => 64,
            '#size' => 64,
            '#weight' => '0',
        ];
        $form['left_side']['left_block_ids'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Block IDs'),
            '#description' => $this->t(
                'Add blocks via block|BLOCK_ID, content blocks via content|CONTENT_BLOCK_ID or view names and ' .
                'display IDs separated by a colon (e.g. my_view:block_1) to display on the right side, one per line.'
            ),
            '#default_value' => $this->configuration['left_block_ids'] ?? '',
            '#weight' => '0',
        ];
        $form['right_side'] = [
            '#type' => 'details',
            '#open' => true,
            '#title' => $this->t('Settings for the right side'),
            '#weight' => '40',
        ];
        $form['right_side']['right_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
            '#description' => $this->t('Optional title for the right side'),
            '#default_value' => $this->configuration['right_title'] ?? '',
            '#maxlength' => 64,
            '#size' => 64,
            '#weight' => '0',
        ];
        $form['right_side']['right_block_ids'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Block IDs'),
            '#description' => $this->t(
                'Add blocks via block|BLOCK_ID, content blocks via content|CONTENT_BLOCK_ID or view names and ' .
                'display IDs separated by a colon (e.g. my_view:block_1) to display on the right side, one per line.'
            ),
            '#default_value' => $this->configuration['right_block_ids'] ?? '',
            '#weight' => '0',
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
    */
    public function blockSubmit($form, FormStateInterface $form_state)
    {
        $this->configuration['grid_option'] = $form_state->getValue('grid_option');
        $this->configuration['left_title'] = $form_state->getValue(['left_side', 'left_title']);
        $this->configuration['left_block_ids'] = $form_state->getValue(['left_side', 'left_block_ids']);
        $this->configuration['right_title'] = $form_state->getValue(['right_side', 'right_title']);
        $this->configuration['right_block_ids'] = $form_state->getValue(['right_side', 'right_block_ids']);
    }

    /**
     * {@inheritdoc}
    */
    public function build()
    {
        // set default variables;
        $title = '';
        if ('visible' == $this->configuration['label_display']) {
            $title = $this->configuration['label'];
        }

        $grid_class_left = $this->calculateLeftGridClass($this->configuration['grid_option']);
        $grid_class_right = $this->calculateRightGridClass($this->configuration['grid_option']);
        $title_left = $this->configuration['left_title'];
        $content_left = $this->getRenderElements($this->configuration['left_block_ids']);
        $title_right = $this->configuration['right_title'];
        $content_right = $this->getRenderElements($this->configuration['right_block_ids']);

        $build = [
            '#theme' => 'blaetter_grid_block',
            '#title' => $title,
            '#grid_class_left' => $grid_class_left,
            '#grid_class_right' => $grid_class_right,
            '#title_left' => $title_left,
            '#content_left' => $content_left,
            '#title_right' => $title_right,
            '#content_right' => $content_right,
        ];
        // $build['label_display']['#markup'] = '<p>' . $this->configuration['label_display'] . '</p>';
        // $build['grid_block_grid_option']['#markup'] = '<p>' . $this->configuration['grid_option'] . '</p>';
        // $build['grid_block_left_block_ids']['#markup'] = '<p>' . $this->configuration['left_block_ids'] . '</p>';
        // $build['grid_block_left_views_ids']['#markup'] = '<p>' . $this->configuration['left_views_ids'] . '</p>';
        // $build['grid_block_right_block_ids']['#markup'] = '<p>' . $this->configuration['right_block_ids'] . '</p>';
        // $build['grid_block_right_views_ids']['#markup'] = '<p>' . $this->configuration['right_views_ids'] . '</p>';

        return $build;
    }

    /**
     * Calculates the css grid class for the left side, depending on the provided grid
     *
     * @param string $grid The given grid from the block configuration
     * @return string $class The calculated css class
     */
    public function calculateLeftGridClass($grid)
    {
        switch ($grid) {
            case '1:1':
                $class = 'one-half';
                break;
            case '1:2':
                $class = 'one-third';
                break;
            case '1:3':
                $class = 'one-fourth';
                break;
            case '3:1':
                $class = 'three-fourths';
                break;
            case '2:1':
                $class = 'two-thirds';
                break;
            default:
                $class = 'one-half';
                break;
        }

        return $class;
    }

    /**
     * Calculates the css grid class for the right side, depending on the provided grid
     *
     * @param string $grid The given grid from the block configuration
     * @return string $class The calculated css class
     */
    public function calculateRightGridClass($grid)
    {
        switch ($grid) {
            case '1:1':
                $class = 'one-half';
                break;
            case '1:2':
                $class = 'two-thirds';
                break;
            case '1:3':
                $class = 'three-fourths';
                break;
            case '3:1':
                $class = 'one-fourth';
                break;
            case '2:1':
                $class = 'one-third';
                break;
            default:
                $class = 'one-half';
                break;
        }
        return $class;
    }

    /**
     * This method extracts the given ids and gets the render arrays of the
     * referenced blocks or views.
     *
     * One entry per line: block|BLOCK_ID, content|CONTENT_BLOCK_ID or VIEW_ID:DISPLAY_ID. Entries that do not exist
     * (anymore) are skipped and logged, so a deleted block or view does not break the whole page.
     *
     * @param string $ids The given block ids or views id specified in the block configuration
     * @return array $build The Drupal render array with all elements that needs to be rendered
     */
    public function getRenderElements($ids)
    {
        $build = [];
        $cacheability = new CacheableMetadata();

        foreach (preg_split('/\R/', (string) $ids) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            if (0 === strpos($line, 'block|')) {
                $element = $this->renderEntity('block', substr($line, strlen('block|')));
            } elseif (0 === strpos($line, 'content|')) {
                $element = $this->renderEntity('block_content', substr($line, strlen('content|')));
            } elseif (false !== strpos($line, ':')) {
                $element = $this->renderView($line, $cacheability);
            } else {
                $element = null;
                $this->getLogger('blaetter_formatters')->warning(
                    'Grid block: unknown entry "@entry"',
                    ['@entry' => $line]
                );
            }

            if (null !== $element) {
                $build[] = $element;
            }
        }

        $cacheability->applyTo($build);
        return $build;
    }

    /**
     * Returns the render array of a block or content block.
     *
     * Blocks are rendered independent of their status, as they may be placed disabled to be used in grid blocks only.
     *
     * @param string $entity_type_id The entity type, block or block_content
     * @param string $id The id of the block
     * @return array|null The render array or null, if the block does not exist
     */
    protected function renderEntity($entity_type_id, $id)
    {
        try {
            $entity_type_manager = \Drupal::entityTypeManager();
            $entity = $entity_type_manager->getStorage($entity_type_id)->load(trim($id));
            if (null === $entity) {
                $this->getLogger('blaetter_formatters')->warning(
                    'Grid block: @type "@id" does not exist',
                    ['@type' => $entity_type_id, '@id' => $id]
                );
                return null;
            }
            return $entity_type_manager->getViewBuilder($entity_type_id)->view($entity);
        } catch (\Throwable $exception) {
            $this->getLogger('blaetter_formatters')->error(
                'Grid block: error while rendering @type "@id": @message',
                ['@type' => $entity_type_id, '@id' => $id, '@message' => $exception->getMessage()]
            );
            return null;
        }
    }

    /**
     * Returns the title and the content of a view display, if the current user may access it.
     *
     * @param string $line The view and display id, separated by a colon
     * @param CacheableMetadata $cacheability Collects the cacheability of the access check
     * @return array|null The render array or null, if the view does not exist or may not be accessed
     */
    protected function renderView($line, CacheableMetadata $cacheability)
    {
        [$view_id, $display_id] = array_map('trim', explode(':', $line, 2));
        $view = Views::getView($view_id);
        if (null === $view || !$view->setDisplay($display_id)) {
            $this->getLogger('blaetter_formatters')->warning(
                'Grid block: view display "@view:@display" does not exist',
                ['@view' => $view_id, '@display' => $display_id]
            );
            return null;
        }

        // The access of views depends on the permissions of the user.
        $cacheability->addCacheContexts(['user.permissions']);
        if (!$view->access($display_id)) {
            return null;
        }

        // we put the content of #title into a seperate #markup to let
        // drupal escape unsafe html, but allow things like links to
        // be added in the corresponding views and blocks.
        return [
            'title' => [
                '#theme' => 'blaetter_grid_block_title',
                '#title' => [
                    '#markup' => $view->getTitle(),
                ],
            ],
            'content' => $view->render(),
        ];
    }
}
