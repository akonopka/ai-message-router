<?php

namespace App\Tool;

use App\Agent\ToolInvocationTracker;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsTool('send_department_email', 'Sends an email to the appropriate department based on the issue description')]

class SendDepartmentEmailTool
{
    private const ALLOWED_DEPARTMENTS = [
        'human-resources@example.com',
        'help-desk@example.com',
        'it@example.com',
        'kadry@example.com',
        'other@example.com',
    ];

    public function __construct(
        private MailerInterface $mailer,
        private RequestStack $requestStack,
        private ToolInvocationTracker $tracker,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private string $fromAddress
    ) {}

    /**
     * @param string $departmentEmail The department email address to route the issue to.
     *                                 Must be exactly one of the addresses listed in the system prompt.
     */
    public function __invoke(string $departmentEmail): bool
    {
        $this->tracker->invoked = true;

        if (!in_array($departmentEmail, self::ALLOWED_DEPARTMENTS, true)) {
            $departmentEmail = 'other@example.com';
        }

        $request = $this->requestStack->getCurrentRequest();
        $data = $request->toArray();
        $senderEmail = $data['email'];
        $message = $data['message'];
        $subject = $data['subject'] ?? 'New Issue Reported';

        $email = (new Email())
            ->from($this->fromAddress)
            ->replyTo($senderEmail)
            ->to($departmentEmail)
            ->subject($subject)
            ->text($message);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            return false;
        }

        return true;
    }
}
