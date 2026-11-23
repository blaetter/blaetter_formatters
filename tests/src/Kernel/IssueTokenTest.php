<?php

namespace Drupal\Tests\blaetter_formatters\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the token [node:blaetter_issue] for the issue of an article.
 *
 * @group blaetter_formatters
 */
#[CoversFunction('blaetter_formatters_issue')]
#[CoversFunction('blaetter_formatters_token_info')]
#[CoversFunction('blaetter_formatters_tokens')]
#[Group('blaetter_formatters')]
#[RunTestsInSeparateProcesses]
class IssueTokenTest extends KernelTestBase
{
    /**
     * {@inheritdoc}
     */
    protected static $modules = [
        'system',
        'user',
        'field',
        'filter',
        'text',
        'node',
        'book',
        'blaetter_formatters',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installEntitySchema('node');
        $this->installSchema('node', ['node_access']);
        $this->installSchema('book', ['book']);
        $this->installConfig(['system', 'node', 'filter', 'book']);
        // Book installs the content type "book" with its configuration.
        foreach (['ausgabe', 'story'] as $type) {
            NodeType::create(['type' => $type, 'name' => $type])->save();
        }
        $this->config('book.settings')
            ->set('allowed_types', ['ausgabe', 'book', 'story'])
            ->set('child_type', 'story')
            ->save();
    }

    /**
     * Creates a node, optionally in the book outline below a parent.
     */
    private function createNode(string $type, string $title, ?NodeInterface $parent = null): NodeInterface
    {
        $node = Node::create(['type' => $type, 'title' => $title]);
        if ($parent) {
            $root = $parent->book['bid'] ?? $parent->id();
            $node->book = ['bid' => $root, 'pid' => $parent->id()];
        } else {
            $node->book = ['bid' => 'new'];
        }
        $node->save();
        // Reload, so $node->book comes from the database like on a real site.
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
        return Node::load($node->id());
    }

    /**
     * Replaces the tokens of the text for the node.
     */
    private function replace(string $text, NodeInterface $node): string
    {
        return \Drupal::token()->replace($text, ['node' => $node], ['clear' => true], new BubbleableMetadata());
    }

    /**
     * An article gets the tokens of its issue.
     */
    public function testArticleInIssue(): void
    {
        $issue = $this->createNode('ausgabe', 'Dezember 2026');
        $article = $this->createNode('story', 'Ein Artikel', $issue);

        $this->assertSame((int) $issue->id(), (int) blaetter_formatters_issue($article)->id());
        $this->assertSame('Dezember 2026', $this->replace('[node:blaetter_issue]', $article));
        $this->assertSame(
            'Dezember 2026/' . $issue->id() . '/Ein Artikel',
            $this->replace('[node:blaetter_issue:title]/[node:blaetter_issue:nid]/[node:title]', $article)
        );
    }

    /**
     * An article deeper in the outline still gets the issue at the root.
     */
    public function testNestedArticle(): void
    {
        $issue = $this->createNode('ausgabe', 'November 2026');
        $section = $this->createNode('story', 'Rubrik', $issue);
        $article = $this->createNode('story', 'Unterseite', $section);

        $this->assertSame('November 2026', $this->replace('[node:blaetter_issue:title]', $article));
    }

    /**
     * Without an issue the tokens are empty.
     */
    public function testNoIssue(): void
    {
        $article = Node::create(['type' => 'story', 'title' => 'Ohne Heft']);
        $article->save();
        $issue = $this->createNode('ausgabe', 'Oktober 2026');
        $other_book = $this->createNode('book', 'Ein anderes Buch');
        $in_other_book = $this->createNode('story', 'Im anderen Buch', $other_book);

        foreach ([Node::load($article->id()), $issue, $in_other_book] as $node) {
            $this->assertNull(blaetter_formatters_issue($node), $node->label());
            $this->assertSame('//' . $node->label(), $this->replace('/[node:blaetter_issue:title]/[node:title]', $node));
        }
    }

    /**
     * The token is described for the token browser and Pathauto.
     */
    public function testTokenInfo(): void
    {
        $info = \Drupal::token()->getInfo();
        $this->assertSame('node', $info['tokens']['node']['blaetter_issue']['type']);
    }
}
