<?php

namespace Drupal\Tests\blaetter_formatters\Unit;

use Drupal\contact\ContactFormInterface;
use Drupal\contact\MessageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the hooks and callbacks of blaetter_formatters.module.
 *
 * @group blaetter_formatters
 */
#[CoversFunction('blaaetter_formatters_allowed_media_providers')]
#[CoversFunction('blaetter_formatters_user_update')]
#[CoversFunction('blaetter_formatters_mask_iban')]
#[CoversFunction('blaetter_formatters_mail_alter')]
#[CoversFunction('blaetter_formatters_contact_message_defaults')]
#[Group('blaetter_formatters')]
class ModuleHooksTest extends UnitTestCase
{
    /**
     * The sent mails as [to, params].
     *
     * @var array
     */
    private $mails = [];

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3) . '/blaetter_formatters.module';
    }

    /**
     * Sets up the services used by the hook for changed user accounts.
     */
    private function setUpContainer(string $shop_mail): void
    {
        $mail_manager = $this->createMock(MailManagerInterface::class);
        $mail_manager->method('mail')->willReturnCallback(function ($module, $key, $to, $langcode, $params) {
            $this->mails[] = [$to, (string) $params['message']];
            return ['result' => true];
        });
        $current_user = $this->createMock(AccountProxyInterface::class);
        $current_user->method('getPreferredLangcode')->willReturn('de');

        $container = new ContainerBuilder();
        $container->set('plugin.manager.mail', $mail_manager);
        $container->set('current_user', $current_user);
        $container->set('config.factory', $this->getConfigFactoryStub([
            'nodeshop.settings' => ['payment' => ['mail_to' => $shop_mail]],
            'system.site' => ['mail' => 'website@blaetter.de'],
        ]));
        $container->set('string_translation', $this->getStringTranslationStub());
        $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
        $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
        $container->set('logger.factory', $logger_factory);
        \Drupal::setContainer($container);
    }

    /**
     * Creates a user with the given field values.
     */
    private function createUser(array $values, ?UserInterface $original = null): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('bundle')->willReturn('user');
        $user->method('id')->willReturn(15);
        $user->method('get')->willReturnCallback(fn ($field) => (object) ['value' => $values[$field] ?? null]);
        $user->method('getOriginal')->willReturn($original);
        $url = $this->createMock(Url::class);
        $url->method('toString')->willReturn('https://www.blaetter.de/user/15');
        $user->method('toUrl')->willReturn($url);
        return $user;
    }

    /**
     * Tests, that the provider options depend on the media type and are not cached.
     */
    public function testAllowedMediaProviders(): void
    {
        $definition = $this->createMock(FieldStorageDefinitionInterface::class);
        $options = [];
        foreach (['generic_iframe', 'remote_video', 'image'] as $bundle) {
            $media = $this->createMock(MediaInterface::class);
            $media->method('bundle')->willReturn($bundle);
            $cacheable = true;
            $options[$bundle] = blaaetter_formatters_allowed_media_providers($definition, $media, $cacheable);
            $this->assertFalse($cacheable, $bundle);
        }

        $this->assertSame(['Detektor.fm' => 'Detektor.fm', 'Google Maps' => 'Google Maps'], $options['generic_iframe']);
        $this->assertSame(['YouTube' => 'YouTube', 'Vimeo' => 'Vimeo'], $options['remote_video']);
        $this->assertSame([], $options['image']);
        $this->assertSame([], blaaetter_formatters_allowed_media_providers($definition));
    }

    /**
     * Tests, that the shop is notified about a changed address of a user.
     */
    public function testChangedAddressIsSent(): void
    {
        $this->setUpContainer('abo@blaetter.de');
        $original = $this->createUser(['field_city' => 'Berlin', 'field_zip' => '10117']);

        blaetter_formatters_user_update($this->createUser(['field_city' => 'Bonn', 'field_zip' => '10117'], $original));

        $this->assertCount(1, $this->mails);
        [$to, $message] = $this->mails[0];
        $this->assertSame('abo@blaetter.de', $to);
        $this->assertStringContainsString('city changed from "Berlin" to "Bonn"', $message);
        $this->assertStringNotContainsString('postal code', $message);
    }

    /**
     * Tests, that no mail is sent, if no address field changed.
     */
    public function testUnchangedAddressIsNotSent(): void
    {
        $this->setUpContainer('abo@blaetter.de');
        $values = ['field_city' => 'Berlin'];

        blaetter_formatters_user_update($this->createUser($values, $this->createUser($values)));
        blaetter_formatters_user_update($this->createUser($values));

        $this->assertSame([], $this->mails);
    }

    /**
     * Tests, that the mail address of the site is used without the mail address of the shop.
     */
    public function testMailAddressOfTheSiteAsFallback(): void
    {
        $this->setUpContainer('');

        blaetter_formatters_user_update(
            $this->createUser(['field_city' => 'Bonn'], $this->createUser(['field_city' => 'Berlin']))
        );

        $this->assertSame('website@blaetter.de', $this->mails[0][0]);
    }

    /**
     * Tests the masking of IBANs.
     */
    public function testMaskIban(): void
    {
        $this->assertSame('DE** **** **** **** **30 00', blaetter_formatters_mask_iban('DE89 3704 0044 0532 0130 00'));
        $this->assertSame('DE** **** **** **** **30 00', blaetter_formatters_mask_iban(' de89370400440532013000 '));
        $this->assertSame('AT** **** **** **** 3201', blaetter_formatters_mask_iban('AT61 1904 3002 3457 3201'));
        $this->assertSame('****', blaetter_formatters_mask_iban('DE12'));
        $this->assertSame('', blaetter_formatters_mask_iban(''));
    }

    /**
     * Tests, that only the mail to the shop contains the complete IBAN of a SEPA mandate.
     */
    public function testSepaMandateIbanIsMaskedInCopy(): void
    {
        $this->setUpContainer('abo@blaetter.de');
        $values = [
            'field_vorname' => 'Erika', 'field_nachname' => 'Muster', 'mail' => 'erika@example.com',
            'field_strasse_und_hausnummer' => 'Musterweg 1', 'field_postleitzahl' => '10117', 'field_stadt' => 'Berlin',
            'field_land' => 'Deutschland', 'field_iban' => 'DE89 3704 0044 0532 0130 00', 'field_kundennummer' => '4711',
            'field_ermaechtigung' => 1, 'field_datenschutzhinweise' => 1,
        ];
        $form = $this->createMock(ContactFormInterface::class);
        $form->method('get')->with('id')->willReturn('sepa_lastschriftmandat');
        $contact_message = $this->createMock(MessageInterface::class);
        $contact_message->method('getContactForm')->willReturn($form);
        $contact_message->method('getFields')->willReturn([]);
        $contact_message->method('get')->willReturnCallback(fn ($field) => (object) ['value' => $values[$field]]);

        $bodies = [];
        foreach (['contact_page_mail', 'contact_page_copy'] as $id) {
            $message = ['id' => $id, 'params' => ['contact_message' => $contact_message], 'body' => []];
            blaetter_formatters_mail_alter($message);
            $bodies[$id] = implode("\n", $message['body']);
            $this->assertArrayNotHasKey('send', $message, $id . ' must not be treated as spam.');
        }

        $this->assertStringContainsString('DE89 3704 0044 0532 0130 00', $bodies['contact_page_mail']);
        $this->assertStringNotContainsString('3704 0044', $bodies['contact_page_copy']);
        $this->assertStringContainsString('DE** **** **** **** **30 00', $bodies['contact_page_copy']);
    }

    /**
     * Tests, that contact messages without subject or sender name get them from the fields or the form.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerContactMessageDefaults')]
    public function testContactMessageDefaults(
        ?string $subject,
        ?string $sender_name,
        array $values,
        ?string $expected_subject,
        ?string $expected_name
    ): void {
        $form = $this->createMock(ContactFormInterface::class);
        $form->method('label')->willReturn('SEPA-Lastschriftmandat');
        $contact_message = $this->createMock(MessageInterface::class);
        $contact_message->method('getSubject')->willReturn($subject);
        $contact_message->method('getSenderName')->willReturn($sender_name);
        $contact_message->method('getSenderMail')->willReturn('erika@example.com');
        $contact_message->method('getContactForm')->willReturn($form);
        $contact_message->method('hasField')->willReturnCallback(fn ($field) => array_key_exists($field, $values));
        $contact_message->method('get')->willReturnCallback(fn ($field) => (object) ['value' => $values[$field]]);
        if (null === $expected_subject) {
            $contact_message->expects($this->never())->method('setSubject');
        } else {
            $contact_message->expects($this->once())->method('setSubject')->with($expected_subject);
        }
        if (null === $expected_name) {
            $contact_message->expects($this->never())->method('setSenderName');
        } else {
            $contact_message->expects($this->once())->method('setSenderName')->with($expected_name);
        }

        $form_array = [];
        blaetter_formatters_contact_message_defaults(
            'contact_message',
            $contact_message,
            $form_array,
            $this->createMock(FormStateInterface::class)
        );
    }

    /**
     * Data for testContactMessageDefaults().
     */
    public static function providerContactMessageDefaults(): array
    {
        $erika = ['field_vorname' => 'Erika', 'field_nachname' => 'Muster'];
        return [
            'SEPA mandate' => [null, null, $erika, 'Erika Muster', 'Erika Muster'],
            'empty values' => ['', ' ', ['field_vorname' => 'Erika', 'field_nachname' => null], 'Erika', 'Erika'],
            'no name fields' => [null, null, [], 'SEPA-Lastschriftmandat', 'erika@example.com'],
            'empty name fields' => [null, null, ['field_vorname' => '', 'field_nachname' => ' '], 'SEPA-Lastschriftmandat', 'erika@example.com'],
            'contact form' => ['Frage zum Abo', 'Erika M.', $erika, null, null],
        ];
    }
}
