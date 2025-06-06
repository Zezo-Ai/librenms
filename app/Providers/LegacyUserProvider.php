<?php

/**
 * LegacyUserProvider.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Providers;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LibreNMS\Authentication\LegacyAuth;
use LibreNMS\Exceptions\AuthenticationException;
use LibreNMS\Util\Debug;
use Log;
use Request;
use Session;

class LegacyUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        try {
            return User::find($identifier);
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Retrieve a user by their legacy auth specific identifier.
     *
     * @param  int  $identifier
     * @return Authenticatable|null
     */
    public function retrieveByLegacyId($identifier)
    {
        $legacy_user = LegacyAuth::get()->getUser($identifier);

        return $this->retrieveByCredentials(['username' => $legacy_user['username'] ?? null]);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        try {
            $user = new User();
            $user = $user->where($user->getAuthIdentifierName(), $identifier)->first();

            if (! $user) {
                return null;
            }
        } catch (QueryException) {
            return null;
        }

        $rememberToken = $user->getRememberToken();
        if ($rememberToken && hash_equals($rememberToken, $token)) {
            if (LegacyAuth::get()->userExists($user->username)) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        /** @var User $user */
        $user->setRememberToken($token);
        $timestamps = $user->timestamps;
        $user->timestamps = false;
        $user->save();
        $user->timestamps = $timestamps;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $authorizer = LegacyAuth::get();

        try {
            // try authentication methods
            if ($authorizer->authIsExternal()) {
                $credentials['username'] = $authorizer->getExternalUsername();
            }

            if (empty($credentials['username']) || ! $authorizer->authenticate($credentials)) {
                throw new AuthenticationException();
            }

            return true;
        } catch (AuthenticationException $ae) {
            $auth_message = $ae->getMessage();
            if (Debug::isEnabled()) {
                $auth_message .= '<br /> ' . $ae->getFile() . ': ' . $ae->getLine();
            }
            toast()->error($auth_message);

            $username = $username ?? Session::get('username', $credentials['username']);

            DB::table('authlog')->insert(['user' => $username, 'address' => Request::ip(), 'result' => $auth_message]);
        }

        return false;
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $auth = LegacyAuth::get();
        $type = LegacyAuth::getType();

        // ldap based auth we should bind before using, otherwise searches may fail due to anonymous bind
        if (method_exists($auth, 'bind')) {
            $auth->bind($credentials);
        }

        $username = $credentials['username'] ?? null;
        $auth_id = $auth->getUserid($username);
        $new_user = $auth->getUser($auth_id);

        if (empty($new_user)) {
            // some legacy auth create users in the authenticate method, if it doesn't exist yet, lets try authenticate (Laravel calls retrieveByCredentials first)
            try {
                $auth->authenticate($credentials);
                $auth_id = $auth->getUserid($username);
                $new_user = $auth->getUser($auth_id);
            } catch (AuthenticationException $ae) {
                toast()->error($ae->getMessage());
            }

            if (empty($new_user)) {
                Log::error("Auth Error ($type): No user ($auth_id) [$username] from " . Request::ip());

                return null;
            }
        }

        unset($new_user['user_id']);

        // remove null fields
        $new_user = array_filter($new_user, function ($var) {
            return ! is_null($var);
        });

        // always create an entry in the users table, but separate by type
        $user = User::thisAuth()->firstOrNew(['username' => $username], $new_user);
        /** @var User $user */
        $user->fill($new_user); // fill all attributes
        $user->auth_type = $type; // doing this here in case it was null (legacy)
        $user->auth_id = (string) $auth_id;
        $user->save();

        // create and update roles, if provided
        $roles = $auth->getRoles($user->username);
        if ($roles !== false) {
            $user->syncRoles($roles);
        }

        return $user;
    }

    /**
     * @inheritDoc
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        // TODO: NEEDS TO BE VERIFIED CORRECT SOLUTION
        if (! isset($credentials['password']) || empty($user->getAuthPassword())) {
            return;
        }
        $hasher = app(HasherContract::class);

        if (! $hasher->needsRehash($user->getAuthPassword()) && ! $force) {
            return;
        }

        $user->forceFill([
            $user->getAuthPasswordName() => $hasher->make($credentials['password']),
        ])->save();
    }
}
