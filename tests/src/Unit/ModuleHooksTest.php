<?php

namespace Drupal\Tests\blaetter_formatters\Unit;

use Drupal\contact\ContactFormInterface;
use Drupal\contact\MessageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
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
#[CoversFunction('blaetter_formatters_check_contact_address')]
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
     * Tests, that an empty field before or after the change does not break the mail.
     *
     * Html::escape() only takes strings: an empty value caused a TypeError on
     * saving the account, the shop got no mail and the customer an error page.
     */
    public function testChangedAddressWithEmptyFieldIsSent(): void
    {
        $this->setUpContainer('abo@blaetter.de');

        blaetter_formatters_user_update(
            $this->createUser(['field_phone' => '030 123'], $this->createUser(['field_city' => 'Berlin']))
        );
        blaetter_formatters_user_update(
            $this->createUser([], $this->createUser(['field_street_addon' => 'Hinterhaus']))
        );

        $this->assertCount(2, $this->mails);
        $this->assertStringContainsString('phone changed from "" to "030 123"', $this->mails[0][1]);
        $this->assertStringContainsString('city changed from "Berlin" to ""', $this->mails[0][1]);
        $this->assertStringContainsString('street addon changed from "Hinterhaus" to ""', $this->mails[1][1]);
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
        $this->assertStringContainsString('Abo- oder Kundennummer: 4711', $bodies['contact_page_mail']);
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

    /**
     * Tests, that the copy of a cancellation leaves out the free text, which only goes to the shop.
     */
    public function testCancellationCopyWithoutMessage(): void
    {
        $this->setUpContainer('abo@blaetter.de');
        $container = \Drupal::getContainer();
        $date_formatter = $this->createMock(DateFormatterInterface::class);
        $date_formatter->method('format')->willReturn('12. November 2026 - 10:48');
        $container->set('date.formatter', $date_formatter);
        $container->set('datetime.time', $this->createMock(TimeInterface::class));
        $container->set('messenger', $this->createMock(MessengerInterface::class));

        $values = [
            'field_vorname' => 'Erika', 'field_nachname' => 'Muster', 'mail' => 'erika@example.com',
            'field_strasse_und_hausnummer' => 'Musterweg 1', 'field_postleitzahl' => '10117', 'field_stadt' => 'Berlin',
            'field_land' => 'Deutschland', 'field_kundennummer' => '4711', 'field_kuendigungszeitraum' => null,
            'field_nachricht' => 'Bitte zum Jahresende', 'field_datenschutzhinweise' => 1,
        ];
        $form = $this->createMock(ContactFormInterface::class);
        $form->method('get')->with('id')->willReturn('kuendigung_abonnement');
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

        $this->assertStringContainsString('Bitte zum Jahresende', $bodies['contact_page_mail']);
        $this->assertStringNotContainsString('Bitte zum Jahresende', $bodies['contact_page_copy']);
        $this->assertStringNotContainsString('Nachricht:', $bodies['contact_page_copy']);
        $this->assertStringContainsString('Abo- oder Kundennummer: 4711', $bodies['contact_page_copy']);
        $this->assertStringContainsString('12. November 2026 - 10:48', $bodies['contact_page_copy']);
    }

    /**
     * Tests, that a link in the free text of a cancellation stops the copy as well, although it leaves the text out.
     */
    public function testCancellationCopyWithLinkInMessageIsSpam(): void
    {
        $this->setUpContainer('abo@blaetter.de');
        $container = \Drupal::getContainer();
        $container->set('date.formatter', $this->createMock(DateFormatterInterface::class));
        $container->set('datetime.time', $this->createMock(TimeInterface::class));
        $container->set('messenger', $this->createMock(MessengerInterface::class));

        $values = [
            'field_vorname' => 'Erika', 'field_nachname' => 'Muster', 'mail' => 'erika@example.com',
            'field_strasse_und_hausnummer' => 'Musterweg 1', 'field_postleitzahl' => '10117', 'field_stadt' => 'Berlin',
            'field_land' => 'Deutschland', 'field_kundennummer' => '', 'field_kuendigungszeitraum' => null,
            'field_nachricht' => 'Siehe https://example.com/offer', 'field_datenschutzhinweise' => 1,
        ];
        $form = $this->createMock(ContactFormInterface::class);
        $form->method('get')->with('id')->willReturn('kuendigung_abonnement');
        $contact_message = $this->createMock(MessageInterface::class);
        $contact_message->method('getContactForm')->willReturn($form);
        $contact_message->method('getFields')->willReturn([]);
        $contact_message->method('get')->willReturnCallback(fn ($field) => (object) ['value' => $values[$field]]);

        $message = ['id' => 'contact_page_copy', 'params' => ['contact_message' => $contact_message], 'body' => []];
        blaetter_formatters_mail_alter($message);
        $this->assertFalse($message['send']);
    }

    /**
     * Tests the plausibility check of the cancellation and SEPA mandate forms.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCheckContactAddress')]
    public function testCheckContactAddress(array $values, array $expected): void
    {
        $base = [
            'field_vorname' => 'Erika', 'field_nachname' => 'Muster', 'field_strasse_und_hausnummer' => 'Musterweg 1',
            'field_postleitzahl' => '10117', 'field_stadt' => 'Berlin', 'field_land' => 'Deutschland',
            'field_kundennummer' => '', 'field_nachricht' => '',
        ];
        $this->assertSame($expected, blaetter_formatters_check_contact_address($values + $base));
    }

    /**
     * Data for testCheckContactAddress().
     */
    public static function providerCheckContactAddress(): array
    {
        return [
            'customer' => [[], []],
            'customer number' => [['field_kundennummer' => ' 123456 '], []],
            'customer number with letters' => [['field_kundennummer' => '4711-AB'], []],
            'customer number starting with a letter' => [
                ['field_kundennummer' => 'AB-4711'],
                ['field_kundennummer' => 'customer_number'],
            ],
            'postcode with prefix' => [['field_postleitzahl' => 'D-10117'], []],
            'postcode with space' => [['field_postleitzahl' => ' 10117 '], []],
            'Austria' => [['field_postleitzahl' => '1010', 'field_land' => 'Österreich'], []],
            'Switzerland' => [['field_postleitzahl' => 'CH-8001', 'field_land' => 'Schweiz'], []],
            'other country' => [['field_postleitzahl' => 'SW1A 1AA', 'field_land' => 'Großbritannien'], []],
            'mail address in message' => [['field_nachricht' => 'Bitte an erika@example.com antworten'], []],
            'street without number' => [['field_strasse_und_hausnummer' => 'Postfach'], []],
            'bot' => [
                [
                    'field_vorname' => 'Megyaeob', 'field_nachname' => 'Dnawzkeh',
                    'field_strasse_und_hausnummer' => 'sgnPVFCIjldRflNKSiJUdeeE', 'field_postleitzahl' => 'rwZfsvzCKY',
                    'field_stadt' => 'VXfQxXCUqTEenyAUOSRq', 'field_kundennummer' => 'iMdrbhrTThKSTPjwHFXFz',
                    'field_nachricht' => 'PHWZlerlodusJRRHyPjs',
                ],
                ['field_postleitzahl' => 'postcode', 'field_kundennummer' => 'customer_number'],
            ],
            'four digits in Germany' => [['field_postleitzahl' => '1011'], ['field_postleitzahl' => 'postcode']],
            'five digits in Austria' => [
                ['field_postleitzahl' => '10101', 'field_land' => 'austria'],
                ['field_postleitzahl' => 'postcode'],
            ],
            'other country without digit' => [
                ['field_postleitzahl' => 'abcdef', 'field_land' => 'Frankreich'],
                ['field_postleitzahl' => 'postcode'],
            ],
            'empty postcode' => [['field_postleitzahl' => ''], ['field_postleitzahl' => 'postcode']],
            'link in name' => [['field_nachname' => 'www.example.com'], ['field_nachname' => 'link']],
            'mail address in city' => [['field_stadt' => 'erika@example.com'], ['field_stadt' => 'link']],
            'link in message' => [['field_nachricht' => 'http://example.com'], ['field_nachricht' => 'link']],
            'no postcode field' => [['field_postleitzahl' => null], ['field_postleitzahl' => 'postcode']],
        ];
    }
}
