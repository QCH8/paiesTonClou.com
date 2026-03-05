<?php

namespace App\Services;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class PaymentConfirmationEmail
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function sendOrderPaidConfirmation(Order $order): void
    {
        $customerEmail = $order->getCustomerEmail();
        if (!$customerEmail) {
            return;
        }

        $orderRef = $order->getNumber() ?: sprintf('#%d', $order->getId() ?? 0);

        $email = (new TemplatedEmail())
            ->from(new Address('payment@paiestonclou.fr', 'Payes Ton Clou'))
            ->to(new Address($customerEmail))
            ->subject(sprintf('Confirmation de commande %s', $orderRef))
            ->htmlTemplate('emails/order_paid_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}
