<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\LoginLog;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class LoginLogManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    )
    {
    }

    public function logAttempt(string $username, bool $success, string $ipAddress, string $userAgent): void
    {
        $log = new LoginLog();
        $log->setUsernameAttempt($username);
        $log->setIsSuccess($success);
        $log->setIpAddress($ipAddress);
        $log->setUserAgent($userAgent);
        $log->setCreatedAt(new DateTime());

        $this->em->persist($log);

        $this->em->flush();
    }
}
