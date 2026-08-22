<?php

namespace App\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageRouterControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testInvalidEmailReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/messages',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'zly-adres',
                'message' => 'test',
            ]),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testMissingEmailReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/messages',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'message' => 'test',
            ]),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testMissingMessageReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/messages',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'jan.nowak@example.com',
            ]),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    #[DataProvider('messageProvider')]
    public function testMessageIsRoutedToCorrectDepartment(string $message, string $expectedDepartment, ?string $subject = null): void
    {
        $client = static::createClient();
        $senderEmail = 'jan.nowak@example.com';

        $payload = [
            'email' => $senderEmail,
            'message' => $message,
        ];
        if ($subject !== null) {
            $payload['subject'] = $subject;
        }

        $client->request(
            'POST',
            '/api/v1/messages',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', $expectedDepartment);
        $this->assertEmailHeaderSame($email, 'Reply-To', $senderEmail);
    }

    public static function messageProvider(): array
    {
        return [
            'IT issue, no subject' => [
                'Nie działa mi komputer, proszę o pomoc.',
                'it@example.com',
            ],
            'Leave request, with subject' => [
                'Chciałbym zgłosić urlop na jutro.',
                'kadry@example.com',
                'Wniosek urlopowy',
            ],
            'Recruitment question, no subject' => [
                'Chcę zgłosić kandydata na stanowisko.',
                'human-resources@example.com',
            ],
            'Other question, no subject' => [
                'Chcę zorganizować spotkanie z klientem w sprawie nowego projektu',
                'other@example.com',
            ],
        ];
    }
}
