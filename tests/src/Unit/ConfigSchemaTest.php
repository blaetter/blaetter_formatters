<?php

namespace Drupal\Tests\blaetter_formatters\Unit;

use Drupal\blaetter_formatters\Plugin\Block\EmbedBlock;
use Drupal\blaetter_formatters\Plugin\Block\GridBlock;
use Drupal\blaetter_formatters\Plugin\Block\SocialMediaBlock;
use Drupal\blaetter_formatters\Plugin\Field\FieldFormatter\InlineFormatter;
use Drupal\blaetter_formatters\Plugin\Field\FieldFormatter\IssueFormatter;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests, that every setting of the blocks and formatters has a config schema.
 *
 * @group blaetter_formatters
 */
#[CoversNothing]
#[Group('blaetter_formatters')]
class ConfigSchemaTest extends UnitTestCase
{
    /**
     * Returns the mapping of the given schema.
     */
    private function getSchemaMapping(string $name): array
    {
        $schema = Yaml::decode(file_get_contents(dirname(__DIR__, 3) . '/config/schema/blaetter_formatters.schema.yml'));
        $this->assertArrayHasKey($name, $schema, 'Missing schema ' . $name);
        return $schema[$name]['mapping'];
    }

    /**
     * Tests the schema of the block settings.
     */
    public function testBlockSettings(): void
    {
        $container = new ContainerBuilder();
        $container->set('string_translation', $this->getStringTranslationStub());
        \Drupal::setContainer($container);

        // the base settings (id, label, provider, ...) are defined by the block_settings type of core
        $base = ['id', 'label', 'label_display', 'provider'];
        foreach (['embed_block' => EmbedBlock::class, 'grid_block' => GridBlock::class, 'socialmedia_block' => SocialMediaBlock::class] as $id => $class) {
            $block = new $class([], $id, ['provider' => 'blaetter_formatters', 'admin_label' => $id]);
            $settings = array_diff(array_keys($block->getConfiguration()), $base);
            $this->assertSame(
                [],
                array_values(array_diff($settings, array_keys($this->getSchemaMapping('block.settings.' . $id)))),
                $id . ' has settings without schema.'
            );
        }
    }

    /**
     * Tests the schema of the formatter settings.
     */
    public function testFormatterSettings(): void
    {
        foreach (['blaetter_formatters_inline' => InlineFormatter::class, 'blaetter_formatters_issue' => IssueFormatter::class] as $id => $class) {
            $own_settings = array_diff_key($class::defaultSettings(), \Drupal\Core\Field\FormatterBase::defaultSettings());
            $this->assertSame(
                [],
                array_values(array_diff(array_keys($own_settings), array_keys($this->getSchemaMapping('field.formatter.settings.' . $id)))),
                $id . ' has settings without schema.'
            );
        }
    }
}
