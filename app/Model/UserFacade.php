<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Security\Passwords;
use Nette\Security\Authenticator;
use Nette\Security\SimpleIdentity;
use Nette\Security\AuthenticationException;
use Nette\Database\UniqueConstraintViolationException;
use App\Model\DuplicateNameException;


/**
 * Manages user-related operations such as authentication, adding new users, and modifying user details.
 */
final class UserFacade implements Authenticator
{
    // Minimum password length requirement for users
    public const PasswordMinLength = 7;

    // Database table and column names
    private const
        TableName = 'users',
        ColumnId = 'id',
        ColumnName = 'name',
        ColumnSurname = 'surname',
        ColumnUsername = 'username',
        ColumnPasswordHash = 'password',
        ColumnEmail = 'email',
        ColumnPhone = 'phone',
        ColumnBirthDate = 'birth_date',  
        ColumnAddress = 'address',   
        ColumnRole = 'role',
        ColumnImage = 'image';


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
    public function authenticate(string $username, string $password): SimpleIdentity
    {
        // Fetch the user details from the database by username
        $row = $this->database->table(self::TableName)
            ->where(self::ColumnUsername, $username)
            ->fetch();

        // Authentication checks
        if (!$row) {
            throw new AuthenticationException('The username is incorrect.', self::IdentityNotFound);
        } elseif (!$this->passwords->verify($password, $row[self::ColumnPasswordHash])) {
            throw new AuthenticationException('The password is incorrect.', self::InvalidCredential);
        } elseif ($this->passwords->needsRehash($row[self::ColumnPasswordHash])) {
            $row->update([
                self::ColumnPasswordHash => $this->passwords->hash($password),
            ]);
        }

        // Return user identity without the password hash
        $arr = $row->toArray();
        unset($arr[self::ColumnPasswordHash]);

        return new SimpleIdentity($row[self::ColumnId], $row[self::ColumnRole], $arr);
    }

    /**
     * Add a new user to the database.
     * Throws a DuplicateNameException if the username is already taken.
     */
    public function add(string $username, string $name, string $surname, string $email, string $phone, string $password, string $role, string $image, string $birth_date,string $address ): void
    {
        // Validate email format
        Nette\Utils\Validators::assert($email, 'email');
    
        try {
            $this->database->table(self::TableName)->insert([
                self::ColumnUsername => $username,
                self::ColumnName => $name,
                self::ColumnSurname => $surname,
                self::ColumnPasswordHash => $this->passwords->hash($password),
                self::ColumnEmail => $email,
                self::ColumnPhone => $phone,
                self::ColumnImage => $image,
                self::ColumnRole => $role,
                self::ColumnBirthDate => $birth_date,
                self::ColumnAddress => $address,
            ]);
        } catch (\Nette\Database\UniqueConstraintViolationException $e) {
            // Throw a custom exception
            throw new DuplicateNameException('The username or email is already taken.');
        } catch (\Exception $e) {
            // General exception catch for debugging purposes
            throw new \RuntimeException('Database error: ' . $e->getMessage());
        }
        
    }
    
    

    /**
     * Get a user by their ID.
     */
    public function getUserById(int $id)
    {
        $user = $this->database
            ->table(self::TableName)
            ->get($id);

        if (!$user) {
            throw new Nette\Application\BadRequestException('User not found');
        }

        return $user;
    }

    /**
     * Get all users ordered by creation date.
     */
    public function getAllUsers()
    {
        return $this->database
            ->table(self::TableName);
    }

    /**
     * Delete a user by their ID.
     */
    public function deleteUser(int $id): void
    {
        $this->database
            ->table(self::TableName)
            ->where(self::ColumnId, $id)
            ->delete();
    }

    /**
     * Find users by username.
     */
    public function getUsersByName(string $name): Nette\Database\Table\Selection
    {
        return $this->database->table(self::TableName)
            ->where(self::ColumnUsername . ' LIKE ?', '%' . $name . '%');
   
    }

    /**
     * Change the password of a user.
     */
    public function changePassword(int $userId, string $newPassword): void
    {
        $user = $this->database->table(self::TableName)->get($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $user->update([
            self::ColumnPasswordHash => $this->passwords->hash($newPassword),
        ]);
    }

    /**
     * Edit user details (username, email, role, etc.).
     */
    public function editUser(int $userId, array $values): void
    {
        $currentUser = $this->getUserById($userId);
    
        if (!$currentUser) {
            throw new \RuntimeException('User not found');
        }
    
        // Pokud `username` není nastavený, přeskočíme kontrolu
        if (isset($values['username'])) {
            // Kontrola, jestli uživatelské jméno již není použité jiným uživatelem
            if ($this->getUserByUsername($values['username']) && $currentUser->username !== $values['username']) {
                throw new \RuntimeException('Uživatelské jméno nelze použít.');
            }
        }
    
        // Připraví data pro aktualizaci
        $updateData = [];
    
        // Jen aktualizuje pole, která se změnila
        if (isset($values['name']) && $currentUser->name !== $values['name']) {
            $updateData[self::ColumnName] = $values['name'];
        }
    
        if (isset($values['surname']) && $currentUser->surname !== $values['surname']) {
            $updateData[self::ColumnSurname] = $values['surname'];
        }
    
        if (isset($values['username']) && $currentUser->username !== $values['username']) {
            $updateData[self::ColumnUsername] = $values['username'];
        }
    
        if (isset($values['email']) && $currentUser->email !== $values['email']) {
            $updateData[self::ColumnEmail] = $values['email'];
        }
    
        if (isset($values['role']) && $currentUser->role !== $values['role']) {
            $updateData[self::ColumnRole] = $values['role'];
        }
    
        if (isset($values['image']) && $currentUser->image !== $values['image']) {
            $updateData[self::ColumnImage] = $values['image'];
        }
    
        // Pokud je zadáno nové heslo, aktualizuje se hash hesla
        if (!empty($values['newPassword'])) {
            $updateData[self::ColumnPasswordHash] = $this->passwords->hash($values['newPassword']);
        }
    
        // Aktualizuje databázi pouze v případě, že se něco změnilo
        if (!empty($updateData)) {
            $this->database->table(self::TableName)
                ->where(self::ColumnId, $userId)
                ->update($updateData);
        }
    }

    public function getUserByUsername(string $username)
    {
        return $this->database->table(self::TableName)
            ->where(self::ColumnUsername, $username)
            ->fetch();
    }

    public function searchUsers(string $query): Nette\Database\Table\Selection
    {
        return $this->database->table(self::TableName)
            ->where(
                self::ColumnUsername . ' LIKE ? OR ' .
                self::ColumnName . ' LIKE ? OR ' .
                self::ColumnSurname . ' LIKE ? OR ' .
                self::ColumnEmail . ' LIKE ?',
                "%$query%", "%$query%", "%$query%", "%$query%"
            );
    }

    public function getTechnicianWithLeastRepairs(): ?int
    {
        return $this->database->fetchField("
            SELECT users.id 
            FROM users 
            LEFT JOIN repairs ON users.id = repairs.technician_id 
            WHERE users.role = 'repair'
            GROUP BY users.id 
            ORDER BY COUNT(repairs.technician_id) ASC 
            LIMIT 1
        ");
    }
    




}
