<?php declare(strict_types=1);

namespace App\Core\Security;

use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator as NetteAuthenticator;
use Nette\Security\SimpleIdentity;
use Nette\Security\Passwords;
use Nette\Http\Request;
use App\Models\UserManager;
use App\Models\LoginLogManager;

readonly class Authenticator implements NetteAuthenticator
{
	public function __construct(
		private UserManager     $userManager,
		private LoginLogManager $loginLogManager,
		private Passwords       $passwords,
		private Request         $httpRequest,
	) {
	}

	public function authenticate(string $username, string $password): SimpleIdentity
	{
		$ip = $this->httpRequest->getRemoteAddress() ?? 'unknown';
		$userAgent = $this->httpRequest->getHeader('User-Agent') ?? 'unknown';

		$row = $this->userManager->findByUsername($username);

		if (!$row) {
			$this->loginLogManager->logAttempt($username, false, $ip, $userAgent);
			throw new AuthenticationException('Nebylo nalezeno uživatelské jméno.');
		}

		if (!$this->passwords->verify($password, $row->getPassword())) {
			$this->loginLogManager->logAttempt($username, false, $ip, $userAgent);
			throw new AuthenticationException('Zadané heslo není správné.');
		}

		if (!$row->isActive()) {
			$this->loginLogManager->logAttempt($username, false, $ip, $userAgent);
			throw new AuthenticationException('Tento účet není aktivní.');
		}

		$this->loginLogManager->logAttempt($username, true, $ip, $userAgent);

		return new SimpleIdentity(
			$row->getId(),
			$row->getRole(),
			[
				'username' => $row->getUsername(),
				'name' => $row->getFirstName() . ' ' . $row->getLastName(),
			]
		);
	}
}
