<?php

namespace Drupal\Tests\blaetter_formatters\Unit\Plugin\Block;

use Drupal\blaetter_formatters\Plugin\Block\EmbedBlock;
use Drupal\blaetter_formatters\Plugin\Block\GridBlock;
use Drupal\blaetter_formatters\Plugin\Block\SocialMediaBlock;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests, that new blocks can be configured without PHP warnings.
 *
 * @group blaetter_formatters
 */
#[CoversClass(EmbedBlock::class)]
#[CoversClass(GridBlock::class)]
#[CoversClass(SocialMediaBlock::class)]
#[Group('blaetter_formatters')]
class BlockDefaultsTest extends UnitTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $container = new ContainerBuilder();
        $container->set('string_translation', $this->getStringTranslationStub());
        \Drupal::setContainer($container);
    }

    public static function blockProvider(): array
    {
        return [
            'embed' => [EmbedBlock::class, 'embed_block'],
            'grid' => [GridBlock::class, 'grid_block'],
            'social media' => [SocialMediaBlock::class, 'socialmedia_block'],
        ];
    }

    /**
     * Tests, that the form of a new block uses only configuration with default values.
     */
    #[DataProvider('blockProvider')]
    public function testFormOfNewBlock(string $class, string $plugin_id): void
    {
        $warnings = [];
        set_error_handler(function ($severity, $message) use (&$warnings) {
            $warnings[] = $message;
            return true;
        });
        try {
            $block = new $class([], $plugin_id, ['provider' => 'blaetter_formatters', 'admin_label' => $plugin_id]);
            $form = $block->blockForm([], new FormState());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertNotEmpty($form);
    }

    /**
     * Tests, that the social media block only stores the chosen providers.
     */
    public function testSocialMediaBlockStoresChosenProviders(): void
    {
        $block = new SocialMediaBlock([], 'socialmedia_block', ['provider' => 'blaetter_formatters', 'admin_label' => 'x']);
        $form = [];
        $block->blockSubmit($form, (new FormState())->setValues([
            'socialmedia_settings' => [
                'socialmedia_provider' => ['twitter' => 'twitter', 'facebook' => 0, 'rss' => 'rss'],
                'block_layout' => 'block block--white',
            ],
        ]));

        $configuration = $block->getConfiguration();
        $this->assertSame(['twitter' => 'twitter', 'rss' => 'rss'], $configuration['socialmedia_provider']);
        $this->assertSame('block block--white', $configuration['block_layout']);
    }
}
