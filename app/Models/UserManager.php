<?php

declare(strict_types=1);

namespace App\Models;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Security\Passwords;

class UserManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly Passwords $passwords,
	) {
	}

	public function add(string $username, string $firstName, string $lastName, string $email, string $phone, string $password): User
	{
		$user = new User();
		$user->setUsername($username);
		$user->setFirstName($firstName);
		$user->setLastName($lastName);
		$user->setEmail($email);
		$user->setPhone($phone);
		$user->setPassword($this->passwords->hash($password));
		$user->setRole('user');
		$user->setIsActive(true);

		$this->em->persist($user);
		$this->em->flush();

		return $user;
	}

	public function findByUsername(string $username): ?User
	{
		return $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
	}

	public function isDuplicate(string $username, string $email): bool
	{
		$qb = $this->em->createQueryBuilder();
		$qb->select('COUNT(u.id)')
			->from(User::class, 'u')
			->where('(u.username = :username OR u.email = :email)')
			->setParameter('username', $username)
			->setParameter('email', $email);

		return (int) $qb->getQuery()->getSingleScalarResult() > 0;
	}

	/**
	 * Returns all users as a Doctrine QueryBuilder (for datagrid compatibility).
	 */
	public function findAll(): \Doctrine\ORM\QueryBuilder
	{
		return $this->em->createQueryBuilder()
			->select('u')
			->from(User::class, 'u');
	}

	public function getById(int $id): ?User
	{
		return $this->em->getRepository(User::class)->find($id);
	}

	/** @param array<string, mixed> $data */
	public function update(int $id, array $data): void
	{
		$user = $this->getById($id);
		if (!$user) {
			throw new \Exception('Uživatel nebyl nalezen.');
		}

		if (isset($data['username'])) {
			$user->setUsername($data['username']);
		}
		if (isset($data['first_name'])) {
			$user->setFirstName($data['first_name']);
		}
		if (isset($data['last_name'])) {
			$user->setLastName($data['last_name']);
		}
		if (isset($data['email'])) {
			$user->setEmail($data['email']);
		}
		if (isset($data['phone'])) {
			$user->setPhone($data['phone']);
		}
		if (isset($data['password'])) {
			$user->setPassword($this->passwords->hash($data['password']));
		}
		if (isset($data['role'])) {
			$user->setRole($data['role']);
		}
		if (array_key_exists('is_active', $data)) {
			$user->setIsActive((bool) $data['is_active']);
		}

		$this->em->flush();
	}

	/** Hard delete — audit references to the user (created_by/edited_by) become NULL (FK ON DELETE SET NULL); login_logs stay. */
	public function delete(int $id): void
	{
		$user = $this->getById($id);
		if ($user) {
			$this->em->remove($user);
			$this->em->flush();
		}
	}
}
