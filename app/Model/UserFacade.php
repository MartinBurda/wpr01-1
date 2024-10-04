<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Security\Passwords;

/**
 * Manages user-related operations such as authentication and adding new users.
 */
final class UserFacade implements Nette\Security\Authenticator
{
    // Minimum password length requirement for users
    public const PasswordMinLength = 7;

    // Database table and column names
    private const
        TableName = 'users',
        ColumnId = 'id',
        ColumnUsername = 'username',
        ColumnJmeno = 'jmeno',
        ColumnPrijmeni = 'prijmeni',
        ColumnPasswordHash = 'password',
        ColumnEmail = 'email',
        ColumnRole = 'role';

    // Dependency injection of database explorer and password utilities
    public function __construct(
        private Nette\Database\Explorer $database,
        private Passwords $passwords,
    ) {
    }

    /**
     * Authenticate a user based on provided credentials.
     * Throws an AuthenticationException if authentication fails.
     */
    public function authenticate(string $username, string $password): Nette\Security\SimpleIdentity
	{
		// Fetch the user details from the database by username
		$row = $this->database->table(self::TableName)
			->where(self::ColumnUsername, $username)
			->fetch();

		// Authentication checks
		if (!$row) {
			throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IdentityNotFound);

		} elseif (!$this->passwords->verify($password, $row[self::ColumnPasswordHash])) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.', self::InvalidCredential);

		} elseif ($this->passwords->needsRehash($row[self::ColumnPasswordHash])) {
			$row->update([
				self::ColumnPasswordHash => $this->passwords->hash($password),
			]);
		}

		// Return user identity without the password hash
		$arr = $row->toArray();
		unset($arr[self::ColumnPasswordHash]);
		return new Nette\Security\SimpleIdentity($row[self::ColumnId], $row[self::ColumnRole], $arr);
	}


    /**
     * Add a new user to the database.
     * Throws a DuplicateNameException if the username is already taken.
     */
    public function add(string $username, string $jmeno, string $prijmeni, string $email, string $password): void
	{
		// Validate the email format
		Nette\Utils\Validators::assert($email, 'email');

		// Attempt to insert the new user into the database
		try {
			$this->database->table(self::TableName)->insert([
				self::ColumnUsername => $username,
                self::ColumnJmeno => $jmeno,
                self::ColumnPrijmeni => $prijmeni,
				self::ColumnPasswordHash => $this->passwords->hash($password),
				self::ColumnEmail => $email,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateNameException;
		}
    }

    public function getUserById(int $id)
    {
        $user = $this->database
            ->table('users')
            ->get($id);
    
        if (!$user) {
            throw new Nette\Application\BadRequestException('Stránka nebyla nalezena');
        }
    
        return $user;
    }
    

    public function getUsers()
    {
        return $this->database
            ->table('users')
            ->order('created_at ASC');
    }

    public function deleteUser(int $id): void
{
    $this->database
        ->table('users')
        ->where('id', $id)
        ->delete();
}
// V třídě UserFacade
public function getUsersByName(string $name): Nette\Database\Table\Selection
{
    return $this->database->table(self::TableName)
        ->where(self::ColumnUsername . ' LIKE ?', '%' . $name . '%')
        ->order('created_at ASC');
}


public function changePassword(int $userId, string $newPassword): void
{
    $user = $this->database->table('users')->get($userId);
    if (!$user) {
        throw new \RuntimeException('User not found');
    }

    $user->update([
        'password' => $this->passwords->hash($newPassword),
    ]);
}

public function getUserByRole(string $role)
{
    return $this->database
        ->table('users')
        ->where('role', $role)
        ->fetchAll();
}
}

    