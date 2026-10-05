<?php

namespace Drupal\Tests\blaetter_formatters\Unit\Plugin\Block;

use Drupal\blaetter_formatters\Plugin\Block\GridBlock;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewExecutableFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests, that the grid block renders its blocks and views and skips missing ones.
 *
 * @group blaetter_formatters
 */
#[CoversClass(GridBlock::class)]
#[Group('blaetter_formatters')]
class GridBlockTest extends UnitTestCase
{
    /**
     * The logged warnings.
     *
     * @var array
     */
    private $warnings = [];

    /**
     * The existing entities, keyed by entity type and id.
     *
     * @var array
     */
    private $entities = [];

    /**
     * The views, keyed by id, with their displays and access.
     *
     * @var array
     */
    private $views = [];

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
        $entity_type_manager->method('getStorage')->willReturnCallback(function ($entity_type_id) {
            $storage = $this->createMock(EntityStorageInterface::class);
            $storage->method('load')->willReturnCallback(
                fn ($id) => $this->entities[$entity_type_id][$id] ?? null
            );
            return $storage;
        });
        $entity_type_manager->method('getViewBuilder')->willReturnCallback(function ($entity_type_id) {
            $view_builder = $this->createMock(EntityViewBuilderInterface::class);
            $view_builder->method('view')->willReturnCallback(
                fn (EntityInterface $entity) => ['#markup' => $entity_type_id . ' ' . $entity->id()]
            );
            return $view_builder;
        });

        $executable_factory = $this->createMock(ViewExecutableFactory::class);
        $executable_factory->method('get')->willReturnCallback(function ($view) {
            $definition = $this->views[$view->id()];
            $executable = $this->createMock(ViewExecutable::class);
            $executable->method('setDisplay')->willReturnCallback(
                fn ($display_id) => in_array($display_id, $definition['displays'], true)
            );
            $executable->method('access')->willReturn($definition['access']);
            $executable->method('getTitle')->willReturn($view->id());
            $executable->method('render')->willReturn(['#markup' => 'view ' . $view->id()]);
            return $executable;
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message, $context = []) {
            $this->warnings[] = strtr($message, $context);
        });
        $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
        $logger_factory->method('get')->willReturn($logger);

        $container = new ContainerBuilder();
        $container->set('entity_type.manager', $entity_type_manager);
        $container->set('views.executable', $executable_factory);
        $container->set('logger.factory', $logger_factory);
        $container->set('string_translation', $this->getStringTranslationStub());
        $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
        $cache_contexts_manager->method('assertValidTokens')->willReturn(true);
        $container->set('cache_contexts_manager', $cache_contexts_manager);
        \Drupal::setContainer($container);
    }

    /**
     * Adds an entity, that can be loaded.
     */
    private function addEntity(string $entity_type_id, string $id): void
    {
        $entity = $this->createMock('view' === $entity_type_id ? ViewEntityInterface::class : EntityInterface::class);
        $entity->method('id')->willReturn($id);
        $this->entities[$entity_type_id][$id] = $entity;
    }

    /**
     * Adds a view with the given displays.
     */
    private function addView(string $id, array $displays, bool $access = true): void
    {
        $this->addEntity('view', $id);
        $this->views[$id] = ['displays' => $displays, 'access' => $access];
    }

    private function createBlock(): GridBlock
    {
        return new GridBlock([], 'grid_block', ['provider' => 'blaetter_formatters', 'admin_label' => 'Grid block']);
    }

    /**
     * Tests, that blocks, content blocks and views are rendered in the given order.
     */
    public function testRenderElements(): void
    {
        $this->addEntity('block', 'embedblock_2');
        $this->addEntity('block_content', '8');
        $this->addView('blaetter_dokumente', ['block_1']);

        $build = $this->createBlock()->getRenderElements(
            "block|embedblock_2\r\n\r\ncontent|8\r\n blaetter_dokumente : block_1 \r\n"
        );

        $this->assertSame(['#markup' => 'block embedblock_2'], $build[0]);
        $this->assertSame(['#markup' => 'block_content 8'], $build[1]);
        $this->assertSame('blaetter_dokumente', $build[2]['title']['#title']['#markup']);
        $this->assertSame(['#markup' => 'view blaetter_dokumente'], $build[2]['content']);
        $this->assertContains('user.permissions', $build['#cache']['contexts']);
        $this->assertSame([], $this->warnings);
    }

    /**
     * Tests, that missing blocks, views and displays are skipped and logged instead of breaking the page.
     */
    public function testMissingElementsAreSkipped(): void
    {
        $this->addView('blaetter_blog', ['block_1']);

        $build = $this->createBlock()->getRenderElements(
            "block|gibtsnicht\ncontent|999999\ngibtsnicht:block_1\nblaetter_blog:block1\nquatsch"
        );

        $this->assertSame([], array_values(array_filter(array_keys($build), 'is_int')));
        $this->assertSame(
            [
                'Grid block: block "gibtsnicht" does not exist',
                'Grid block: block_content "999999" does not exist',
                'Grid block: view display "gibtsnicht:block_1" does not exist',
                'Grid block: view display "blaetter_blog:block1" does not exist',
                'Grid block: unknown entry "quatsch"',
            ],
            $this->warnings
        );
    }

    /**
     * Tests, that views are only shown, if the user may access them.
     */
    public function testViewWithoutAccessIsHidden(): void
    {
        $this->addView('intern', ['block_1'], false);

        $build = $this->createBlock()->getRenderElements('intern:block_1');

        $this->assertSame([], array_values(array_filter(array_keys($build), 'is_int')));
        $this->assertContains('user.permissions', $build['#cache']['contexts']);
    }

    /**
     * Tests the css classes of the grid.
     */
    public function testGridClasses(): void
    {
        $block = $this->createBlock();
        $expected = [
            '1:1' => ['one-half', 'one-half'],
            '1:2' => ['one-third', 'two-thirds'],
            '1:3' => ['one-fourth', 'three-fourths'],
            '3:1' => ['three-fourths', 'one-fourth'],
            '2:1' => ['two-thirds', 'one-third'],
            'unknown' => ['one-half', 'one-half'],
        ];
        foreach ($expected as $grid => [$left, $right]) {
            $this->assertSame($left, $block->calculateLeftGridClass($grid), $grid);
            $this->assertSame($right, $block->calculateRightGridClass($grid), $grid);
        }
    }
}
